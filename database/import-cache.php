<?php
/**
 * Cache-to-Database Importer
 *
 * Membaca file cache .json.gz dari writable/cache/ dan mengimport
 * data ke tabel database (researchers, publications, institutions).
 *
 * Usage:
 *   php database/import-cache.php [--module=all|orcid|scopus|citation] [--dry-run]
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/library/autoload.php';

use Sangia\Api\Config\Config;
use Sangia\Api\Config\Database;

Config::load();
$pdo = Database::connection();

$module = 'all';
$dryRun = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--module='))  $module = substr($arg, 9);
    if ($arg === '--dry-run')                $dryRun = true;
}

$cacheBase = dirname(__DIR__) . '/writable/cache/';
$stats     = ['researchers' => 0, 'publications' => 0, 'institutions' => 0, 'skipped' => 0];

echo "Wizdam Cache Importer\n";
echo "Mode: " . ($dryRun ? 'DRY RUN' : 'LIVE') . " | Module: $module\n\n";

// ── ORCID → researchers ───────────────────────────────────────────────────
if (in_array($module, ['all', 'orcid'])) {
    $orcidDir = $cacheBase . 'ORCID/';
    if (is_dir($orcidDir)) {
        foreach (glob($orcidDir . '*.json.gz') as $file) {
            $data = readCacheFile($file);
            if (!$data) continue;

            // Person data cache
            if (isset($data['person'])) {
                $person = $data['person'];
                $orcid  = extractOrcid($file, $data);

                $row = [
                    'orcid_id'  => $orcid,
                    'full_name' => buildName($person['name'] ?? []),
                    'biography' => $person['biography']['content'] ?? null,
                    'website'   => extractWebsite($person['researcher-urls'] ?? []),
                ];

                if (!$dryRun) {
                    upsertResearcher($pdo, $row);
                }
                $stats['researchers']++;
                echo "  [ORCID] {$row['full_name']} ($orcid)\n";
            }
        }
    }
}

// ── Scopus → researchers (metrics) ───────────────────────────────────────
if (in_array($module, ['all', 'scopus'])) {
    $scopusDir = $cacheBase . 'Scopus/';
    if (is_dir($scopusDir)) {
        foreach (glob($scopusDir . '*.json.gz') as $file) {
            $data = readCacheFile($file);
            if (!$data) continue;

            // Author summary
            $author = $data['author-retrieval-response'][0]['author-profile'] ?? null;
            if (!$author) continue;

            $name      = $author['preferred-name'] ?? [];
            $fullName  = trim(($name['given-name'] ?? '') . ' ' . ($name['surname'] ?? ''));
            $orcid     = $data['author-retrieval-response'][0]['coredata']['orcid'] ?? null;
            $hIndex    = (int) ($data['author-retrieval-response'][0]['h-index'] ?? 0);
            $docCount  = (int) ($author['publication-range']['@end'] ?? 0);

            $affil  = $author['affiliation-current']['affiliation'] ?? [];
            $instName = is_array($affil) ? ($affil['preferred-name']['$'] ?? null) : null;

            $row = [
                'orcid_id'          => $orcid,
                'full_name'         => $fullName,
                'h_index'           => $hIndex,
                'total_publications'=> $docCount,
                'institution_name'  => $instName,
            ];

            if (!$dryRun) {
                upsertResearcher($pdo, $row);
            }
            $stats['researchers']++;
            echo "  [Scopus] $fullName (h=$hIndex)\n";
        }
    }
}

// ── Crossref / DOI → publications ─────────────────────────────────────────
if (in_array($module, ['all', 'citation'])) {
    $citDir = $cacheBase . 'Citation/';
    if (is_dir($citDir)) {
        foreach (glob($citDir . '*.json.gz') as $file) {
            $data = readCacheFile($file);
            if (!$data) continue;

            // Crossref work object
            $work = $data['message'] ?? $data['work'] ?? $data;
            if (empty($work['title'])) continue;

            $title   = is_array($work['title']) ? ($work['title'][0] ?? '') : $work['title'];
            $doi     = $work['DOI'] ?? null;
            $year    = (int) ($work['published']['date-parts'][0][0]
                        ?? $work['published-print']['date-parts'][0][0]
                        ?? $work['issued']['date-parts'][0][0]
                        ?? 0);
            $journal = is_array($work['container-title'])
                ? ($work['container-title'][0] ?? null)
                : ($work['container-title'] ?? null);
            $cites   = (int) ($work['is-referenced-by-count'] ?? 0);
            $authors = buildAuthorsList($work['author'] ?? []);
            $type    = $work['type'] ?? 'article';

            $row = [
                'doi'              => $doi,
                'title'            => $title,
                'abstract'         => strip_tags($work['abstract'] ?? ''),
                'authors_list'     => $authors,
                'journal_title'    => $journal,
                'publication_year' => $year ?: null,
                'cited_by_count'   => $cites,
                'document_type'    => mapDocType($type),
                'access_type'      => 'open_access',
            ];

            if (!$dryRun) {
                upsertPublication($pdo, $row);
            }
            $stats['publications']++;
            echo "  [DOI] " . substr($title, 0, 60) . " ($doi)\n";
        }
    }
}

// ── Summary ────────────────────────────────────────────────────────────────
echo "\n=== Import " . ($dryRun ? '(DRY RUN) ' : '') . "complete ===\n";
echo "  Researchers:  {$stats['researchers']}\n";
echo "  Publications: {$stats['publications']}\n";
echo "  Institutions: {$stats['institutions']}\n";

// ── Helper functions ────────────────────────────────────────────────────────

function readCacheFile(string $path): ?array
{
    $compressed = file_get_contents($path);
    if (!$compressed) return null;
    $json = gzdecode($compressed);
    if (!$json) return null;
    $data = json_decode($json, true);
    // Unwrap cache envelope if present
    if (isset($data['data'])) $data = $data['data'];
    return is_array($data) ? $data : null;
}

function extractOrcid(string $filename, array $data): string
{
    // Try from data first
    if (isset($data['orcid-identifier']['path'])) {
        return $data['orcid-identifier']['path'];
    }
    // Extract from filename: person_0000-0001-2345-6789_abcd.json.gz
    preg_match('/(\d{4}-\d{4}-\d{4}-\d{3}[\dX])/', basename($filename), $m);
    return $m[1] ?? 'unknown';
}

function buildName(array $name): string
{
    $given  = $name['given-names']['value'] ?? '';
    $family = $name['family-name']['value'] ?? '';
    return trim("$given $family");
}

function buildAuthorsList(array $authors): string
{
    return implode(', ', array_map(
        fn($a) => trim(($a['given'] ?? '') . ' ' . ($a['family'] ?? '')),
        $authors
    ));
}

function extractWebsite(array $urls): ?string
{
    foreach ($urls['researcher-url'] ?? [] as $u) {
        return $u['url']['value'] ?? null;
    }
    return null;
}

function mapDocType(string $type): string
{
    return match ($type) {
        'journal-article'       => 'article',
        'proceedings-article'   => 'conference',
        'book-chapter'          => 'book_chapter',
        'book'                  => 'book',
        'dissertation'          => 'thesis',
        'dataset'               => 'dataset',
        default                 => 'article',
    };
}

function upsertResearcher(PDO $pdo, array $row): void
{
    global $stats;

    // Resolve institution_id
    $instId = null;
    if (!empty($row['institution_name'])) {
        $instId = ensureInstitution($pdo, $row['institution_name']);
        $stats['institutions']++;
    }

    $existing = null;
    if (!empty($row['orcid_id'])) {
        $stmt = $pdo->prepare('SELECT id FROM researchers WHERE orcid_id = ?');
        $stmt->execute([$row['orcid_id']]);
        $existing = $stmt->fetchColumn();
    }

    $fields = array_filter([
        'orcid_id'           => $row['orcid_id'] ?? null,
        'full_name'          => $row['full_name'] ?? null,
        'bio'                => $row['biography'] ?? null,
        'website'            => $row['website'] ?? null,
        'h_index'            => $row['h_index'] ?? null,
        'total_publications' => $row['total_publications'] ?? null,
        'institution_id'     => $instId,
    ], fn($v) => $v !== null);

    if ($existing) {
        $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $pdo->prepare("UPDATE researchers SET $sets WHERE id = ?")->execute(
            [...array_values($fields), $existing]
        );
    } else {
        $cols = implode(', ', array_keys($fields));
        $phdr = implode(', ', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT IGNORE INTO researchers ($cols) VALUES ($phdr)")
            ->execute(array_values($fields));
    }
}

function upsertPublication(PDO $pdo, array $row): void
{
    if (empty($row['doi']) && empty($row['title'])) return;

    $existing = null;
    if (!empty($row['doi'])) {
        $stmt = $pdo->prepare('SELECT id FROM publications WHERE doi = ?');
        $stmt->execute([$row['doi']]);
        $existing = $stmt->fetchColumn();
    }

    $fields = array_filter($row, fn($v) => $v !== null && $v !== '');

    if ($existing) {
        $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $pdo->prepare("UPDATE publications SET $sets WHERE id = ?")->execute(
            [...array_values($fields), $existing]
        );
    } else {
        $cols = implode(', ', array_keys($fields));
        $phdr = implode(', ', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT IGNORE INTO publications ($cols) VALUES ($phdr)")
            ->execute(array_values($fields));
    }
}

function ensureInstitution(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM institutions WHERE name = ?');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id;

    $pdo->prepare('INSERT INTO institutions (name) VALUES (?)')->execute([$name]);
    return (int) $pdo->lastInsertId();
}
