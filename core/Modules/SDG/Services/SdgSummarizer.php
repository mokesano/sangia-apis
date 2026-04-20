<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\SDG\Services;

use Sangia\Core\Modules\SDG\Config\SdgConfig;

class SdgSummarizer
{
    /**
     * Merekonstruksi profil lengkap peneliti dari tumpukan hasil analisis per karya
     */
    public function generateProfileSummary(array $worksResults): array
    {
        $sdgSummary = [];

        // 1. Kumpulkan statistik dasar per SDG dari seluruh karya
        foreach ($worksResults as $work) {
            $analysis = $work['sdg_analysis'] ?? [];
            if (empty($analysis['detailed_analysis'])) continue;

            foreach ($analysis['detailed_analysis'] as $sdgCode => $detail) {
                if ($detail['score'] >= SdgConfig::CONFIDENCE_THRESHOLD) {
                    if (!isset($sdgSummary[$sdgCode])) {
                        $sdgSummary[$sdgCode] = [
                            'work_count' => 0,
                            'average_confidence' => 0.0,
                            'high_confidence_works' => 0,
                            'contributor_types' => [
                                'Active Contributor' => 0,
                                'Relevant Contributor' => 0,
                                'Discutor' => 0
                            ],
                            'dominant_pathways' => [],
                            'example_works' => []
                        ];
                    }

                    // Update akumulasi
                    $sdgSummary[$sdgCode]['work_count']++;
                    $sdgSummary[$sdgCode]['average_confidence'] += $detail['score'];
                    
                    $type = $detail['contributor_type']['type'] ?? 'Discutor';
                    if (isset($sdgSummary[$sdgCode]['contributor_types'][$type])) {
                        $sdgSummary[$sdgCode]['contributor_types'][$type]++;
                    }

                    if ($detail['score'] >= SdgConfig::HIGH_CONFIDENCE_THRESHOLD) {
                        $sdgSummary[$sdgCode]['high_confidence_works']++;
                    }

                    // Ambil Pathway dominan jika ada (disimplifikasi)
                    $pathway = $analysis['contribution_pathways'][$sdgCode] ?? null;
                    if ($pathway) {
                        $sdgSummary[$sdgCode]['dominant_pathways'][$pathway] = 
                            ($sdgSummary[$sdgCode]['dominant_pathways'][$pathway] ?? 0) + 1;
                    }

                    // Kumpulkan contoh karya (maks 3)
                    if (count($sdgSummary[$sdgCode]['example_works']) < 3) {
                        $sdgSummary[$sdgCode]['example_works'][] = [
                            'title' => $work['title'],
                            'doi' => $work['doi'],
                            'confidence' => $detail['score'],
                            'contributor_type' => $type
                        ];
                    }
                }
            }
        }

        // 2. Finalisasi dan Hitung Rata-rata
        $contributorProfile = [];
        foreach ($sdgSummary as $sdgCode => &$summary) {
            if ($summary['work_count'] > 0) {
                $summary['average_confidence'] = round($summary['average_confidence'] / $summary['work_count'], 3);
            }
            
            if (!empty($summary['dominant_pathways'])) {
                arsort($summary['dominant_pathways']);
            }

            // Buat Profil Kontributor per SDG
            $contributorProfile[$sdgCode] = $this->buildContributorProfile($summary);
        }

        // Urutkan SDG berdasarkan jumlah karya terbanyak
        uasort($sdgSummary, fn($a, $b) => $b['work_count'] <=> $a['work_count']);

        return [
            'contributor_profile' => $contributorProfile,
            'researcher_sdg_summary' => $sdgSummary
        ];
    }

    /**
     * Menentukan profil kontributor dan kekuatan (Strength)
     */
    private function buildContributorProfile(array $summary): array
    {
        $activeCount = $summary['contributor_types']['Active Contributor'];
        $relevantCount = $summary['contributor_types']['Relevant Contributor'];
        $discussantCount = $summary['contributor_types']['Discutor'];
        $totalWorks = max(1, $summary['work_count']); // Hindari div by zero

        // Tentukan Tipe Dominan
        $dominantType = 'Discutor';
        if (($activeCount / $totalWorks) >= 0.3) {
            $dominantType = 'Active Contributor';
        } elseif ((($activeCount + $relevantCount) / $totalWorks) >= 0.5) {
            $dominantType = 'Relevant Contributor';
        }

        // Tentukan Pathway Dominan
        $dominantPathway = '';
        if (!empty($summary['dominant_pathways'])) {
            $dominantPathway = array_key_first($summary['dominant_pathways']);
        }

        return [
            'dominant_type' => $dominantType,
            'dominant_pathway' => $dominantPathway,
            'work_distribution' => [
                'active_contributor' => $activeCount,
                'relevant_contributor' => $relevantCount,
                'discussant' => $discussantCount
            ],
            'active_contributor_percentage' => round(($activeCount / $totalWorks) * 100, 1),
            'contribution_strength' => $this->determineContributionStrength($summary)
        ];
    }

    /**
     * Porting langsung dari fungsi asli Anda (Scoring 1-12)
     */
    private function determineContributionStrength(array $summary): string
    {
        $score = 0;
        $workCount = max(1, $summary['work_count']);

        // Faktor 1: Jumlah karya (max 3 pts)
        if ($workCount >= 10) $score += 3;
        elseif ($workCount >= 5) $score += 2;
        elseif ($workCount >= 3) $score += 1;

        // Faktor 2: Karya confidence tinggi (max 3 pts)
        $highConfRatio = $summary['high_confidence_works'] / $workCount;
        if ($highConfRatio >= 0.5) $score += 3;
        elseif ($highConfRatio >= 0.3) $score += 2;
        elseif ($highConfRatio >= 0.1) $score += 1;

        // Faktor 3: Tipe kontributor (max 4 pts)
        $activeRatio = $summary['contributor_types']['Active Contributor'] / $workCount;
        if ($activeRatio >= 0.5) $score += 4;
        elseif ($activeRatio >= 0.3) $score += 3;
        elseif ($activeRatio >= 0.2) $score += 2;
        elseif ($activeRatio >= 0.1) $score += 1;

        // Faktor 4: Konsentrasi jalur kontribusi (max 2 pts)
        if (!empty($summary['dominant_pathways'])) {
            $pathwayValues = array_values($summary['dominant_pathways']);
            $dominantPathwayRatio = $pathwayValues[0] / $workCount;
            
            if ($dominantPathwayRatio >= 0.6) $score += 2;
            elseif ($dominantPathwayRatio >= 0.3) $score += 1;
        }

        // Return Level
        if ($score >= 10) return 'Very Strong';
        if ($score >= 7) return 'Strong';
        if ($score >= 4) return 'Moderate';
        return 'Low';
    }
}