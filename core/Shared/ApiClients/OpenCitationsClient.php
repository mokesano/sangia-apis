<?php
declare(strict_types=1);

namespace Sangia\Core\Shared\ApiClients;

/**
 * OpenCitations COCI API — open citation data from DOI metadata.
 * No API key required. Coverage: Crossref-indexed journals.
 * Docs: https://opencitations.net/index/coci/api/v1
 */
class OpenCitationsClient extends HttpClient
{
    private const BASE = 'https://opencitations.net/index/coci/api/v1';

    /** Returns papers that cite the given DOI (incoming citations). */
    public function getCitations(string $doi, int $limit = 100): array
    {
        $url  = self::BASE . '/citations/' . urlencode($doi) . '?format=json';
        $data = $this->httpGet($url);
        if (!is_array($data)) return [];

        return array_slice(array_map(fn($c) => [
            'citing_doi' => $this->stripPrefix($c['citing'] ?? ''),
            'cited_doi'  => $this->stripPrefix($c['cited'] ?? ''),
            'creation'   => $c['creation'] ?? null,
            'timespan'   => $c['timespan'] ?? null,
            'source'     => 'opencitations',
        ], $data), 0, $limit);
    }

    /** Returns the raw citation count for a DOI (fast, single field). */
    public function getCitationCount(string $doi): int
    {
        $url  = self::BASE . '/citation-count/' . urlencode($doi);
        $data = $this->httpGet($url);
        return (int) ($data[0]['count'] ?? 0);
    }

    /** Returns papers referenced by the given DOI (outgoing references). */
    public function getReferences(string $doi, int $limit = 100): array
    {
        $url  = self::BASE . '/references/' . urlencode($doi) . '?format=json';
        $data = $this->httpGet($url);
        if (!is_array($data)) return [];

        return array_slice(array_map(fn($r) => [
            'cited_doi' => $this->stripPrefix($r['cited'] ?? ''),
            'source'    => 'opencitations',
        ], $data), 0, $limit);
    }

    private function stripPrefix(string $id): string
    {
        // COCI prefixes IDs with "coci>" — strip it
        return preg_replace('/^[a-z]+>/', '', $id);
    }
}
