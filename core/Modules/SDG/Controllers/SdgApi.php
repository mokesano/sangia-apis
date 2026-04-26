<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\SDG\Controllers;

use Sangia\Core\Shared\ApiClients\OrcidClient;
use Sangia\Core\Shared\ApiClients\CrossrefClient;
use Sangia\Core\Shared\Services\CacheService;
use Sangia\Core\Modules\SDG\Services\SdgAnalyzer;

/**
 * SDG API Controller — handles DOI and ORCID classification requests.
 *
 * No result caching here. Wizdam Sikola owns all persistence.
 * CacheService is only used for short-lived batch session state (partial accumulator).
 *
 * ORCID requests use the anti-timeout batch pattern:
 *   offset=0  → process first BATCH_SIZE works → {status:"processing", next_offset:N}
 *   offset=N  → continue until {status:"success", data:{...}}
 */
class SdgApi
{
    private const BATCH_SIZE = 20;
    private const MAX_WORKS  = 50;

    private OrcidClient    $orcidClient;
    private CrossrefClient $crossrefClient;
    private CacheService   $batchState; // batch session state only — NOT result cache
    private SdgAnalyzer    $analyzer;

    public function __construct(SdgAnalyzer $analyzer)
    {
        $this->orcidClient    = new OrcidClient();
        $this->crossrefClient = new CrossrefClient();
        $this->batchState     = new CacheService('sdg');
        $this->analyzer       = $analyzer;
    }

    /**
     * @param string $orcid          ORCID iD
     * @param bool   $forceRefresh   Ignore supplied data and re-fetch from ORCID
     * @param int    $batchSize      Works processed per call
     * @param int    $offset         Starting index (0 on first call)
     * @param array  $suppliedWorks  Works from Wizdam Sikola DB — skips ORCID cURL
     */
    public function handleOrcidRequest(
        string $orcid,
        bool   $forceRefresh   = false,
        int    $batchSize      = self::BATCH_SIZE,
        int    $offset         = 0,
        array  $suppliedWorks  = []
    ): array {
        $orcid = trim($orcid);

        // Use supplied works from Wizdam Sikola DB if provided (no ORCID fetch)
        if (!$forceRefresh && !empty($suppliedWorks)) {
            $works      = array_slice($suppliedWorks, 0, self::MAX_WORKS);
            $dataSource = 'wizdam_sikola_db';
        } else {
            // Fetch from ORCID API
            try {
                $worksRaw = $this->orcidClient->getWorksData($orcid, self::MAX_WORKS);
            } catch (\Throwable $e) {
                return $this->error(502, 'ORCID API error: ' . $e->getMessage());
            }

            $works = [];
            foreach ($worksRaw['group'] ?? [] as $group) {
                $summary = $group['work-summary'][0] ?? [];
                $title   = $summary['title']['title']['value'] ?? '';
                if (empty($title)) continue;

                $extIds = $summary['external-ids']['external-id'] ?? [];
                $doi    = null;
                foreach ((array) $extIds as $ext) {
                    if (($ext['external-id-type'] ?? '') === 'doi') {
                        $doi = $ext['external-id-value'] ?? null;
                        break;
                    }
                }

                $pubDate = $summary['publication-date'] ?? [];
                $year    = (int) ($pubDate['year']['value'] ?? 0);

                $works[] = [
                    'title'            => $title,
                    'doi'              => $doi,
                    'publication_year' => $year ?: null,
                ];
            }

            $works      = array_slice($works, 0, self::MAX_WORKS);
            $dataSource = 'orcid_api';
        }

        $totalWorks = count($works);
        $accumKey   = 'orcid_accum_' . md5($orcid);

        // Load or initialise batch accumulator
        if ($offset === 0) {
            $accum = [];
        } else {
            $stored = $this->batchState->get('partial', $accumKey);
            if ($stored === false) {
                return [
                    'status'  => 'error',
                    'code'    => 410,
                    'message' => 'Batch session expired. Restart with offset=0.',
                ];
            }
            $accum = $stored;
        }

        // ── Process batch ─────────────────────────────────────────────────────
        foreach (array_slice($works, $offset, $batchSize) as $work) {
            $abstract = $work['abstract'] ?? '';

            if (empty($abstract) && !empty($work['doi'])) {
                try {
                    $crData   = $this->crossrefClient->getWorkData($work['doi']);
                    $abstract = strip_tags($crData['message']['abstract'] ?? '');
                } catch (\Throwable) {
                    // title-only analysis
                }
            }

            try {
                $analysis = $this->analyzer->analyzeWork($work['title'] ?? '', $abstract);
            } catch (\Throwable) {
                continue;
            }

            $accum[] = [
                'title'            => substr($work['title'] ?? '', 0, 100),
                'doi'              => $work['doi'] ?? null,
                'publication_year' => $work['publication_year'] ?? null,
                'sdgs'             => array_keys($analysis['sdg_confidence'] ?? []),
                'confidence'       => $analysis['sdg_confidence'] ?? [],
            ];
        }

        $this->batchState->set('partial', $accumKey, $accum);

        $processed = min($offset + $batchSize, $totalWorks);
        $isDone    = ($processed >= $totalWorks) || ($processed === $offset);

        // ── More batches remain ───────────────────────────────────────────────
        if (!$isDone) {
            return [
                'status'      => 'processing',
                'orcid'       => $orcid,
                'progress'    => [
                    'processed'   => $processed,
                    'total_works' => $totalWorks,
                    'percent'     => (int) round($processed / max(1, $totalWorks) * 100),
                ],
                'next_offset' => $processed,
            ];
        }

        // ── All works done — aggregate ────────────────────────────────────────
        $allSdgs      = [];
        $worksSummary = [];

        foreach ($accum as $item) {
            foreach ($item['confidence'] ?? [] as $sdgCode => $score) {
                $allSdgs[$sdgCode][] = (float) $score;
            }
            if (!empty($item['sdgs'])) {
                $worksSummary[] = [
                    'title'            => $item['title'],
                    'doi'              => $item['doi'],
                    'publication_year' => $item['publication_year'],
                    'sdgs'             => $item['sdgs'],
                ];
            }
        }

        $sdgSummary = [];
        foreach ($allSdgs as $sdgCode => $scores) {
            $sdgSummary[$sdgCode] = [
                'average_confidence' => round(array_sum($scores) / count($scores), 3),
                'work_count'         => count($scores),
            ];
        }
        arsort($sdgSummary);

        return [
            'status'      => 'success',
            'orcid'       => $orcid,
            'total_works' => $totalWorks,
            'data_source' => $dataSource,
            'data'        => [
                'sdg_summary' => $sdgSummary,
                'works'       => $worksSummary,
            ],
            // Wizdam Sikola should save works_fetched to its DB when data_source=orcid_api
            'raw_data'    => $dataSource === 'orcid_api' ? [
                'works'      => $works,
                'fetched_at' => date('c'),
            ] : null,
            'api_version' => 'v5.1.8-batch',
        ];
    }

    // ── DOI — single work, synchronous ───────────────────────────────────────

    public function handleDoiRequest(string $doi, bool $forceRefresh = false): array
    {
        $doi = trim($doi);
        if (empty($doi)) {
            return $this->error(400, 'DOI tidak boleh kosong');
        }

        try {
            $crossrefData = $this->crossrefClient->getWorkData($doi);
            if (empty($crossrefData)) {
                return $this->error(404, 'Data DOI tidak ditemukan di Crossref.');
            }

            $title    = $crossrefData['message']['title'][0] ?? '';
            $abstract = strip_tags($crossrefData['message']['abstract'] ?? '');

            if (empty($abstract)) {
                $abstract = $this->crossrefClient->getAlternativeAbstract($doi);
            }

            $analysis = $this->analyzer->analyzeWork($title, $abstract);

            return [
                'status'       => 'success',
                'doi'          => $doi,
                'title'        => $title,
                'abstract'     => $abstract,
                'sdg_analysis' => $analysis,
                'api_version'  => 'v5.1.8-batch',
                'cache_info'   => ['from_cache' => false],
            ];

        } catch (\Throwable $e) {
            return $this->error(500, 'Kesalahan internal: ' . $e->getMessage());
        }
    }

    private function error(int $code, string $message): array
    {
        http_response_code($code);
        return ['status' => 'error', 'code' => $code, 'message' => $message];
    }
}
