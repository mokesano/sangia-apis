<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\Journal;

/**
 * Journal Module — fetches journal metrics from Scopus.
 *
 * No result caching here. Sangia Scieco owns all persistence:
 *   response includes 'raw_data' so Sangia Scieco can save results to its DB.
 */
class JournalModule
{
    private const API_BASE = 'https://api.elsevier.com/content/serial/title';
    private const TIMEOUT  = 15;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = $_ENV['SCOPUS_API_KEY'] ?? getenv('SCOPUS_API_KEY') ?: '';
    }

    public function getMetrics(string $issn, bool $refresh = false): array
    {
        $issn = preg_replace('/[^0-9X]/', '', strtoupper($issn));
        if (strlen($issn) !== 8) {
            return $this->error(400, 'Invalid ISSN format. Expected 8 digits (e.g. 1234-5678).');
        }

        $issnFormatted = substr($issn, 0, 4) . '-' . substr($issn, 4);

        if (empty($this->apiKey)) {
            return $this->error(503, 'Scopus API key not configured');
        }

        $data = $this->fetchFromScopus($issnFormatted);
        if (empty($data)) {
            return $this->error(404, "Journal with ISSN $issnFormatted not found in Scopus");
        }

        return array_merge(
            ['status' => 'success', 'api_version' => 'v2.1-modular'],
            $data,
            [
                'cache_info' => ['from_cache' => false],
                // Sangia Scieco should save this to its journal_profiles_cache table
                'raw_data'   => [
                    'issn'       => $issnFormatted,
                    'metrics'    => $data,
                    'fetched_at' => date('c'),
                ],
            ]
        );
    }

    private function fetchFromScopus(string $issn): array
    {
        $views = ['CITESCORE', 'STANDARD'];

        foreach ($views as $view) {
            $url  = self::API_BASE . "/issn/$issn?view=$view&httpAccept=application%2Fjson";
            $data = $this->httpGet($url, ['X-ELS-APIKey: ' . $this->apiKey]);

            $entry = $data['serial-metadata-response']['entry'][0] ?? [];
            if (empty($entry) || isset($entry['@_fa'])) continue;

            return $this->parseEntry($entry, $issn);
        }

        return [];
    }

    private function parseEntry(array $entry, string $issn): array
    {
        $citeScore  = $entry['citeScoreYearInfoList']['citeScoreCurrentMetric'] ?? null;
        $sjrList    = $entry['SJRList']['SJR'] ?? [];
        $snipList   = $entry['SNIPList']['SNIP'] ?? [];
        $subAreas   = $entry['subject-area'] ?? [];

        $latestSjr  = is_array($sjrList)  ? ($sjrList[0]  ?? []) : $sjrList;
        $latestSnip = is_array($snipList) ? ($snipList[0] ?? []) : $snipList;

        $quartile = null;
        $subjects  = [];
        foreach ((array) $subAreas as $area) {
            $q = $area['@abbrevName'] ?? null;
            if ($q && !$quartile) $quartile = $q;
            $subjects[] = [
                'name'     => $area['$'] ?? '',
                'code'     => $area['@code'] ?? '',
                'quartile' => $area['@abbrevName'] ?? null,
            ];
        }

        return [
            'journal' => [
                'title'            => $entry['dc:title'] ?? '',
                'issn_print'       => $entry['prism:issn'] ?? $issn,
                'issn_electronic'  => $entry['prism:eIssn'] ?? null,
                'publisher'        => $entry['dc:publisher'] ?? null,
                'country'          => $entry['prism:aggregationType'] ?? null,
                'open_access'      => $entry['openaccess'] ?? null,
                'publication_type' => $entry['prism:aggregationType'] ?? 'Journal',
                'active'           => ($entry['active-or-inactiveDtxt'] ?? 'Active') === 'Active',
            ],
            'metrics' => [
                'citescore'     => is_numeric($citeScore) ? (float) $citeScore : null,
                'sjr'           => is_numeric($latestSjr['$'] ?? null) ? (float) $latestSjr['$'] : null,
                'sjr_year'      => $latestSjr['@year'] ?? null,
                'snip'          => is_numeric($latestSnip['$'] ?? null) ? (float) $latestSnip['$'] : null,
                'snip_year'     => $latestSnip['@year'] ?? null,
                'quartile'      => $quartile,
                'subject_areas' => $subjects,
            ],
        ];
    }

    private function httpGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'Sangia-API-Engine/1.0',
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
