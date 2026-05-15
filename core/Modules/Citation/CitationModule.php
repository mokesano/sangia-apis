<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\Citation;

use Sangia\Core\Shared\ApiClients\CrossrefClient;
use Sangia\Core\Shared\ApiClients\OpenCitationsClient;
use Sangia\Core\Shared\ApiClients\SemanticScholarClient;
use Sangia\Core\Shared\ApiClients\OpenAlexClient;
use Sangia\Core\Shared\ApiClients\PubMedClient;

/**
 * Citation Module — multi-source citation retrieval with deduplication.
 *
 * Sources (incoming citations — papers that cite the given DOI):
 *   1. OpenCitations (COCI)   — open, DOI-based
 *   2. Semantic Scholar       — comprehensive, needs API key for full rate limits
 *   3. OpenAlex               — 250M+ works, year-by-year breakdown
 *   4. PubMed                 — biomedical focus, PMC cited-by
 *   5. Crossref               — citation count only (no citing-paper list)
 *
 * No result caching here. Wizdam Sikola owns all persistence:
 *   response includes 'raw_data' so Wizdam Sikola can cache results in its DB.
 */
class CitationModule
{
    private const LIMIT_DEFAULT = 100;

    private CrossrefClient        $crossref;
    private OpenCitationsClient   $openCitations;
    private SemanticScholarClient $semanticScholar;
    private OpenAlexClient        $openAlex;
    private PubMedClient          $pubmed;

    public function __construct()
    {
        $this->crossref        = new CrossrefClient();
        $this->openCitations   = new OpenCitationsClient();
        $this->semanticScholar = new SemanticScholarClient();
        $this->openAlex        = new OpenAlexClient();
        $this->pubmed          = new PubMedClient();
    }

    public function getCitations(string $doi, int $limit = self::LIMIT_DEFAULT): array
    {
        $doi = strtolower(trim($doi));
        if (empty($doi)) {
            http_response_code(400);
            return ['status' => 'error', 'code' => 400, 'message' => 'doi is required'];
        }

        $meta = $this->fetchMetadata($doi);

        // Fetch from all sources in parallel (sequential here — PHP limitation)
        $raw = [
            'opencitations'   => $this->openCitations->getCitations($doi, $limit),
            'semantic_scholar' => $this->semanticScholar->getCitations($doi, $limit),
            'openalex'        => $this->openAlex->getCitations($doi, $limit),
            'pubmed'          => $this->pubmed->getCitationsByDoi($doi, $limit),
        ];

        // Crossref provides count only (no citing-paper list)
        $crossrefCount = (int) ($meta['is_referenced_by'] ?? 0);

        $counts = [
            'opencitations'   => count($raw['opencitations']),
            'semantic_scholar' => count($raw['semantic_scholar']),
            'openalex'        => count($raw['openalex']),
            'pubmed'          => count($raw['pubmed']),
            'crossref'        => $crossrefCount,
        ];
        $counts['best'] = max(array_values($counts));

        $consolidated = $this->consolidate($raw);

        return [
            'status'            => 'success',
            'doi'               => $doi,
            'article_metadata'  => $meta,
            'citation_counts'   => $counts,
            'consolidated'      => $consolidated,
            'total_unique'      => count($consolidated),
            'per_source'        => $raw,
            'api_version'       => 'v2.0-multisource',
            'data_source'       => 'external_apis',
            'raw_data'          => [
                'doi'        => $doi,
                'metadata'   => $meta,
                'counts'     => $counts,
                'fetched_at' => date('c'),
            ],
        ];
    }

    // ── Metadata ──────────────────────────────────────────────────────────────

    private function fetchMetadata(string $doi): array
    {
        try {
            $data = $this->crossref->getWorkData($doi);
            $msg  = $data['message'] ?? [];
            $issn = $msg['ISSN'][0] ?? $msg['issn-type'][0]['value'] ?? null;
            return [
                'title'            => $msg['title'][0] ?? '',
                'authors'          => array_map(
                    fn($a) => trim(($a['given'] ?? '') . ' ' . ($a['family'] ?? '')),
                    $msg['author'] ?? []
                ),
                'publication_year' => (int) ($msg['published']['date-parts'][0][0] ?? 0) ?: null,
                'journal'          => $msg['container-title'][0] ?? null,
                'issn'             => $issn,
                'volume'           => $msg['volume'] ?? null,
                'issue'            => $msg['issue'] ?? null,
                'pages'            => $msg['page'] ?? null,
                'abstract'         => strip_tags($msg['abstract'] ?? ''),
                'publisher'        => $msg['publisher'] ?? null,
                'type'             => $msg['type'] ?? null,
                'is_referenced_by' => (int) ($msg['is-referenced-by-count'] ?? 0),
            ];
        } catch (\Throwable) {
            return ['doi' => $doi];
        }
    }

    // ── Consolidation ─────────────────────────────────────────────────────────

    /**
     * Merges all source lists into a deduplicated array keyed by DOI.
     * Items without a DOI are included per source but not deduplicated.
     * Each entry carries a 'sources' array listing every source that found it.
     */
    private function consolidate(array $raw): array
    {
        $byDoi   = [];  // doi → merged entry
        $noDoi   = [];  // entries without a DOI

        foreach ($raw as $source => $items) {
            foreach ($items as $item) {
                $doi = isset($item['citing_doi']) ? strtolower(trim($item['citing_doi'] ?? '')) : '';

                if ($doi === '' || $doi === null) {
                    $noDoi[] = array_merge($item, ['sources' => [$source]]);
                    continue;
                }

                if (isset($byDoi[$doi])) {
                    // Merge: add source, fill missing fields from this source
                    $byDoi[$doi]['sources'][] = $source;
                    foreach (['title', 'year', 'authors', 'citation_count'] as $f) {
                        if (empty($byDoi[$doi][$f]) && !empty($item[$f])) {
                            $byDoi[$doi][$f] = $item[$f];
                        }
                    }
                } else {
                    $byDoi[$doi] = [
                        'doi'            => $doi,
                        'title'          => $item['title'] ?? null,
                        'year'           => $item['year'] ?? null,
                        'authors'        => $item['authors'] ?? [],
                        'citation_count' => $item['citation_count'] ?? null,
                        'sources'        => [$source],
                    ];
                }
            }
        }

        // Sort by year desc, then by number of confirming sources desc
        $merged = array_values($byDoi);
        usort($merged, function ($a, $b) {
            $sc = count($b['sources']) - count($a['sources']);
            if ($sc !== 0) return $sc;
            return (int) ($b['year'] ?? 0) - (int) ($a['year'] ?? 0);
        });

        return array_merge($merged, $noDoi);
    }
}
