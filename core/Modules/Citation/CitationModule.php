<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\Citation;

use Sangia\Core\Shared\ApiClients\CrossrefClient;

/**
 * Citation Module — multi-source citation retrieval for a DOI.
 * Sources: OpenCitations → Crossref → OpenAlex → Semantic Scholar
 *
 * No result caching here. Wizdam Sikola owns all persistence:
 *   response includes 'raw_data' so Wizdam Sikola can save results to its DB.
 */
class CitationModule
{
    private const TIMEOUT = 15;

    private CrossrefClient $crossref;

    public function __construct()
    {
        $this->crossref = new CrossrefClient();
    }

    public function getCitations(string $doi, int $limit = 15, bool $refresh = false): array
    {
        $doi = strtolower(trim($doi));
        if (empty($doi)) return $this->error(400, 'doi is required');

        $meta = $this->fetchMetadata($doi);

        $citations = [
            'opencitations'   => $this->fetchOpenCitations($doi, $limit),
            'crossref'        => $this->fetchCrossrefCitedBy($doi, $limit),
            'openalex'        => $this->fetchOpenAlex($doi, $limit),
            'semantic_scholar'=> $this->fetchSemanticScholar($doi, $limit),
        ];

        $counts = array_map('count', $citations);

        return [
            'status'           => 'success',
            'doi'              => $doi,
            'article_metadata' => $meta,
            'citations'        => $citations,
            'citation_count'   => $counts,
            'total_unique'     => $this->countUnique($citations),
            'api_version'      => 'v1.2-modular',
            'data_source'      => 'external_apis',
            'cache_info'       => ['from_cache' => false],
            // Wizdam Sikola should save this to its citations_cache table
            'raw_data'         => [
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
            return [
                'title'            => $msg['title'][0] ?? '',
                'authors'          => array_map(fn($a) => trim(($a['given'] ?? '') . ' ' . ($a['family'] ?? '')), $msg['author'] ?? []),
                'publication_year' => (int) ($msg['published']['date-parts'][0][0] ?? 0) ?: null,
                'journal'          => $msg['container-title'][0] ?? null,
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

    // ── Citation sources ──────────────────────────────────────────────────────

    private function fetchOpenCitations(string $doi, int $limit): array
    {
        $url  = "https://opencitations.net/index/api/v1/citations/$doi?format=json&limit=$limit";
        $data = $this->httpGet($url);
        if (!is_array($data)) return [];

        return array_map(fn($c) => [
            'citing_doi' => $c['citing'] ?? null,
            'title'      => null,
            'year'       => null,
            'source'     => 'opencitations',
        ], array_slice($data, 0, $limit));
    }

    private function fetchCrossrefCitedBy(string $doi, int $limit): array
    {
        $url   = "https://api.crossref.org/works/$doi/references?rows=$limit&mailto=info@sangia.org";
        $data  = $this->httpGet($url);
        $items = $data['message']['items'] ?? [];

        return array_map(fn($item) => [
            'citing_doi' => $item['DOI'] ?? null,
            'title'      => $item['article-title'] ?? $item['unstructured'] ?? null,
            'year'       => (int) ($item['year'] ?? 0) ?: null,
            'source'     => 'crossref',
        ], array_slice($items, 0, $limit));
    }

    private function fetchOpenAlex(string $doi, int $limit): array
    {
        $url  = "https://api.openalex.org/works/https://doi.org/$doi?select=cited_by_api_url,cited_by_count";
        $meta = $this->httpGet($url);
        if (empty($meta['cited_by_api_url'])) return [];

        $data  = $this->httpGet($meta['cited_by_api_url'] . "&per-page=$limit");
        $works = $data['results'] ?? [];

        return array_map(fn($w) => [
            'citing_doi' => $w['doi'] ?? null,
            'title'      => $w['title'] ?? null,
            'year'       => (int) ($w['publication_year'] ?? 0) ?: null,
            'source'     => 'openalex',
        ], array_slice($works, 0, $limit));
    }

    private function fetchSemanticScholar(string $doi, int $limit): array
    {
        $url   = "https://api.semanticscholar.org/graph/v1/paper/DOI:$doi/citations?limit=$limit&fields=title,year,externalIds";
        $data  = $this->httpGet($url);
        $items = $data['data'] ?? [];

        return array_map(fn($item) => [
            'citing_doi' => $item['citingPaper']['externalIds']['DOI'] ?? null,
            'title'      => $item['citingPaper']['title'] ?? null,
            'year'       => (int) ($item['citingPaper']['year'] ?? 0) ?: null,
            'source'     => 'semantic_scholar',
        ], array_slice($items, 0, $limit));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function countUnique(array $citations): int
    {
        $dois = [];
        foreach ($citations as $source) {
            foreach ($source as $c) {
                if (!empty($c['citing_doi'])) $dois[$c['citing_doi']] = true;
            }
        }
        return count($dois);
    }

    private function httpGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Sangia-API-Engine/1.0 (mailto:info@sangia.org)',
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$body) return [];
        return json_decode($body, true) ?? [];
    }

    private function error(int $code, string $message): array
    {
        http_response_code($code);
        return ['status' => 'error', 'code' => $code, 'message' => $message];
    }
}
