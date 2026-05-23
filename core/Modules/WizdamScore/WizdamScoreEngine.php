<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\SangiaScore;

use Sangia\Core\Modules\ORCID\OrcidModule;
use Sangia\Core\Modules\Scopus\ScopusModule;
use Sangia\Core\Modules\SDG\Services\SdgAnalyzer;
use Sangia\Core\Modules\SDG\Services\SdgClassifier;
use Sangia\Core\Modules\SDG\Services\Evaluator\LevelV4Evaluator;
use Sangia\Core\Modules\SDG\Config\SdgDictionary;
use Sangia\Core\Shared\Services\CacheService;

/**
 * Sangia Impact Score Engine.
 *
 * Formula: Composite = Academic×0.40 + Social×0.25 + Economic×0.20 + SDG×0.15
 *
 * No result caching here. Sangia Sikola owns all persistence:
 *   - pass $suppliedWorks / $suppliedScopus to skip external API calls
 *   - response includes 'raw_data' so Sangia Sikola can save fresh fetches to DB
 *
 * CacheService is only used for short-lived batch session state (partial accumulator).
 *
 * Anti-timeout batch pattern:
 *   Call 1: offset=0  → {status:"processing", next_offset:20, progress:{...}}
 *   Call 2: offset=20 → {status:"processing", next_offset:40, progress:{...}}
 *   Call 3: offset=40 → {status:"success", composite:..., pillars:{...}}
 */
class SangiaScoreEngine
{
    private const WEIGHTS = [
        'academic' => 0.40,
        'social'   => 0.25,
        'economic' => 0.20,
        'sdg'      => 0.15,
    ];

    private const MAX_WORKS  = 50;
    private const BATCH_SIZE = 20;

    private OrcidModule  $orcid;
    private ScopusModule $scopus;
    private SdgAnalyzer  $sdgAnalyzer;
    private CacheService $batchState; // batch session state only — NOT result cache

    public function __construct()
    {
        $this->orcid      = new OrcidModule();
        $this->scopus     = new ScopusModule();
        $this->batchState = new CacheService('SangiaScore');

        $dictionary        = new SdgDictionary();
        $classifier        = new SdgClassifier($dictionary);
        $evaluator         = new LevelV4Evaluator();
        $this->sdgAnalyzer = new SdgAnalyzer($classifier, $evaluator, $dictionary);
    }

    /**
     * @param string      $orcid           ORCID iD
     * @param string|null $scopusId        Scopus Author ID
     * @param array       $social          Social pillar data (0–100 per metric)
     * @param array       $economic        Economic pillar data (0–100 per metric)
     * @param bool        $refresh         Force re-fetch even if supplied data present
     * @param int         $batchSize       Works processed per HTTP request
     * @param int         $offset          Batch starting index (0 = first call)
     * @param array       $weightOverride  Sangia Sikola admin composite weights
     * @param array       $suppliedWorks   Works from Sangia Sikola DB — skips ORCID cURL
     * @param array|null  $suppliedPerson  Person summary from Sangia Sikola DB
     * @param array|null  $suppliedScopus  Scopus author data from Sangia Sikola DB
     */
    public function calculate(
        string  $orcid,
        ?string $scopusId       = null,
        array   $social         = [],
        array   $economic       = [],
        bool    $refresh        = false,
        int     $batchSize      = self::BATCH_SIZE,
        int     $offset         = 0,
        array   $weightOverride = [],
        array   $suppliedWorks  = [],
        ?array  $suppliedPerson = null,
        ?array  $suppliedScopus = null
    ): array {
        $orcid = trim($orcid);
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
            return $this->error(400, "Invalid ORCID: $orcid");
        }

        // ── Resolve ORCID data ────────────────────────────────────────────────
        if (!$refresh && !empty($suppliedWorks)) {
            $works         = array_slice($suppliedWorks, 0, self::MAX_WORKS);
            $personSummary = $suppliedPerson ?? [];
            $orcidSource   = 'sangia_sikola_db';
            $rawOrcidData  = null;
        } else {
            $orcidData = $this->orcid->getProfile($orcid, $refresh && $offset === 0);
            if (($orcidData['status'] ?? '') === 'error') {
                return $this->error(502, $orcidData['message'] ?? 'ORCID fetch failed');
            }
            $works         = array_slice($orcidData['works'] ?? [], 0, self::MAX_WORKS);
            $personSummary = $orcidData['person_summary'] ?? [];
            $orcidSource   = 'orcid_api';
            $rawOrcidData  = $orcidData['raw_data'] ?? null;
        }

        $totalWorks = count($works);
        $accumKey   = 'accum_' . md5($orcid . ($scopusId ? "_$scopusId" : ''));

        // Load or initialise batch accumulator
        if ($offset === 0) {
            $accum = ['sdg' => [], 'by_work' => []];
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

        // ── Process this batch ────────────────────────────────────────────────
        foreach (array_slice($works, $offset, $batchSize) as $work) {
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

        $this->batchState->set('partial', $accumKey, $accum);

        $processed = min($offset + $batchSize, $totalWorks);
        $isDone    = ($processed >= $totalWorks) || empty(array_slice($works, $offset, $batchSize));

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

        // ── Final batch — compute composite score ─────────────────────────────

        // Resolve Scopus data
        $rawScopusData = null;
        if ($scopusId) {
            if (!$refresh && $suppliedScopus !== null) {
                $scopusData   = $suppliedScopus;
                $scopusSource = 'sangia_sikola_db';
            } else {
                $scopusFull    = $this->scopus->getAuthor($scopusId, 25, false);
                $scopusData    = $scopusFull;
                $scopusSource  = 'scopus_api';
                $rawScopusData = $scopusFull['raw_data'] ?? null;
            }
        } else {
            $scopusData   = [];
            $scopusSource = 'none';
        }

        $academicScore = $this->calculateAcademic($works, $scopusData);
        [$sdgScore, $sdgTags, $sdgByWork] = $this->buildSdgScore($accum, $totalWorks);
        $socialScore   = $this->computeSocialScore($social);
        $economicScore = $this->computeEconomicScore($economic);

        $w = [
            'academic' => (float) ($weightOverride['academic'] ?? self::WEIGHTS['academic']),
            'social'   => (float) ($weightOverride['social']   ?? self::WEIGHTS['social']),
            'economic' => (float) ($weightOverride['economic'] ?? self::WEIGHTS['economic']),
            'sdg'      => (float) ($weightOverride['sdg']      ?? self::WEIGHTS['sdg']),
        ];

        $composite = round(
            ($academicScore  * $w['academic'])  +
            ($socialScore    * $w['social'])    +
            ($economicScore  * $w['economic'])  +
            ($sdgScore       * $w['sdg']),
            2
        );

        $result = [
            'status'           => 'success',
            'orcid'            => $orcid,
            'name'             => $personSummary['name'] ?? null,
            'composite'        => $composite,
            'pillars'          => [
                'academic'  => round($academicScore, 2),
                'social'    => round($socialScore,   2),
                'economic'  => round($economicScore, 2),
                'sdg'       => round($sdgScore,      2),
            ],
            'weights'          => $w,
            'sdg_tags'         => $sdgTags,
            'sdg_by_work'      => $sdgByWork,
            'academic_metrics' => $this->academicMetrics($works, $scopusData),
            'social_inputs'    => $social,
            'economic_inputs'  => $economic,
            'data_sources'     => [
                'orcid'  => $orcidSource,
                'scopus' => $scopusSource,
            ],
            'api_version'      => 'v1.1-batch',
            'calculated_at'    => date('c'),
            'cache_info'       => ['from_cache' => false],
        ];

        // Include raw fetched data for Sangia Sikola to persist
        $rawData = [];
        if ($rawOrcidData !== null) {
            $rawData['orcid'] = $rawOrcidData;
        }
        if ($rawScopusData !== null) {
            $rawData['scopus'] = $rawScopusData;
        }
        if (!empty($rawData)) {
            $result['raw_data'] = $rawData;
        }

        return $result;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function calculateAcademic(array $works, array $scopusData): float
    {
        $pubCount      = count($works);
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

    private function academicMetrics(array $works, array $scopusData): array
    {
        return [
            'publication_count' => count($works),
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
