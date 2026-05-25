<?php
declare(strict_types=1);

namespace Sangia\Core\Shared\ApiClients;

/**
 * Semantic Scholar API client.
 * Set SEMANTIC_SCHOLAR_API_KEY in .env for higher rate limits (no anonymous pool).
 * Docs: https://api.semanticscholar.org/api-docs/
 */
class SemanticScholarClient extends HttpClient
{
    private const BASE   = 'https://api.semanticscholar.org/graph/v1';
    private array $authHeader;

    public function __construct()
    {
        $key = $this->cfg('SEMANTIC_SCHOLAR_API_KEY');
        $this->authHeader = $key ? ["x-api-key: $key"] : [];
        // Semantic Scholar recommends a longer timeout for large result sets
        $this->timeout = 20;
    }

    /**
     * Returns papers that cite the given DOI.
     * Fields: title, year, externalIds, citationCount, authors
     */
    public function getCitations(string $doi, int $limit = 100): array
    {
        $fields = 'title,year,externalIds,citationCount,authors';
        $url    = self::BASE . '/paper/DOI:' . urlencode($doi)
                . "/citations?limit=$limit&fields=$fields";
        $data   = $this->httpGet($url, $this->authHeader);
        $items  = $data['data'] ?? [];

        return array_map(fn($item) => [
            'citing_doi'     => $item['citingPaper']['externalIds']['DOI'] ?? null,
            'title'          => $item['citingPaper']['title'] ?? null,
            'year'           => (int) ($item['citingPaper']['year'] ?? 0) ?: null,
            'citation_count' => (int) ($item['citingPaper']['citationCount'] ?? 0),
            'authors'        => array_column($item['citingPaper']['authors'] ?? [], 'name'),
            'source'         => 'semantic_scholar',
        ], $items);
    }

    /**
     * Returns papers referenced by the given DOI (outgoing).
     */
    public function getReferences(string $doi, int $limit = 100): array
    {
        $fields = 'title,year,externalIds';
        $url    = self::BASE . '/paper/DOI:' . urlencode($doi)
                . "/references?limit=$limit&fields=$fields";
        $data   = $this->httpGet($url, $this->authHeader);
        $items  = $data['data'] ?? [];

        return array_map(fn($item) => [
            'cited_doi' => $item['citedPaper']['externalIds']['DOI'] ?? null,
            'title'     => $item['citedPaper']['title'] ?? null,
            'year'      => (int) ($item['citedPaper']['year'] ?? 0) ?: null,
            'source'    => 'semantic_scholar',
        ], $items);
    }

    /**
     * Returns paper metadata including abstract, citation count, and fields of study.
     */
    public function getPaper(string $doi): array
    {
        $fields = 'title,abstract,year,citationCount,referenceCount,fieldsOfStudy,externalIds,authors';
        $url    = self::BASE . '/paper/DOI:' . urlencode($doi) . "?fields=$fields";
        return $this->httpGet($url, $this->authHeader);
    }

    /**
     * Returns author information by Semantic Scholar author ID.
     */
    public function getAuthor(string $authorId): array
    {
        $fields = 'name,affiliations,citationCount,hIndex,paperCount';
        $url    = self::BASE . "/author/$authorId?fields=$fields";
        return $this->httpGet($url, $this->authHeader);
    }
}
