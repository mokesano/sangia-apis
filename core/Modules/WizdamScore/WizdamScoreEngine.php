<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\WizdamScore;

use Sangia\Core\Modules\ORCID\OrcidModule;
use Sangia\Core\Modules\Scopus\ScopusModule;
use Sangia\Core\Modules\SDG\Services\SdgAnalyzer;
use Sangia\Core\Modules\SDG\Services\SdgClassifier;
use Sangia\Core\Modules\SDG\Services\Evaluator\LevelV4Evaluator;
use Sangia\Core\Modules\SDG\Config\SdgDictionary;
use Sangia\Core\Shared\Services\CacheService;

/**
 * Wizdam Impact Score Engine.
 *
 * Formula: Composite = Academic×0.40 + Social×0.25 + Economic×0.20 + SDG×0.15
 *
 * Anti-timeout batch pattern (from KONSEP/KODE_ANTI-TIMEOUT):
 *   Each request processes BATCH_SIZE works and returns quickly.
 *   Client (Wizdam Sikola) loops until status === "success".
 *
 *   Call 1: offset=0  → {status:"processing", next_offset:20, progress:{...}}
 *   Call 2: offset=20 → {status:"processing", next_offset:40, progress:{...}}
 *   Call 3: offset=40 → {status:"success", composite:..., pillars:{...}}
 */
class WizdamScoreEngine
{
    private const WEIGHTS = [
        'academic' => 0.40,
        'social'   => 0.25,
        'economic' => 0.20,
        'sdg'      => 0.15,
    ];

    private const MAX_WORKS  = 50;
    private const BATCH_SIZE = 20; // ~4-6s per batch on average hardware

    private OrcidModule  $orcid;
    private ScopusModule $scopus;
    private SdgAnalyzer  $sdgAnalyzer;
    private CacheService $cache;

    public function __construct()
    {
        $this->orcid  = new OrcidModule();
        $this->scopus = new ScopusModule();
        $this->cache  = new CacheService('WizdamScore');

        $dictionary        = new SdgDictionary();
        $classifier        = new SdgClassifier($dictionary);
        $evaluator         = new LevelV4Evaluator();
        $this->sdgAnalyzer = new SdgAnalyzer($classifier, $evaluator, $dictionary);
    }

    /**
     * Calculate Wizdam Impact Score with batched SDG analysis.
     *
     * @param string      $orcid
     * @param string|null $scopusId  Optional Scopus author ID
     * @param array       $social    Social pillar data from Wizdam Sikola
     * @param array       $economic  Economic / practical pillar data from Wizdam Sikola
     * @param bool        $refresh   Force recalculation (clears cache)
     * @param int         $batchSize Works processed per HTTP request
     * @param int         $offset    Batch starting index (0 = first call)
     */
    public function calculate(
        string  $orcid,
        ?string $scopusId  = null,
        array   $social    = [],
        array   $economic  = [],
        bool    $refresh   = false,
        int     $batchSize = self::BATCH_SIZE,
        int     $offset    = 0
    ): array {
        $orcid = trim($orcid);
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
            return $this->error(400, "Invalid ORCID: $orcid");
        }

        $scoreKey = $orcid . ($scopusId ? "_$scopusId" : '');

        // Return completed cached result on the first call (no refresh)
        if ($offset === 0 && !$refresh) {
            $cached = $this->cache->get('score', $scoreKey);
            if ($cached !== false) {
                $cached['cache_info'] = ['from_cache' => true];
                return $cached;
            }
        }

        // Fetch ORCID profile — OrcidModule caches this after the first call
        $orcidData = $this->orcid->getProfile($orcid, $refresh && $offset === 0);
        if (($orcidData['status'] ?? '') === 'error') {
            return $this->error(502, $orcidData['message'] ?? 'ORCID fetch failed');
        }

        $works      = array_slice($orcidData['works'] ?? [], 0, self::MAX_WORKS);
        $totalWorks = count($works);
        $accumKey   = 'accum_' . md5($scoreKey);

        // Load or initialise the SDG accumulator for this batch session
        if ($offset === 0) {
            $accum = ['sdg' => [], 'by_work' => []];
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

        // ── Process this batch ────────────────────────────────────────────────
        $batch = array_slice($works, $offset, $batchSize);

        foreach ($batch as $work) {
            $title    = $work['title']    ?? '';
            $abstract = $work['abstract'] ?? '';
            if (empty($title)) continue;

            try {
                $analysis = $this->sdgAnalyzer->analyzeWork($title, $abstract);
            } catch (\Throwable) {
                continue;
            }

            $workSdgs = [];
            foreach ($analysis['sdg_confidence'] ?? [] as $sdgCode => $score) {
                $accum['sdg'][$sdgCode][] = (float) $score;
                $workSdgs[] = ['sdg' => $sdgCode, 'score' => round((float) $score, 3)];
            }
            if (!empty($workSdgs)) {
                $accum['by_work'][] = ['title' => substr($title, 0, 80), 'sdgs' => $workSdgs];
            }
        }

        // Persist accumulator so the next batch can continue from here
        $this->cache->set('partial', $accumKey, $accum);

        $processed = min($offset + $batchSize, $totalWorks);
        $isDone    = ($processed >= $totalWorks) || empty($batch);

        // ── More batches remain — instruct client to continue ─────────────────
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

        // ── Final batch — compute composite score ─────────────────────────────
        $scopusData = [];
        if ($scopusId) {
            $scopusData = $this->scopus->getAuthor($scopusId, 25, false);
        }

        $academicScore = $this->calculateAcademic($orcidData, $scopusData);
        [$sdgScore, $sdgTags, $sdgByWork] = $this->buildSdgScore($accum, $totalWorks);
        $socialScore   = $this->computeSocialScore($social);
        $economicScore = $this->computeEconomicScore($economic);

        $composite = round(
            ($academicScore  * self::WEIGHTS['academic'])  +
            ($socialScore    * self::WEIGHTS['social'])    +
            ($economicScore  * self::WEIGHTS['economic'])  +
            ($sdgScore       * self::WEIGHTS['sdg']),
            2
        );

        $result = [
            'status'           => 'success',
            'orcid'            => $orcid,
            'name'             => $orcidData['person_summary']['name'] ?? null,
            'composite'        => $composite,
            'pillars'          => [
                'academic'  => round($academicScore, 2),
                'social'    => round($socialScore,   2),
                'economic'  => round($economicScore, 2),
                'sdg'       => round($sdgScore,      2),
            ],
            'weights'          => self::WEIGHTS,
            'sdg_tags'         => $sdgTags,
            'sdg_by_work'      => $sdgByWork,
            'academic_metrics' => $this->academicMetrics($orcidData, $scopusData),
            'social_inputs'    => $social,
            'economic_inputs'  => $economic,
            'api_version'      => 'v1.1-batch',
            'calculated_at'    => date('c'),
            'cache_info'       => ['from_cache' => false],
        ];

        $this->cache->set('score', $scoreKey, $result);
        return $result;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function calculateAcademic(array $orcidData, array $scopusData): float
    {
        $pubCount      = count($orcidData['works'] ?? []);
        $hIndex        = (int) ($scopusData['author']['h_index']        ?? 0);
        $citationCount = (int) ($scopusData['author']['citation_count'] ?? 0);

        $hScore = min(100, $hIndex * 3.5);
        $cScore = min(100, $citationCount > 0 ? log10($citationCount + 1) * 25 : 0);
        $pScore = min(100, $pubCount * 1.2);

        return round(($hScore * 0.45) + ($cScore * 0.35) + ($pScore * 0.20), 2);
    }

    private function buildSdgScore(array $accum, int $totalWorks): array
    {
        $sdgAccumulator = $accum['sdg']     ?? [];
        $byWork         = $accum['by_work'] ?? [];

        if (empty($sdgAccumulator)) return [0.0, [], $byWork];

        $sdgTags = [];
        foreach ($sdgAccumulator as $sdgCode => $scores) {
            $avgScore  = array_sum($scores) / count($scores);
            $sdgNumber = (int) preg_replace('/\D/', '', $sdgCode);
            $sdgTags[] = [
                'sdg'   => $sdgNumber,
                'code'  => $sdgCode,
                'score' => round($avgScore, 3),
                'count' => count($scores),
                'label' => $this->sdgLabel($sdgNumber),
            ];
        }

        usort($sdgTags, fn($a, $b) => $b['score'] <=> $a['score']);
        $sdgTags = array_slice($sdgTags, 0, 10);

        $coverage      = min(1.0, count($sdgTags) / 5);
        $avgConfidence = array_sum(array_column($sdgTags, 'score')) / count($sdgTags);
        $sdgScore      = round(($coverage * 0.4 + $avgConfidence * 0.6) * 100, 2);

        return [$sdgScore, $sdgTags, $byWork];
    }

    private function computeSocialScore(array $social): float
    {
        if (empty($social)) return 0.0;
        $keys   = ['media_mentions', 'policy_citations', 'social_shares', 'news_coverage'];
        $values = array_values(array_filter(
            array_map(fn($k) => isset($social[$k]) ? (float) $social[$k] : null, $keys),
            fn($v) => $v !== null
        ));
        return empty($values) ? 0.0 : round(array_sum($values) / count($values), 2);
    }

    private function computeEconomicScore(array $economic): float
    {
        if (empty($economic)) return 0.0;
        $keys   = ['industry_adoption', 'patents', 'tech_transfer', 'startup_spinoffs'];
        $values = array_values(array_filter(
            array_map(fn($k) => isset($economic[$k]) ? (float) $economic[$k] : null, $keys),
            fn($v) => $v !== null
        ));
        return empty($values) ? 0.0 : round(array_sum($values) / count($values), 2);
    }

    private function academicMetrics(array $orcidData, array $scopusData): array
    {
        return [
            'publication_count' => count($orcidData['works'] ?? []),
            'h_index'           => (int) ($scopusData['author']['h_index']        ?? 0),
            'citation_count'    => (int) ($scopusData['author']['citation_count'] ?? 0),
            'cited_by_count'    => (int) ($scopusData['author']['cited_by_count'] ?? 0),
            'data_sources'      => array_values(array_filter(['orcid', empty($scopusData) ? null : 'scopus'])),
        ];
    }

    private function sdgLabel(int $n): string
    {
        return [
            1  => 'Tanpa Kemiskinan',       2  => 'Tanpa Kelaparan',
            3  => 'Kehidupan Sehat',         4  => 'Pendidikan Berkualitas',
            5  => 'Kesetaraan Gender',       6  => 'Air Bersih & Sanitasi',
            7  => 'Energi Bersih',           8  => 'Pekerjaan Layak',
            9  => 'Industri & Inovasi',      10 => 'Berkurangnya Kesenjangan',
            11 => 'Kota Berkelanjutan',      12 => 'Konsumsi Bertanggung Jawab',
            13 => 'Penanganan Iklim',        14 => 'Ekosistem Laut',
            15 => 'Ekosistem Darat',         16 => 'Perdamaian & Keadilan',
            17 => 'Kemitraan Global',
        ][$n] ?? "SDG $n";
    }

    private function error(int $code, string $message): array
    {
        http_response_code($code);
        return ['status' => 'error', 'code' => $code, 'message' => $message];
    }
}
