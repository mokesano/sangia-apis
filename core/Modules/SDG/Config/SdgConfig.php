<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\SDG\Config;

class SdgConfig
{
    // Konfigurasi Threshold & Bobot Dasar
    public const MIN_SCORE_THRESHOLD = 0.20;
    public const CONFIDENCE_THRESHOLD = 0.30;
    public const HIGH_CONFIDENCE_THRESHOLD = 0.60;
    public const MAX_SDGS_PER_WORK = 7;
    
    // Konfigurasi Bobot V4
    public const KEYWORD_WEIGHT = 0.30;
    public const SIMILARITY_WEIGHT = 0.30;
    public const SUBSTANTIVE_WEIGHT = 0.20;
    public const CAUSAL_WEIGHT = 0.20;
    
    // Threshold Kontributor
    public const ACTIVE_CONTRIBUTOR_THRESHOLD = 0.50;
    public const RELEVANT_CONTRIBUTOR_THRESHOLD = 0.35;
    public const DISCUSSANT_THRESHOLD = 0.25;

    /**
     * Mengembalikan kata kunci orientasi dampak
     */
    public static function getImpactIndicators(): array
    {
        return [
            'solution_words' => ['solution', 'framework', 'model', 'approach', 'strategy', 'solusi', 'kerangka', 'pendekatan', 'strategi'],
            'policy_words' => ['policy', 'regulation', 'governance', 'planning', 'kebijakan', 'regulasi', 'tata kelola', 'perencanaan'],
            'outcome_words' => ['impact', 'outcome', 'result', 'improvement', 'dampak', 'hasil', 'peningkatan', 'manfaat'],
            'stakeholder_words' => ['community', 'stakeholder', 'participant', 'komunitas', 'pemangku kepentingan', 'peserta'],
            'evaluation_words' => ['evaluation', 'assessment', 'monitoring', 'evaluasi', 'penilaian', 'pemantauan']
        ];
    }

    /**
     * Mengembalikan kata kerja transformatif
     */
    public static function getTransformativeVerbs(): array
    {
        return [
            'develop', 'implement', 'improve', 'enhance', 'establish', 'strengthen', 'transform', 'create', 
            'mengembangkan', 'mengimplementasikan', 'meningkatkan', 'memperbaiki', 'membangun', 'memperkuat'
        ];
    }

    /**
     * Mengembalikan pola kausalitas (Causal Patterns)
     */
    public static function getCausalPatterns(): array
    {
        return [
            'contributes to', 'supports', 'advances', 'helps achieve', 'improves',
            'untuk', 'agar', 'supaya', 'mendukung', 'membantu', 'guna', 'dapat', 'mencegah'
        ];
    }

    /**
     * Mengembalikan jalur kontribusi spesifik per SDG
     */
    public static function getContributionPathways(string $sdgCode): array
    {
        $pathways = [
            'SDG1' => [
                'poverty_reduction' => ['poverty reduction', 'poverty alleviation', 'income increase'],
                'social_protection' => ['social protection', 'safety net', 'social security']
            ],
            'SDG2' => [
                'food_security' => ['food security', 'food availability', 'food access'],
                'sustainable_agriculture' => ['sustainable agriculture', 'agroecology', 'pertanian berkelanjutan']
            ]
            // Tambahkan Sdg3 - Sdg17 sesuai kode asli Anda di sini...
        ];

        return $pathways[$sdgCode] ?? [];
    }
}