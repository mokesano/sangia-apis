<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\Citation;

use Sangia\Core\Shared\ApiClients\CrossrefClient;
use Sangia\Core\Shared\Services\CacheService;

/**
 * Citation Module — multi-source citation retrieval for a DOI.
 * Sources: OpenCitations → Crossref → OpenAlex → Semantic Scholar → Dimensions
 */
class CitationModule
{
    private const TIMEOUT    = 15;
    private const CACHE_TTL  = 604800; // 7 days
    private const SOURCES    = ['opencitations', 'crossref', 'openalex', 'semantic_scholar', 'dimensions'];

    private CrossrefClient $crossref;
    private CacheService   $cache;

    public function __construct()
    {
        $this->crossref = new CrossrefClient();
        $this->cache    = new CacheService('Citation');
    }

    public function getCitations(string $doi, int $limit = 15, bool $refresh = false): array
    {
        $doi = strtolower(trim($doi));
        if (empty($doi)) return $this->error(400, 'doi is required');

        if (!$refresh) {
            $cached = $this->cache->get('doi', $doi);
            if ($cached !== false) {
                $cached['cache_info'] = ['from_cache' => true];
                return $cached;
            }
        }

        // Metadata first
        $meta = $this->fetchMetadata($doi);

        // Citations from all sources
        $citations = [];
        $counts    = [];

        $oc = $this->fetchOpenCitations($doi, $limit);
        $citations['opencitations'] = $oc;
        $counts['opencitations']    = count($oc);

        $cr = $this->fetchCrossrefCitedBy($doi, $limit);
        $citations['crossref'] = $cr;
        $counts['crossref']    = count($cr);

        $oa = $this->fetchOpenAlex($doi, $limit);
        $citations['openalex'] = $oa;
        $counts['openalex']    = count($oa);

        $ss = $this->fetchSemanticScholar($doi, $limit);
        $citations['semantic_scholar'] = $ss;
        $counts['semantic_scholar']    = count($ss);

        $result = [
            'status'           => 'success',
            'doi'              => $doi,
            'article_metadata' => $meta,
            'citations'        => $citations,
            'citation_count'   => $counts,
            'total_unique'     => $this->countUnique($citations),
            'api_version'      => 'v1.2-modular',
            'cache_info'       => ['from_cache' => false],
        ];

        $this->cache->set('doi', $doi, $result);
        return $result;
    }

    // ── Metadata ─────────────────────────────────────────────────────────────

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
        $url  = "https://api.crossref.org/works/$doi/references?rows=$limit&mailto=info@sangia.org";
        $data = $this->httpGet($url);
        $items = $data['message']['items'] ?? [];

        return array_map(fn($item) => [
            'citing_doi'  => $item['DOI'] ?? null,
            'title'       => $item['article-title'] ?? $item['unstructured'] ?? null,
            'year'        => (int) ($item['year'] ?? 0) ?: null,
            'source'      => 'crossref',
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
        $url  = "https://api.semanticscholar.org/graph/v1/paper/DOI:$doi/citations?limit=$limit&fields=title,year,externalIds";
        $data = $this->httpGet($url);
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
