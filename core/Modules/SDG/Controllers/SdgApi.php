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
 * ORCID requests use the same anti-timeout batch pattern as WizdamScoreEngine:
 *   offset=0  → process first BATCH_SIZE works → {status:"processing", next_offset:N}
 *   offset=N  → continue until {status:"success", data:{...}}
 */
class SdgApi
{
    private const BATCH_SIZE = 20;
    private const MAX_WORKS  = 50;

    private OrcidClient    $orcidClient;
    private CrossrefClient $crossrefClient;
    private CacheService   $cache;
    private SdgAnalyzer    $analyzer;

    public function __construct(SdgAnalyzer $analyzer)
    {
        $this->orcidClient    = new OrcidClient();
        $this->crossrefClient = new CrossrefClient();
        $this->cache          = new CacheService('sdg');
        $this->analyzer       = $analyzer;
    }

    // ── ORCID — batched, anti-timeout ────────────────────────────────────────

    /**
     * @param string $orcid         ORCID iD
     * @param bool   $forceRefresh  Clear cache and start fresh
     * @param int    $batchSize     Works processed per call
     * @param int    $offset        Starting index (0 on first call)
     */
    public function handleOrcidRequest(
        string $orcid,
        bool   $forceRefresh = false,
        int    $batchSize    = self::BATCH_SIZE,
        int    $offset       = 0
    ): array {
        $orcid = trim($orcid);

        // Return completed cached result on first call (no refresh)
        if ($offset === 0 && !$forceRefresh) {
            $cached = $this->cache->get('orcid', $orcid);
            if ($cached !== false) {
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        // Fetch ORCID works (OrcidClient caches this)
        try {
            $worksRaw = $this->orcidClient->getWorksData($orcid, self::MAX_WORKS);
        } catch (\Throwable $e) {
            return $this->error(502, 'ORCID API error: ' . $e->getMessage());
        }

        $groups = $worksRaw['group'] ?? [];
        $works  = [];
        foreach ($groups as $group) {
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
            $works[] = ['title' => $title, 'doi' => $doi];
        }

        $works      = array_slice($works, 0, self::MAX_WORKS);
        $totalWorks = count($works);
        $accumKey   = 'orcid_accum_' . md5($orcid);

        // Load or initialise accumulator
        if ($offset === 0) {
            $accum = [];
        } else {
            $stored = $this->cache->get('partial', $accumKey);
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
        $batch = array_slice($works, $offset, $batchSize);

        foreach ($batch as $work) {
            $abstract = '';

            // Try to enrich with abstract from Crossref if DOI is available
            if (!empty($work['doi'])) {
                try {
                    $crData   = $this->crossrefClient->getWorkData($work['doi']);
                    $abstract = strip_tags($crData['message']['abstract'] ?? '');
                } catch (\Throwable) {
                    // abstract stays empty — title-only analysis
                }
            }

            try {
                $analysis = $this->analyzer->analyzeWork($work['title'], $abstract);
            } catch (\Throwable) {
                continue;
            }

            $accum[] = [
                'title'      => substr($work['title'], 0, 100),
                'doi'        => $work['doi'],
                'sdgs'       => array_keys($analysis['sdg_confidence'] ?? []),
                'confidence' => $analysis['sdg_confidence'] ?? [],
                'analysis'   => $analysis,
            ];
        }

        $this->cache->set('partial', $accumKey, $accum);

        $processed = min($offset + $batchSize, $totalWorks);
        $isDone    = ($processed >= $totalWorks) || empty($batch);

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

        // ── All works done — aggregate and return ─────────────────────────────
        $allSdgs    = [];
        $worksSummary = [];

        foreach ($accum as $item) {
            foreach ($item['confidence'] ?? [] as $sdgCode => $score) {
                $allSdgs[$sdgCode][] = (float) $score;
            }
            if (!empty($item['sdgs'])) {
                $worksSummary[] = [
                    'title' => $item['title'],
                    'doi'   => $item['doi'],
                    'sdgs'  => $item['sdgs'],
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

        $result = [
            'status'       => 'success',
            'orcid'        => $orcid,
            'total_works'  => $totalWorks,
            'data'         => [
                'sdg_summary' => $sdgSummary,
                'works'       => $worksSummary,
            ],
            'from_cache'   => false,
            'api_version'  => 'v5.1.8-batch',
        ];

        $this->cache->set('orcid', $orcid, $result);
        return $result;
    }

    // ── DOI — single work, synchronous ───────────────────────────────────────

    public function handleDoiRequest(string $doi, bool $forceRefresh = false): array
    {
        $doi = trim($doi);
        if (empty($doi)) {
            return $this->error(400, 'DOI tidak boleh kosong');
        }

        if (!$forceRefresh) {
            $cached = $this->cache->get('article', $doi);
            if ($cached !== false) {
                $cached['cache_info'] = ['from_cache' => true];
                return $cached;
            }
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

            $result = [
                'status'      => 'success',
                'doi'         => $doi,
                'title'       => $title,
                'abstract'    => $abstract,
                'sdg_analysis'=> $analysis,
                'api_version' => 'v5.1.8-batch',
                'cache_info'  => ['from_cache' => false],
            ];

            $this->cache->set('article', $doi, $result);
            return $result;

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
