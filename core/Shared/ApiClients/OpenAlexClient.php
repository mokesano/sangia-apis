<?php
declare(strict_types=1);

namespace Sangia\Core\Shared\ApiClients;

/**
 * OpenAlex API client — open scholarly graph covering 250M+ works.
 * No API key required. Set OPENALEX_MAILTO in .env for polite pool.
 * Docs: https://docs.openalex.org
 */
class OpenAlexClient extends HttpClient
{
    private const BASE = 'https://api.openalex.org';
    private string $mailtoParam;

    public function __construct()
    {
        $mailto = $this->cfg('OPENALEX_MAILTO', 'api@sangia.org');
        $this->mailtoParam = 'mailto=' . urlencode($mailto);
    }

    /**
     * Returns full work metadata for a DOI including citation count and referenced works.
     */
    public function getWork(string $doi): array
    {
        $url = self::BASE . '/works/' . urlencode("https://doi.org/$doi")
             . '?' . $this->mailtoParam;
        return $this->httpGet($url);
    }

    /**
     * Returns papers that cite the given DOI (incoming citations).
     * Uses OpenAlex's cited_by_api_url for accurate results.
     */
    public function getCitations(string $doi, int $limit = 100): array
    {
        $work = $this->getWork($doi);
        $citedByUrl = $work['cited_by_api_url'] ?? null;

        if (!$citedByUrl) return [];

        $url   = $citedByUrl . "&per-page=$limit&select=doi,title,publication_year,authorships,cited_by_count&"
               . $this->mailtoParam;
        $data  = $this->httpGet($url);
        $works = $data['results'] ?? [];

        return array_map(fn($w) => [
            'citing_doi'     => $this->cleanDoi($w['doi'] ?? ''),
            'title'          => $w['title'] ?? null,
            'year'           => (int) ($w['publication_year'] ?? 0) ?: null,
            'citation_count' => (int) ($w['cited_by_count'] ?? 0),
            'authors'        => array_column(
                array_column($w['authorships'] ?? [], 'author'),
                'display_name'
            ),
            'source' => 'openalex',
        ], $works);
    }

    /**
     * Returns works by ORCID iD (researcher's publication list).
     */
    public function getWorksByOrcid(string $orcid, int $limit = 100, int $page = 1): array
    {
        $url  = self::BASE . '/works?filter=author.orcid:' . urlencode($orcid)
              . "&per-page=$limit&page=$page&" . $this->mailtoParam;
        $data = $this->httpGet($url);
        return [
            'results' => $data['results'] ?? [],
            'total'   => $data['meta']['count'] ?? 0,
        ];
    }

    /**
     * Returns citation counts per year for a work (cited_by_year breakdown).
     */
    public function getCitationsByYear(string $doi): array
    {
        $work = $this->getWork($doi);
        return $work['counts_by_year'] ?? [];
    }

    /**
     * Returns author metadata from OpenAlex by ORCID.
     */
    public function getAuthorByOrcid(string $orcid): array
    {
        $url = self::BASE . '/authors/' . urlencode("https://orcid.org/$orcid")
             . '?' . $this->mailtoParam;
        return $this->httpGet($url);
    }

    /**
     * Returns journal/source metadata by ISSN.
     */
    public function getSourceByIssn(string $issn): array
    {
        $url = self::BASE . '/sources?filter=issn:' . urlencode($issn)
             . '&' . $this->mailtoParam;
        $data = $this->httpGet($url);
        return $data['results'][0] ?? [];
    }

    private function cleanDoi(string $raw): string
    {
        // OpenAlex returns full URL: https://doi.org/10.xxx/yyy
        return preg_replace('#^https?://doi\.org/#', '', strtolower($raw));
    }
}
