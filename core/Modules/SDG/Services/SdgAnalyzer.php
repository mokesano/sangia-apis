<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\SDG\Services;

use Sangia\Core\Modules\SDG\Config\SdgConfig;
use Sangia\Core\Modules\SDG\Config\SdgDictionary;
use Sangia\Core\Modules\SDG\Services\Evaluator\LevelV4Evaluator;

class SdgAnalyzer
{
    private SdgClassifier $classifier;
    private LevelV4Evaluator $v4Evaluator;
    private SdgDictionary $dictionary;

    /**
     * Full version config: weights + thresholds.
     * Keys: keyword, similarity, substantive, causal, thresholds:{min,confidence,high}, max_sdgs
     * Source: VersionConfig::get($version) merged with request-level overrides from Sangia Scieco.
     * Hardcoded values in SdgConfig are fallback only.
     */
    private array $config = [];

    public function __construct(
        SdgClassifier $classifier,
        LevelV4Evaluator $v4Evaluator,
        SdgDictionary $dictionary,
        array $config = []
    ) {
        $this->classifier  = $classifier;
        $this->v4Evaluator = $v4Evaluator;
        $this->dictionary  = $dictionary;
        $this->config      = $config;
    }

    /**
     * Menganalisis satu karya (Judul + Abstrak) secara komprehensif
     */
    public function analyzeWork(string $title, string $abstract): array
    {
        $fullText = $title . ' ' . $abstract;
        $baseScores = $this->classifier->calculateBaseScores($fullText);
        
        $detailedAnalysis = [];
        $filteredSdgs = [];
        $sdgConfidence = [];
        $sdgContributorTypes = [];
        $contributionPathways = [];

        // Adaptasi Bobot — gunakan override versi jika ada, lalu sesuaikan panjang teks
        $weights = $this->determineWeights(strlen($fullText));

        foreach ($baseScores as $sdgCode => $baseScore) {
            // Evaluasi Lanjutan (V4)
            $substantive = $this->v4Evaluator->analyzeSubstantiveContribution($fullText, $sdgCode);
            
            $relevantKeywords = $this->dictionary->getKeywordsFor($sdgCode);
            $causal = $this->v4Evaluator->detectCausalRelationship($fullText, $sdgCode, $relevantKeywords);

            // Perhitungan Kombinasi Skor Akhir
            $combinedScore = (
                ($baseScore['keyword_freq'] * $weights['KEYWORD_WEIGHT']) +
                ($baseScore['similarity'] * $weights['SIMILARITY_WEIGHT']) +
                ($substantive['score'] * $weights['SUBSTANTIVE_WEIGHT']) +
                ($causal['score'] * $weights['CAUSAL_WEIGHT'])
            );

            $minScore   = $this->threshold('min',  SdgConfig::MIN_SCORE_THRESHOLD);
            $confScore  = $this->threshold('confidence', SdgConfig::CONFIDENCE_THRESHOLD);

            // Hanya proses yang melampaui batas minimal
            if ($combinedScore > $minScore) {
                // Tentukan Tipe Kontributor
                $contributorInfo = $this->v4Evaluator->determineContributorType(
                    $combinedScore,
                    $causal['score'],
                    $substantive['score']
                );

                // Susun array detail per SDG
                $detailedAnalysis[$sdgCode] = [
                    'score' => round($combinedScore, 3),
                    'confidence_level' => $this->getConfidenceLevel($combinedScore),
                    'contributor_type' => $contributorInfo,
                    'components' => [
                        'keyword_score'     => round((float) $baseScore['keyword_freq'], 3),
                        'similarity_score'  => round((float) $baseScore['similarity'],   3),
                        'substantive_score' => round($substantive['score'],              3),
                        'causal_score'      => round($causal['score'],                   3),
                    ],
                    'evidence' => [
                        'causal_relationship' => $causal['evidence'] ?? [],
                    ],
                ];

                // Filter berdasarkan Confidence Threshold
                if ($combinedScore >= $confScore) {
                    $filteredSdgs[] = $sdgCode;
                    $sdgConfidence[$sdgCode] = round($combinedScore, 3);
                    $sdgContributorTypes[$sdgCode] = $contributorInfo['type'];

                    $pathways = SdgConfig::getContributionPathways($sdgCode);
                    if (!empty($pathways)) {
                        $contributionPathways[$sdgCode] = array_key_first($pathways);
                    }
                }
            }
        }

        // Urutkan dari confidence tertinggi
        arsort($sdgConfidence);

        $maxSdgs = $this->config['max_sdgs'] ?? SdgConfig::MAX_SDGS_PER_WORK;

        // Batasi maksimal SDG per karya
        if (count($filteredSdgs) > $maxSdgs) {
            $sdgConfidence = array_slice($sdgConfidence, 0, $maxSdgs, true);
            $filteredSdgs  = array_keys($sdgConfidence);
            
            // Filter array lain agar sinkron
            $sdgContributorTypes = array_intersect_key($sdgContributorTypes, $sdgConfidence);
            $contributionPathways = array_intersect_key($contributionPathways, $sdgConfidence);
        }

        return [
            'sdgs' => $filteredSdgs,
            'sdg_confidence' => $sdgConfidence,
            'contributor_types' => $sdgContributorTypes,
            'contribution_pathways' => $contributionPathways,
            'detailed_analysis' => $detailedAnalysis
        ];
    }

    /**
     * Determine scoring weights.
     * Priority: request override (from Sangia Scieco admin) → version config → SdgConfig defaults.
     */
    private function determineWeights(int $textLength): array
    {
        $c = $this->config;

        if (!empty($c)) {
            return [
                'KEYWORD_WEIGHT'     => (float) ($c['keyword']     ?? SdgConfig::KEYWORD_WEIGHT),
                'SIMILARITY_WEIGHT'  => (float) ($c['similarity']  ?? SdgConfig::SIMILARITY_WEIGHT),
                'SUBSTANTIVE_WEIGHT' => (float) ($c['substantive'] ?? SdgConfig::SUBSTANTIVE_WEIGHT),
                'CAUSAL_WEIGHT'      => (float) ($c['causal']      ?? SdgConfig::CAUSAL_WEIGHT),
            ];
        }

        if ($textLength < 100) {
            return ['KEYWORD_WEIGHT' => 0.40, 'SIMILARITY_WEIGHT' => 0.40, 'SUBSTANTIVE_WEIGHT' => 0.10, 'CAUSAL_WEIGHT' => 0.10];
        }

        return [
            'KEYWORD_WEIGHT'     => SdgConfig::KEYWORD_WEIGHT,
            'SIMILARITY_WEIGHT'  => SdgConfig::SIMILARITY_WEIGHT,
            'SUBSTANTIVE_WEIGHT' => SdgConfig::SUBSTANTIVE_WEIGHT,
            'CAUSAL_WEIGHT'      => SdgConfig::CAUSAL_WEIGHT,
        ];
    }

    /** Read a threshold, checking config['thresholds'] first, then falling back to $default. */
    private function threshold(string $key, float $default): float
    {
        return (float) ($this->config['thresholds'][$key] ?? $default);
    }

    private function getConfidenceLevel(float $score): string
    {
        $high = $this->threshold('high', SdgConfig::HIGH_CONFIDENCE_THRESHOLD);
        $mid  = $this->threshold('confidence', SdgConfig::CONFIDENCE_THRESHOLD);
        if ($score > $high) return 'High';
        if ($score > $mid)  return 'Middle';
        return 'Low';
    }
}