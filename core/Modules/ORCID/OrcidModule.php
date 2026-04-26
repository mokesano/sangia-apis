<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\ORCID;

use Sangia\Core\Shared\ApiClients\OrcidClient;
use Sangia\Core\Shared\ApiClients\CrossrefClient;
use Sangia\Core\Shared\Services\CacheService;

/**
 * ORCID Module — OOP replacement for the procedural ORCID_UserData.php.
 */
class OrcidModule
{
    private const CACHE_TTL_PERSON = 86400;   // 24 h
    private const CACHE_TTL_WORKS  = 604800;  // 7 days

    private OrcidClient    $orcidClient;
    private CrossrefClient $crossrefClient;
    private CacheService   $cache;

    public function __construct()
    {
        $this->orcidClient    = new OrcidClient();
        $this->crossrefClient = new CrossrefClient();
        $this->cache          = new CacheService('Orchid');
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public function getProfile(string $orcid, bool $refresh = false, int $limit = 50): array
    {
        $orcid = trim($orcid);
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
            return $this->error(400, "Invalid ORCID format: $orcid");
        }

        if (!$refresh) {
            $cached = $this->cache->get('orcid', $orcid);
            if ($cached !== false) {
                $cached['cache_info'] = ['from_cache' => true];
                return $cached;
            }
        }

        try {
            $personRaw = $this->orcidClient->getPersonData($orcid);
            $worksRaw  = $this->orcidClient->getWorksData($orcid, $limit);
        } catch (\Throwable $e) {
            return $this->error(502, 'ORCID API error: ' . $e->getMessage());
        }

        $person = $this->parsePerson($personRaw);
        $works  = $this->parseWorks($worksRaw);

        $result = [
            'status'         => 'success',
            'orcid'          => $orcid,
            'person_summary' => $person,
            'works'          => $works,
            'works_count'    => count($works),
            'api_version'    => 'v2.2-modular',
            'cache_info'     => ['from_cache' => false],
        ];

        $this->cache->set('orcid', $orcid, $result);
        return $result;
    }

    // ── Parsers ───────────────────────────────────────────────────────────────

    private function parsePerson(array $raw): array
    {
        $name    = $raw['name'] ?? [];
        $bio     = $raw['biography'] ?? [];
        $emails  = $raw['emails']['email'] ?? [];
        $extIds  = $raw['external-identifiers']['external-identifier'] ?? [];
        $urls    = $raw['researcher-urls']['researcher-url'] ?? [];
        $kws     = $raw['keywords']['keyword'] ?? [];
        $addrs   = $raw['addresses']['address'] ?? [];

        return [
            'name'           => trim(($name['given-names']['value'] ?? '') . ' ' . ($name['family-name']['value'] ?? '')),
            'given_names'    => $name['given-names']['value'] ?? '',
            'family_name'    => $name['family-name']['value'] ?? '',
            'credit_name'    => $name['credit-name']['value'] ?? null,
            'bio'            => $bio['content'] ?? null,
            'emails'         => array_map(fn($e) => $e['email'] ?? '', $emails),
            'keywords'       => array_map(fn($k) => $k['content'] ?? '', $kws),
            'external_ids'   => array_map(fn($id) => [
                'type'  => $id['external-id-type'] ?? '',
                'value' => $id['external-id-value'] ?? '',
            ], is_array($extIds) ? $extIds : [$extIds]),
            'urls'           => array_map(fn($u) => [
                'name' => $u['url-name'] ?? '',
                'url'  => $u['url']['value'] ?? '',
            ], is_array($urls) ? $urls : []),
            'country'        => $addrs[0]['country']['value'] ?? null,
        ];
    }

    private function parseWorks(array $raw): array
    {
        $groups = $raw['group'] ?? [];
        $works  = [];

        foreach ($groups as $group) {
            $summaries = $group['work-summary'] ?? [];
            $summary   = $summaries[0] ?? [];

            $title      = $summary['title']['title']['value'] ?? '';
            $extIds     = $summary['external-ids']['external-id'] ?? [];
            $doi        = null;
            $scopusId   = null;

            foreach ((array) $extIds as $ext) {
                if (($ext['external-id-type'] ?? '') === 'doi')         $doi      = $ext['external-id-value'] ?? null;
                if (($ext['external-id-type'] ?? '') === 'eid')         $scopusId = $ext['external-id-value'] ?? null;
                if (($ext['external-id-type'] ?? '') === 'source-work-id') $scopusId ??= $ext['external-id-value'] ?? null;
            }

            $pubDate = $summary['publication-date'] ?? [];
            $year    = (int) ($pubDate['year']['value'] ?? 0);

            $works[] = [
                'title'            => $title,
                'doi'              => $doi,
                'publication_year' => $year ?: null,
                'type'             => $summary['type'] ?? null,
                'journal_title'    => $summary['journal-title']['value'] ?? null,
                'url'              => $summary['url']['value'] ?? null,
                'external_ids'     => ['doi' => $doi, 'scopus_eid' => $scopusId],
            ];
        }

        return $works;
    }

    private function error(int $code, string $message): array
    {
        http_response_code($code);
        return ['status' => 'error', 'code' => $code, 'message' => $message];
    }
}
