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
 * Calculates the composite Wizdam score for a researcher:
 *
 *   Composite = Academic×0.40 + Social×0.25 + Economic×0.20 + SDG×0.15
 *
 * Input  : ORCID ID (optionally enriched by Scopus author_id)
 * Process: fetch ORCID profile → fetch Scopus metrics (if key set) →
 *          run SDG analysis on all works → compute pillars → composite
 * Output : structured impact score with per-pillar breakdown
 */
class WizdamScoreEngine
{
    private const WEIGHTS = [
        'academic' => 0.40,
        'social'   => 0.25,
        'economic' => 0.20,
        'sdg'      => 0.15,
    ];

    private OrcidModule  $orcid;
    private ScopusModule $scopus;
    private SdgAnalyzer  $sdgAnalyzer;
    private CacheService $cache;

    public function __construct()
    {
        $this->orcid  = new OrcidModule();
        $this->scopus = new ScopusModule();
        $this->cache  = new CacheService('WizdamScore');

        $dictionary       = new SdgDictionary();
        $classifier       = new SdgClassifier($dictionary);
        $evaluator        = new LevelV4Evaluator();
        $this->sdgAnalyzer = new SdgAnalyzer($classifier, $evaluator, $dictionary);
    }

    /**
     * @param string      $orcid      ORCID iD (0000-0002-XXXX-XXXX)
     * @param string|null $scopusId   Optional Scopus author ID for richer metrics
     * @param array       $social     Optional social pillar overrides from Wizdam Sikola
     * @param array       $economic   Optional economic pillar overrides from Wizdam Sikola
     * @param bool        $refresh    Force re-calculation
     */
    public function calculate(
        string $orcid,
        ?string $scopusId   = null,
        array   $social     = [],
        array   $economic   = [],
        bool    $refresh    = false
    ): array {
        $orcid = trim($orcid);
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
            return $this->error(400, "Invalid ORCID: $orcid");
        }

        $cacheKey = $orcid . ($scopusId ? "_$scopusId" : '');

        if (!$refresh) {
            $cached = $this->cache->get('score', $cacheKey);
            if ($cached !== false) {
                $cached['cache_info'] = ['from_cache' => true];
                return $cached;
            }
        }

        // ── 1. Fetch researcher profile ────────────────────────────────────
        $orcidData   = $this->orcid->getProfile($orcid, $refresh);
        $scopusData  = [];

        if ($scopusId) {
            $scopusData = $this->scopus->getAuthor($scopusId, 25, $refresh);
        }

        // ── 2. Academic Pillar ─────────────────────────────────────────────
        $academicScore = $this->calculateAcademic($orcidData, $scopusData);

        // ── 3. SDG Pillar — analyse all works ─────────────────────────────
        [$sdgScore, $sdgTags, $sdgByWork] = $this->calculateSdg($orcidData);

        // ── 4. Social Pillar (provided by Wizdam Sikola or 0) ─────────────
        $socialScore = $this->computeSocialScore($social);

        // ── 5. Economic Pillar (provided by Wizdam Sikola or 0) ──────────
        $economicScore = $this->computeEconomicScore($economic);

        // ── 6. Composite ──────────────────────────────────────────────────
        $composite = round(
            ($academicScore  * self::WEIGHTS['academic'])  +
            ($socialScore    * self::WEIGHTS['social'])    +
            ($economicScore  * self::WEIGHTS['economic'])  +
            ($sdgScore       * self::WEIGHTS['sdg']),
            2
        );

        $result = [
            'status'        => 'success',
            'orcid'         => $orcid,
            'name'          => $orcidData['person_summary']['name'] ?? null,
            'composite'     => $composite,
            'pillars'       => [
                'academic'  => round($academicScore,  2),
                'social'    => round($socialScore,    2),
                'economic'  => round($economicScore,  2),
                'sdg'       => round($sdgScore,       2),
            ],
            'weights'       => self::WEIGHTS,
            'sdg_tags'      => $sdgTags,
            'sdg_by_work'   => $sdgByWork,
            'academic_metrics' => $this->academicMetrics($orcidData, $scopusData),
            'social_inputs'    => $social,
            'economic_inputs'  => $economic,
            'api_version'      => 'v1.0-modular',
            'calculated_at'    => date('c'),
            'cache_info'       => ['from_cache' => false],
        ];

        $this->cache->set('score', $cacheKey, $result);
        return $result;
    }

    // ── Pillar calculators ────────────────────────────────────────────────────

    private function calculateAcademic(array $orcidData, array $scopusData): float
    {
        $works         = $orcidData['works'] ?? [];
        $pubCount      = count($works);
        $hIndex        = (int) ($scopusData['author']['h_index']        ?? 0);
        $citationCount = (int) ($scopusData['author']['citation_count'] ?? 0);

        // Normalize to 0–100
        $hScore   = min(100, $hIndex * 3.5);
        $cScore   = min(100, $citationCount > 0 ? log10($citationCount + 1) * 25 : 0);
        $pScore   = min(100, $pubCount * 1.2);

        // Weighted average of sub-components
        return round(($hScore * 0.45) + ($cScore * 0.35) + ($pScore * 0.20), 2);
    }

    private function calculateSdg(array $orcidData): array
    {
        $works  = $orcidData['works'] ?? [];
        if (empty($works)) return [0.0, [], []];

        $sdgAccumulator = []; // sdgCode => [scores]
        $byWork         = [];

        foreach (array_slice($works, 0, 50) as $work) {
            $title    = $work['title']     ?? '';
            $abstract = $work['abstract']  ?? '';

            if (empty($title)) continue;

            try {
                $analysis = $this->sdgAnalyzer->analyzeWork($title, $abstract);
            } catch (\Throwable) {
                continue;
            }

            $workSdgs = [];
            foreach ($analysis['sdg_confidence'] ?? [] as $sdgCode => $score) {
                $sdgAccumulator[$sdgCode][] = (float) $score;
                $workSdgs[] = ['sdg' => $sdgCode, 'score' => round((float) $score, 3)];
            }

            if (!empty($workSdgs)) {
                $byWork[] = ['title' => substr($title, 0, 80), 'sdgs' => $workSdgs];
            }
        }

        // Average confidence per SDG across all works
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

        // SDG pillar score = weighted average of top SDG scores (coverage × confidence)
        $sdgScore = 0.0;
        if (!empty($sdgTags)) {
            $totalWorks    = max(1, count($works));
            $coverage      = min(1.0, count($sdgTags) / 5);          // max 5 distinct SDGs → full
            $avgConfidence = array_sum(array_column($sdgTags, 'score')) / count($sdgTags);
            $sdgScore      = round(($coverage * 0.4 + $avgConfidence * 0.6) * 100, 2);
        }

        return [$sdgScore, $sdgTags, $byWork];
    }

    private function computeSocialScore(array $social): float
    {
        if (empty($social)) return 0.0;

        // Expected keys (0–100 each): media_mentions, policy_citations, social_shares, news_coverage
        $keys   = ['media_mentions', 'policy_citations', 'social_shares', 'news_coverage'];
        $values = [];
        foreach ($keys as $k) {
            if (isset($social[$k])) $values[] = (float) $social[$k];
        }

        return empty($values) ? 0.0 : round(array_sum($values) / count($values), 2);
    }

    private function computeEconomicScore(array $economic): float
    {
        if (empty($economic)) return 0.0;

        // Expected keys: industry_adoption, patents, tech_transfer, startup_spinoffs
        $keys   = ['industry_adoption', 'patents', 'tech_transfer', 'startup_spinoffs'];
        $values = [];
        foreach ($keys as $k) {
            if (isset($economic[$k])) $values[] = (float) $economic[$k];
        }

        return empty($values) ? 0.0 : round(array_sum($values) / count($values), 2);
    }

    private function academicMetrics(array $orcidData, array $scopusData): array
    {
        return [
            'publication_count' => count($orcidData['works'] ?? []),
            'h_index'           => (int) ($scopusData['author']['h_index']        ?? 0),
            'citation_count'    => (int) ($scopusData['author']['citation_count'] ?? 0),
            'cited_by_count'    => (int) ($scopusData['author']['cited_by_count'] ?? 0),
            'data_sources'      => array_filter(['orcid', empty($scopusData) ? null : 'scopus']),
        ];
    }

    private function sdgLabel(int $n): string
    {
        return [
            1=>'Tanpa Kemiskinan', 2=>'Tanpa Kelaparan', 3=>'Kehidupan Sehat',
            4=>'Pendidikan Berkualitas', 5=>'Kesetaraan Gender', 6=>'Air Bersih & Sanitasi',
            7=>'Energi Bersih', 8=>'Pekerjaan Layak', 9=>'Industri & Inovasi',
            10=>'Berkurangnya Kesenjangan', 11=>'Kota Berkelanjutan',
            12=>'Konsumsi Bertanggung Jawab', 13=>'Penanganan Iklim',
            14=>'Ekosistem Laut', 15=>'Ekosistem Darat',
            16=>'Perdamaian & Keadilan', 17=>'Kemitraan Global',
        ][$n] ?? "SDG $n";
    }

    private function error(int $code, string $message): array
    {
        http_response_code($code);
        return ['status' => 'error', 'code' => $code, 'message' => $message];
    }
}
