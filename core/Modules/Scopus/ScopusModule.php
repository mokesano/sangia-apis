<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\Scopus;

/**
 * Scopus Module — fetches author profile and publications.
 *
 * No result caching here. Sangia Sikola owns all persistence:
 *   - pass $suppliedData to skip the Scopus cURL call entirely
 *   - response always includes 'raw_data' so Sangia Sikola can save it to DB
 */
class ScopusModule
{
    private const API_BASE  = 'https://api.elsevier.com/content';
    private const OPENALEX  = 'https://api.openalex.org';
    private const MAX_COUNT = 25;
    private const TIMEOUT   = 15;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = $_ENV['SCOPUS_API_KEY'] ?? getenv('SCOPUS_API_KEY') ?: '';
    }

    /**
     * @param string     $authorId      Scopus Author ID
     * @param int        $count         Max publications to return
     * @param bool       $refresh       Force re-fetch even if Sangia Sikola supplied data
     * @param array|null $suppliedData  Author data already in Sangia Sikola DB — skips cURL
     */
    public function getAuthor(
        string $authorId,
        int    $count        = 10,
        bool   $refresh      = false,
        ?array $suppliedData = null
    ): array {
        $authorId = trim($authorId);
        $count    = min(self::MAX_COUNT, max(1, $count));

        if (empty($authorId)) {
            return $this->error(400, 'authorid is required');
        }

        // Use supplied data from Sangia Sikola DB (no external call needed)
        if (!$refresh && $suppliedData !== null) {
            return array_merge($suppliedData, [
                'data_source' => 'sangia_sikola_db',
                'cache_info'  => ['from_cache' => true],
            ]);
        }

        // Fetch fresh from Scopus / OpenAlex
        $author = $this->fetchAuthorProfile($authorId);
        if (empty($author)) {
            $author = $this->fetchAuthorFromOpenAlex($authorId);
            if (empty($author)) {
                return $this->error(404, "Author '$authorId' not found in Scopus or OpenAlex");
            }
        }

        $publications = $this->fetchAuthorPublications($authorId, $count);

        $result = [
            'status'       => 'success',
            'author_id'    => $authorId,
            'author'       => $author,
            'publications' => $publications,
            'api_version'  => 'v6.0-modular',
            'data_source'  => $author['data_source'] ?? 'scopus',
            'cache_info'   => ['from_cache' => false],
            // Sangia Sikola should save these fields to its DB
            'raw_data'     => [
                'author'       => $author,
                'publications' => $publications,
                'fetched_at'   => date('c'),
            ],
        ];

        return $result;
    }

    // ── Private fetchers ──────────────────────────────────────────────────────

    private function fetchAuthorProfile(string $authorId): array
    {
        if (empty($this->apiKey)) return [];

        $url  = self::API_BASE . "/author/author_id/$authorId?httpAccept=application%2Fjson&view=ENHANCED";
        $data = $this->httpGet($url, ['X-ELS-APIKey: ' . $this->apiKey]);

        if (empty($data)) return [];

        $profile  = $data['author-retrieval-response'][0] ?? [];
        $coredata = $profile['coredata'] ?? [];
        $preferred = $profile['author-profile']['preferred-name'] ?? [];
        $affil    = $profile['author-profile']['affiliation-current']['affiliation'] ?? [];

        return [
            'author_id'      => $authorId,
            'first_name'     => $preferred['given-name'] ?? '',
            'last_name'      => $preferred['surname'] ?? '',
            'full_name'      => trim(($preferred['given-name'] ?? '') . ' ' . ($preferred['surname'] ?? '')),
            'display_name'   => $preferred['indexed-name'] ?? '',
            'affiliation'    => is_array($affil) ? ($affil['preferred-name']['$'] ?? '') : '',
            'h_index'        => (int) ($profile['h-index'] ?? 0),
            'document_count' => (int) ($coredata['document-count'] ?? 0),
            'citation_count' => (int) ($coredata['citation-count'] ?? 0),
            'cited_by_count' => (int) ($coredata['cited-by-count'] ?? 0),
            'orcid'          => $coredata['orcid'] ?? null,
            'scopus_id'      => $authorId,
            'data_source'    => 'scopus',
        ];
    }

    private function fetchAuthorFromOpenAlex(string $authorId): array
    {
        $url  = self::OPENALEX . "/authors/a$authorId?select=id,display_name,last_known_institution,works_count,cited_by_count,summary_stats";
        $data = $this->httpGet($url, ['Accept: application/json']);

        if (empty($data) || isset($data['error'])) return [];

        $institution = $data['last_known_institution']['display_name'] ?? '';
        $stats       = $data['summary_stats'] ?? [];

        return [
            'author_id'      => $authorId,
            'full_name'      => $data['display_name'] ?? '',
            'affiliation'    => $institution,
            'h_index'        => (int) ($stats['h_index'] ?? 0),
            'document_count' => (int) ($data['works_count'] ?? 0),
            'citation_count' => (int) ($data['cited_by_count'] ?? 0),
            'data_source'    => 'openalex_fallback',
        ];
    }

    private function fetchAuthorPublications(string $authorId, int $count): array
    {
        if (empty($this->apiKey)) return [];

        $url  = self::API_BASE . "/search/scopus?query=AU-ID($authorId)&count=$count&sort=coverDate&httpAccept=application%2Fjson&field=eid,doi,dc:title,prism:publicationName,prism:volume,prism:issueIdentifier,prism:pageRange,prism:coverDate,citedby-count,dc:creator,prism:doi,subtypeDescription,openaccess";
        $data = $this->httpGet($url, ['X-ELS-APIKey: ' . $this->apiKey]);

        $entries = $data['search-results']['entry'] ?? [];
        if (empty($entries)) return [];

        return array_map(function ($entry) {
            return [
                'eid'            => $entry['eid'] ?? '',
                'doi'            => $entry['prism:doi'] ?? $entry['doi'] ?? null,
                'title'          => $entry['dc:title'] ?? '',
                'journal'        => $entry['prism:publicationName'] ?? '',
                'volume'         => $entry['prism:volume'] ?? null,
                'issue'          => $entry['prism:issueIdentifier'] ?? null,
                'pages'          => $entry['prism:pageRange'] ?? null,
                'year'           => (int) substr($entry['prism:coverDate'] ?? '0', 0, 4),
                'cover_date'     => $entry['prism:coverDate'] ?? null,
                'cited_by_count' => (int) ($entry['citedby-count'] ?? 0),
                'authors_string' => $entry['dc:creator'] ?? '',
                'document_type'  => $entry['subtypeDescription'] ?? 'Article',
                'open_access'    => (bool) ($entry['openaccess'] ?? false),
            ];
        }, $entries);
    }

    private function httpGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'Sangia-API-Engine/6.0',
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
