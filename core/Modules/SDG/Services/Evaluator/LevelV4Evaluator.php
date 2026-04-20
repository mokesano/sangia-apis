<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\SDG\Services\Evaluator;

use Sangia\Core\Modules\SDG\Config\SdgConfig;
use Sangia\Core\Shared\Helpers\TextHelper;

class LevelV4Evaluator
{
    private array $runtimeCache = []; // Pengganti $MEMORY_CACHE global

    /**
     * Analisis Kontribusi Substantif (Metodologi, Solusi, Dampak)
     */
    public function analyzeSubstantiveContribution(string $text, string $sdgCode): array
    {
        $cacheKey = md5($text . '_' . $sdgCode . '_substantive');
        if (isset($this->runtimeCache[$cacheKey])) {
            return $this->runtimeCache[$cacheKey];
        }

        // Ambil indikator dari impact indicators (bisa disesuaikan jika Anda punya array khusus substantif)
        $indicators = [
            'solution' => ['solution', 'strategy', 'approach', 'solusi', 'strategi', 'pendekatan'],
            'impact' => ['impact', 'effect', 'outcome', 'dampak', 'efek', 'hasil'],
            'method' => ['survey', 'interview', 'analysis', 'survei', 'wawancara', 'analisis']
        ];

        $scores = [];
        $phrases = TextHelper::extractPhrases($text);

        foreach ($indicators as $category => $words) {
            $categoryScore = 0;
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $categoryScore++;
                    // Bonus jika muncul dalam frasa bermakna
                    foreach ($phrases as $phrase) {
                        if (stripos($phrase, $word) !== false) {
                            $categoryScore += 0.5;
                            break;
                        }
                    }
                }
            }
            $divisor = count($words) * 0.5;
            $scores[$category] = min(1.0, $divisor > 0 ? $categoryScore / $divisor : 0.0);
        }

        $avgScore = !empty($scores) ? array_sum($scores) / count($scores) : 0.0;
        
        $result = ['score' => $avgScore, 'components' => $scores];
        $this->runtimeCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Deteksi Hubungan Kausal (Bobot 0.20 di V4)
     */
    public function detectCausalRelationship(string $text, string $sdgCode, array $relevantKeywords): array
    {
        $cacheKey = md5($text . '_' . $sdgCode . '_causal');
        if (isset($this->runtimeCache[$cacheKey])) {
            return $this->runtimeCache[$cacheKey];
        }

        $patterns = SdgConfig::getCausalPatterns();
        $verbs = SdgConfig::getTransformativeVerbs();
        $score = 0.0;
        $evidences = [];

        // 1. Deteksi Kausalitas Langsung (Pola + Keyword SDG)
        foreach ($patterns as $pattern) {
            foreach ($relevantKeywords as $keyword) {
                // Cek "pattern keyword"
                if (stripos($text, $pattern . ' ' . $keyword) !== false) {
                    $score += 0.3;
                    $evidences[] = [
                        'type' => 'direct_causality',
                        'context' => TextHelper::extractKeywordContext($text, $pattern . ' ' . $keyword)
                    ];
                }
                // Cek "keyword pattern"
                if (stripos($text, $keyword . ' ' . $pattern) !== false) {
                    $score += 0.3;
                    $evidences[] = [
                        'type' => 'direct_causality',
                        'context' => TextHelper::extractKeywordContext($text, $keyword . ' ' . $pattern)
                    ];
                }
            }
        }

        // 2. Deteksi Kata Kerja Transformatif (Jarak < 50 karakter)
        foreach ($verbs as $verb) {
            foreach ($relevantKeywords as $keyword) {
                $verbPos = stripos($text, $verb);
                $keywordPos = stripos($text, $keyword);
                
                if ($verbPos !== false && $keywordPos !== false) {
                    $distance = abs($verbPos - $keywordPos);
                    if ($distance < 50) {
                        $score += 0.25;
                        $context = substr($text, max(0, min($verbPos, $keywordPos) - 30), 100);
                        $evidences[] = [
                            'type' => 'transformative_verb',
                            'context' => '...' . $context . '...'
                        ];
                        break; 
                    }
                }
            }
        }

        $result = [
            'score' => min(1.0, $score),
            'evidence' => array_slice($evidences, 0, 3) // Batasi maksimal 3 bukti
        ];
        
        $this->runtimeCache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Menentukan Tipe Kontributor (Active, Relevant, Discutor)
     */
    public function determineContributorType(float $combinedScore, float $causalScore, float $impactScore): array
    {
        // Formula kontribusi dari kode asli Anda
        $contributionScore = ($combinedScore * 0.5) + ($causalScore * 0.3) + ($impactScore * 0.2);
        
        if ($contributionScore >= SdgConfig::ACTIVE_CONTRIBUTOR_THRESHOLD && $causalScore >= 0.3 && $impactScore >= 0.3) {
            return ['type' => 'Active Contributor', 'score' => round($contributionScore, 3)];
        } 
        
        if ($contributionScore >= SdgConfig::RELEVANT_CONTRIBUTOR_THRESHOLD) {
            return ['type' => 'Relevant Contributor', 'score' => round($contributionScore, 3)];
        } 
        
        if ($contributionScore >= SdgConfig::DISCUSSANT_THRESHOLD) {
            return ['type' => 'Discutor', 'score' => round($contributionScore, 3)];
        }
        
        return ['type' => 'Not Relevant', 'score' => round($contributionScore, 3)];
    }
}