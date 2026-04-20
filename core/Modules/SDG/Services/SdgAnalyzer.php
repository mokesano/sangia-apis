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

    public function __construct(
        SdgClassifier $classifier,
        LevelV4Evaluator $v4Evaluator,
        SdgDictionary $dictionary
    ) {
        $this->classifier = $classifier;
        $this->v4Evaluator = $v4Evaluator;
        $this->dictionary = $dictionary;
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

        // Adaptasi Bobot (Sama seperti kode asli: jika teks terlalu pendek/tanpa abstrak)
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

            // Hanya proses yang melampaui batas minimal
            if ($combinedScore > SdgConfig::MIN_SCORE_THRESHOLD) {
                // Tentukan Tipe Kontributor
                $contributorInfo = $this->v4Evaluator->determineContributorType(
                    $combinedScore, 
                    $causal['score'], 
                    $substantive['score'] // Menggunakan substantive sebagai proxy impact untuk contoh ini
                );

                // Susun array detail per SDG
                $detailedAnalysis[$sdgCode] = [
                    'score' => round($combinedScore, 3),
                    'confidence_level' => $this->getConfidenceLevel($combinedScore),
                    'contributor_type' => $contributorInfo,
                    'components' => [
                        'keyword_score' => round((float)$baseScore['keyword_freq'], 3),
                        'similarity_score' => round((float)$baseScore['similarity'], 3),
                        'substantive_score' => round($substantive['score'], 3),
                        'causal_score' => round($causal['score'], 3)
                    ],
                    'evidence' => [
                        'causal_relationship' => $causal['evidence'] ?? []
                    ]
                ];

                // Filter berdasarkan Confidence Threshold
                if ($combinedScore >= SdgConfig::CONFIDENCE_THRESHOLD) {
                    $filteredSdgs[] = $sdgCode;
                    $sdgConfidence[$sdgCode] = round($combinedScore, 3);
                    $sdgContributorTypes[$sdgCode] = $contributorInfo['type'];
                    
                    // Ambil dominan pathway jika ada (disimplifikasi)
                    $pathways = SdgConfig::getContributionPathways($sdgCode);
                    if (!empty($pathways)) {
                        $contributionPathways[$sdgCode] = array_key_first($pathways);
                    }
                }
            }
        }

        // Urutkan dari confidence tertinggi
        arsort($sdgConfidence);

        // Batasi maksimal SDG per karya
        if (count($filteredSdgs) > SdgConfig::MAX_SDGS_PER_WORK) {
            $sdgConfidence = array_slice($sdgConfidence, 0, SdgConfig::MAX_SDGS_PER_WORK, true);
            $filteredSdgs = array_keys($sdgConfidence);
            
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
     * Penyesuaian bobot adaptif berdasarkan panjang teks
     */
    private function determineWeights(int $textLength): array
    {
        if ($textLength < 100) {
            // Teks sangat pendek (misal hanya judul)
            return [
                'KEYWORD_WEIGHT' => 0.40,
                'SIMILARITY_WEIGHT' => 0.40,
                'SUBSTANTIVE_WEIGHT' => 0.10,
                'CAUSAL_WEIGHT' => 0.10
            ];
        }

        // Standar V4
        return [
            'KEYWORD_WEIGHT' => SdgConfig::KEYWORD_WEIGHT,
            'SIMILARITY_WEIGHT' => SdgConfig::SIMILARITY_WEIGHT,
            'SUBSTANTIVE_WEIGHT' => SdgConfig::SUBSTANTIVE_WEIGHT,
            'CAUSAL_WEIGHT' => SdgConfig::CAUSAL_WEIGHT
        ];
    }

    /**
     * Konversi angka menjadi level
     */
    private function getConfidenceLevel(float $score): string
    {
        if ($score > SdgConfig::HIGH_CONFIDENCE_THRESHOLD) return 'High';
        if ($score > SdgConfig::CONFIDENCE_THRESHOLD) return 'Middle';
        return 'Low';
    }
}