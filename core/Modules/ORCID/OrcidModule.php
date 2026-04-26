<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\ORCID;

use Sangia\Core\Shared\ApiClients\OrcidClient;
use Sangia\Core\Shared\ApiClients\CrossrefClient;

/**
 * ORCID Module — fetches researcher profile and works.
 *
 * No result caching here. Wizdam Sikola owns all persistence:
 *   - pass $suppliedWorks to skip the ORCID cURL call entirely
 *   - response always includes 'raw_data' so Wizdam Sikola can save it to DB
 */
class OrcidModule
{
    private OrcidClient    $orcidClient;
    private CrossrefClient $crossrefClient;

    public function __construct()
    {
        $this->orcidClient    = new OrcidClient();
        $this->crossrefClient = new CrossrefClient();
    }

    /**
     * @param string     $orcid          ORCID iD
     * @param bool       $refresh        Force re-fetch even if Wizdam Sikola supplied data
     * @param int        $limit          Max works to fetch from ORCID API
     * @param array|null $suppliedWorks  Works already stored in Wizdam Sikola DB — skips cURL
     * @param array|null $suppliedPerson Person summary already stored in Wizdam Sikola DB
     */
    public function getProfile(
        string  $orcid,
        bool    $refresh        = false,
        int     $limit          = 50,
        ?array  $suppliedWorks  = null,
        ?array  $suppliedPerson = null
    ): array {
        $orcid = trim($orcid);
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
            return $this->error(400, "Invalid ORCID format: $orcid");
        }

        // Use supplied data from Wizdam Sikola DB (no external call needed)
        if (!$refresh && $suppliedWorks !== null) {
            return [
                'status'         => 'success',
                'orcid'          => $orcid,
                'person_summary' => $suppliedPerson ?? [],
                'works'          => $suppliedWorks,
                'works_count'    => count($suppliedWorks),
                'api_version'    => 'v2.2-modular',
                'data_source'    => 'wizdam_sikola_db',
                'cache_info'     => ['from_cache' => true],
            ];
        }

        // Fetch fresh from ORCID API
        try {
            $personRaw = $this->orcidClient->getPersonData($orcid);
            $worksRaw  = $this->orcidClient->getWorksData($orcid, $limit);
        } catch (\Throwable $e) {
            return $this->error(502, 'ORCID API error: ' . $e->getMessage());
        }

        $person = $this->parsePerson($personRaw);
        $works  = $this->parseWorks($worksRaw);

        return [
            'status'         => 'success',
            'orcid'          => $orcid,
            'person_summary' => $person,
            'works'          => $works,
            'works_count'    => count($works),
            'api_version'    => 'v2.2-modular',
            'data_source'    => 'orcid_api',
            'cache_info'     => ['from_cache' => false],
            // Wizdam Sikola should save these fields to its DB
            'raw_data'       => [
                'person'     => $person,
                'works'      => $works,
                'fetched_at' => date('c'),
            ],
        ];
    }

    // ── Parsers ───────────────────────────────────────────────────────────────

    private function parsePerson(array $raw): array
    {
        $name   = $raw['name'] ?? [];
        $bio    = $raw['biography'] ?? [];
        $emails = $raw['emails']['email'] ?? [];
        $extIds = $raw['external-identifiers']['external-identifier'] ?? [];
        $urls   = $raw['researcher-urls']['researcher-url'] ?? [];
        $kws    = $raw['keywords']['keyword'] ?? [];
        $addrs  = $raw['addresses']['address'] ?? [];

        return [
            'name'         => trim(($name['given-names']['value'] ?? '') . ' ' . ($name['family-name']['value'] ?? '')),
            'given_names'  => $name['given-names']['value'] ?? '',
            'family_name'  => $name['family-name']['value'] ?? '',
            'credit_name'  => $name['credit-name']['value'] ?? null,
            'bio'          => $bio['content'] ?? null,
            'emails'       => array_map(fn($e) => $e['email'] ?? '', $emails),
            'keywords'     => array_map(fn($k) => $k['content'] ?? '', $kws),
            'external_ids' => array_map(fn($id) => [
                'type'  => $id['external-id-type'] ?? '',
                'value' => $id['external-id-value'] ?? '',
            ], is_array($extIds) ? $extIds : [$extIds]),
            'urls'         => array_map(fn($u) => [
                'name' => $u['url-name'] ?? '',
                'url'  => $u['url']['value'] ?? '',
            ], is_array($urls) ? $urls : []),
            'country'      => $addrs[0]['country']['value'] ?? null,
        ];
    }

    private function parseWorks(array $raw): array
    {
        $groups = $raw['group'] ?? [];
        $works  = [];

        foreach ($groups as $group) {
            $summaries = $group['work-summary'] ?? [];
            $summary   = $summaries[0] ?? [];

            $title    = $summary['title']['title']['value'] ?? '';
            $extIds   = $summary['external-ids']['external-id'] ?? [];
            $doi      = null;
            $scopusId = null;

            foreach ((array) $extIds as $ext) {
                if (($ext['external-id-type'] ?? '') === 'doi')            $doi      = $ext['external-id-value'] ?? null;
                if (($ext['external-id-type'] ?? '') === 'eid')            $scopusId = $ext['external-id-value'] ?? null;
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
