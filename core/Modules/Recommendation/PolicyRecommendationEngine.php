<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\Recommendation;

/**
 * Policy Recommendation Engine — API #8
 *
 * Generates evidence-based policy recommendations for different stakeholders.
 * Stateless: no caching. Wizdam Sikola owns all persistence.
 *
 * Supported stakeholder types: government, institution, industry, researcher, community
 *
 * Pass $researchLandscape (aggregated stats) from Wizdam Sikola DB for data-driven
 * recommendations. Without it, returns general template-based recommendations.
 */
class PolicyRecommendationEngine
{
    /**
     * @param string $stakeholderType  government | institution | industry | researcher | community
     * @param string $domain           e.g. education, research_funding, sdg_achievement, innovation
     * @param string $timeHorizon      short | medium | long
     * @param string $region           Region/country name for context
     * @param array  $researchLandscape Aggregated stats from Wizdam Sikola DB
     */
    public function generate(
        string $stakeholderType    = 'government',
        string $domain             = 'general',
        string $timeHorizon        = 'medium',
        string $region             = 'Indonesia',
        array  $researchLandscape  = []
    ): array {
        $validTypes = ['government', 'institution', 'industry', 'researcher', 'community'];
        if (!in_array($stakeholderType, $validTypes, true)) {
            return $this->error(400, "Invalid stakeholder_type. Supported: " . implode(', ', $validTypes));
        }

        $landscape = $this->buildLandscape($researchLandscape, $region);

        $recommendations = match ($stakeholderType) {
            'government'  => $this->forGovernment($domain, $timeHorizon, $landscape),
            'institution' => $this->forInstitution($domain, $timeHorizon, $landscape),
            'industry'    => $this->forIndustry($domain, $timeHorizon, $landscape),
            'researcher'  => $this->forResearcher($domain, $timeHorizon, $landscape),
            'community'   => $this->forCommunity($domain, $timeHorizon, $landscape),
        };

        return [
            'status'           => 'success',
            'stakeholder_type' => $stakeholderType,
            'domain'           => $domain,
            'region'           => $region,
            'time_horizon'     => $timeHorizon,
            'context_summary'  => $landscape['summary'],
            'recommendations'  => $this->enrichRecommendations($recommendations, $timeHorizon),
            'priority_matrix'  => $this->buildPriorityMatrix($recommendations),
            'api_version'      => 'v1.0-recommendation',
            'data_driven'      => !empty($researchLandscape),
        ];
    }

    // ── Stakeholder-specific recommendation sets ──────────────────────────────

    private function forGovernment(string $domain, string $timeHorizon, array $landscape): array
    {
        $base = [
            [
                'id'              => 'GOV-01',
                'title'           => 'Peningkatan Infrastruktur Riset Nasional',
                'description'     => 'Program komprehensif modernisasi fasilitas riset dan infrastruktur digital di perguruan tinggi dan lembaga riset nasional.',
                'priority'        => 'high',
                'category'        => 'infrastructure',
                'target_sdgs'     => ['SDG4', 'SDG9', 'SDG17'],
                'expected_impact' => [
                    'research_capacity_increase'       => '40%',
                    'international_collaboration_growth' => '60%',
                    'publication_quality_improvement'  => '35%',
                ],
                'key_activities'  => [
                    'Modernisasi laboratorium dan peralatan riset',
                    'Perluasan perpustakaan digital nasional',
                    'Pembangunan pusat komputasi berperforma tinggi',
                    'Platform kolaborasi riset lintas institusi',
                    'Pengembangan kemitraan internasional',
                ],
            ],
            [
                'id'              => 'GOV-02',
                'title'           => 'Program Talenta Riset Nasional',
                'description'     => 'Inisiatif strategis untuk mengembangkan dan mempertahankan peneliti terbaik di bidang prioritas nasional.',
                'priority'        => 'high',
                'category'        => 'human_resources',
                'target_sdgs'     => ['SDG4', 'SDG8', 'SDG10'],
                'expected_impact' => [
                    'phd_graduation_increase'         => '50%',
                    'researcher_retention_rate'       => '75%',
                    'international_researcher_inflow' => '25%',
                ],
                'key_activities'  => [
                    'Perluasan beasiswa riset nasional',
                    'Program post-doctoral fellowship',
                    'Program pertukaran peneliti internasional',
                    'Insentif kemitraan industri-akademia',
                    'Pengembangan jalur karier peneliti',
                ],
            ],
            [
                'id'              => 'GOV-03',
                'title'           => 'Akselerasi Komersialisasi Riset',
                'description'     => 'Kerangka kebijakan untuk meningkatkan transfer teknologi dan komersialisasi hasil riset ke sektor industri.',
                'priority'        => 'medium',
                'category'        => 'innovation',
                'target_sdgs'     => ['SDG8', 'SDG9', 'SDG17'],
                'expected_impact' => [
                    'patent_applications_increase'    => '80%',
                    'startup_formation_rate'          => '60%',
                    'industry_collaboration_growth'   => '45%',
                ],
                'key_activities'  => [
                    'Penguatan Technology Transfer Office (TTO)',
                    'Reformasi kebijakan hak kekayaan intelektual',
                    'Dukungan inkubasi startup berbasis riset',
                    'Platform matching industri-akademia',
                    'Insentif komersialisasi riset',
                ],
            ],
        ];

        // Add SDG-specific recommendation if domain is sdg_achievement
        if ($domain === 'sdg_achievement' || $domain === 'general') {
            $weakSdgs = $landscape['weak_sdgs'] ?? ['SDG14', 'SDG15'];
            $base[] = [
                'id'              => 'GOV-04',
                'title'           => 'Penguatan Riset SDG Prioritas',
                'description'     => "Program khusus untuk meningkatkan kontribusi riset nasional terhadap SDG yang masih rendah: " . implode(', ', $weakSdgs),
                'priority'        => 'high',
                'category'        => 'sdg_focus',
                'target_sdgs'     => $weakSdgs,
                'expected_impact' => [
                    'sdg_coverage_increase' => '30%',
                    'targeted_publications' => '200+ per tahun',
                ],
                'key_activities'  => [
                    'Dana riset khusus untuk SDG yang lemah',
                    'Kolaborasi dengan lembaga internasional SDG',
                    'Program studi tematik SDG di universitas',
                ],
            ];
        }

        return $base;
    }

    private function forInstitution(string $domain, string $timeHorizon, array $landscape): array
    {
        return [
            [
                'id'              => 'INST-01',
                'title'           => 'Strategi Kolaborasi Riset Antar-Institusi',
                'description'     => 'Pengembangan ekosistem kolaborasi riset dengan institusi dalam dan luar negeri untuk meningkatkan kualitas dan dampak penelitian.',
                'priority'        => 'high',
                'category'        => 'collaboration',
                'target_sdgs'     => ['SDG4', 'SDG17'],
                'key_activities'  => [
                    'Identifikasi mitra potensial berdasarkan kesamaan bidang SDG',
                    'MoU riset bersama dengan institusi tier-1 internasional',
                    'Program joint supervision mahasiswa doktoral',
                    'Publikasi bersama di jurnal Q1/Q2',
                    'Partisipasi dalam konsorsium riset internasional',
                ],
            ],
            [
                'id'              => 'INST-02',
                'title'           => 'Diversifikasi Sumber Pendanaan Riset',
                'description'     => 'Pengembangan portofolio pendanaan riset melalui berbagai saluran untuk mengurangi ketergantungan pada satu sumber.',
                'priority'        => 'high',
                'category'        => 'funding',
                'key_activities'  => [
                    'Pengajuan hibah riset internasional (EU Horizon, NSF)',
                    'Kemitraan riset dengan industri (kontrak riset)',
                    'Endowment fund untuk riset prioritas',
                    'Revenue sharing dari komersialisasi HKI',
                ],
            ],
            [
                'id'              => 'INST-03',
                'title'           => 'Pengembangan Kapasitas SDG Analysis',
                'description'     => 'Membangun kemampuan institusi dalam menganalisis dan melaporkan kontribusi riset terhadap SDGs.',
                'priority'        => 'medium',
                'category'        => 'capacity',
                'target_sdgs'     => ['SDG4', 'SDG17'],
                'key_activities'  => [
                    'Integrasi Wizdam Sikola untuk tracking kontribusi SDG',
                    'Pelatihan peneliti dalam SDG-aligned research design',
                    'SDG impact reporting tahunan',
                    'SDG research cluster di setiap bidang ilmu',
                ],
            ],
        ];
    }

    private function forIndustry(string $domain, string $timeHorizon, array $landscape): array
    {
        return [
            [
                'id'              => 'IND-01',
                'title'           => 'Kemitraan R&D dengan Perguruan Tinggi',
                'description'     => 'Membangun ekosistem inovasi melalui kemitraan riset dan pengembangan yang strategis dengan universitas riset.',
                'priority'        => 'high',
                'category'        => 'partnership',
                'target_sdgs'     => ['SDG8', 'SDG9'],
                'key_activities'  => [
                    'Kontrak riset tematik dengan universitas mitra',
                    'Program magang peneliti industri di akademia',
                    'Co-authorship untuk publikasi inovasi',
                    'Sponsorship kursi peneliti (endowed chair)',
                    'Akses preferensial ke hasil riset dan HKI',
                ],
            ],
            [
                'id'              => 'IND-02',
                'title'           => 'Strategi Keberlanjutan Berbasis Riset',
                'description'     => 'Mengintegrasikan temuan riset SDG ke dalam strategi bisnis untuk meningkatkan dampak sosial dan daya saing.',
                'priority'        => 'medium',
                'category'        => 'sustainability',
                'target_sdgs'     => ['SDG12', 'SDG13', 'SDG9'],
                'key_activities'  => [
                    'Pemetaan rantai nilai terhadap SDG targets',
                    'R&D produk/layanan ramah lingkungan',
                    'Laporan keberlanjutan berbasis data riset',
                    'Investasi di startup deep-tech SDG',
                ],
            ],
        ];
    }

    private function forResearcher(string $domain, string $timeHorizon, array $landscape): array
    {
        return [
            [
                'id'              => 'RES-01',
                'title'           => 'Penguatan Profil Riset SDG',
                'description'     => 'Strategi untuk meningkatkan visibilitas dan dampak kontribusi riset terhadap SDGs secara terukur.',
                'priority'        => 'high',
                'category'        => 'profile',
                'key_activities'  => [
                    'Sinkronisasi ORCID, Scopus ID, dan SINTA secara berkala',
                    'Penulisan abstrak yang mengandung kata kunci SDG relevan',
                    'Publikasi di jurnal open access untuk jangkauan lebih luas',
                    'Partisipasi aktif di jaringan riset SDG internasional',
                    'Penggunaan Wizdam Sikola untuk monitoring impact score',
                ],
            ],
            [
                'id'              => 'RES-02',
                'title'           => 'Strategi Kolaborasi untuk Peningkatan H-Index',
                'description'     => 'Pendekatan sistematis untuk membangun jaringan kolaborasi yang meningkatkan sitasi dan h-index.',
                'priority'        => 'medium',
                'category'        => 'collaboration',
                'key_activities'  => [
                    'Kolaborasi dengan peneliti h-index tinggi di bidang yang sama',
                    'Review artikel jurnal bereputasi untuk membangun reputasi',
                    'Aktif di konferensi internasional tier-1',
                    'Membangun preprint di arXiv/SSRN sebelum publikasi formal',
                ],
            ],
        ];
    }

    private function forCommunity(string $domain, string $timeHorizon, array $landscape): array
    {
        return [
            [
                'id'              => 'COM-01',
                'title'           => 'Akses Terbuka ke Pengetahuan Riset',
                'description'     => 'Mendorong dan memfasilitasi akses komunitas terhadap hasil riset untuk meningkatkan dampak sosial.',
                'priority'        => 'high',
                'category'        => 'access',
                'target_sdgs'     => ['SDG4', 'SDG10', 'SDG16'],
                'key_activities'  => [
                    'Repositori publikasi open access nasional',
                    'Terjemahan hasil riset ke bahasa lokal',
                    'Science communication untuk masyarakat umum',
                    'Program citizen science untuk melibatkan komunitas',
                ],
            ],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildLandscape(array $supplied, string $region): array
    {
        // Use data from Wizdam Sikola DB if provided, else use defaults
        return [
            'total_researchers'      => $supplied['total_researchers'] ?? 0,
            'total_institutions'     => $supplied['total_institutions'] ?? 0,
            'total_publications'     => $supplied['total_publications'] ?? 0,
            'avg_h_index'            => $supplied['avg_h_index'] ?? 0,
            'strong_sdgs'            => $supplied['strong_sdgs'] ?? ['SDG3', 'SDG4', 'SDG9'],
            'weak_sdgs'              => $supplied['weak_sdgs'] ?? ['SDG14', 'SDG15'],
            'top_fields'             => $supplied['top_fields'] ?? [],
            'international_collab_rate' => $supplied['international_collab_rate'] ?? 0,
            'region'                 => $region,
            'summary'                => [
                'data_driven'      => !empty($supplied),
                'researchers'      => $supplied['total_researchers'] ?? 'N/A',
                'institutions'     => $supplied['total_institutions'] ?? 'N/A',
                'strong_sdgs'      => $supplied['strong_sdgs'] ?? ['SDG3', 'SDG4', 'SDG9'],
                'weak_sdgs'        => $supplied['weak_sdgs'] ?? ['SDG14', 'SDG15'],
                'region'           => $region,
            ],
        ];
    }

    private function enrichRecommendations(array $recommendations, string $timeHorizon): array
    {
        $horizonYears = match ($timeHorizon) {
            'short'  => '1-2 tahun',
            'long'   => '5-10 tahun',
            default  => '3-5 tahun',
        };

        return array_map(function ($rec) use ($horizonYears) {
            $rec['time_horizon']    = $horizonYears;
            $rec['implementation']  = $this->buildImplementationSteps($rec, $horizonYears);
            $rec['success_metrics'] = $this->defineSuccessMetrics($rec);
            return $rec;
        }, $recommendations);
    }

    private function buildImplementationSteps(array $rec, string $horizonYears): array
    {
        $activities = $rec['key_activities'] ?? [];
        $steps      = [];
        $total      = count($activities);

        foreach ($activities as $i => $activity) {
            $phase = $i < $total / 3 ? 'Fase 1 (Persiapan)' : ($i < $total * 2 / 3 ? 'Fase 2 (Implementasi)' : 'Fase 3 (Evaluasi)');
            $steps[] = ['phase' => $phase, 'action' => $activity];
        }

        return ['horizon' => $horizonYears, 'steps' => $steps];
    }

    private function defineSuccessMetrics(array $rec): array
    {
        $base = [
            'tracking_period' => 'Tahunan',
            'review_mechanism' => 'Evaluasi berkala oleh komite teknis',
        ];

        if (!empty($rec['expected_impact'])) {
            $base['targets'] = $rec['expected_impact'];
        }

        return $base;
    }

    private function buildPriorityMatrix(array $recommendations): array
    {
        $high   = array_values(array_filter($recommendations, fn($r) => ($r['priority'] ?? '') === 'high'));
        $medium = array_values(array_filter($recommendations, fn($r) => ($r['priority'] ?? '') === 'medium'));
        $low    = array_values(array_filter($recommendations, fn($r) => ($r['priority'] ?? '') === 'low'));

        return [
            'high_priority'   => array_column($high, 'id'),
            'medium_priority' => array_column($medium, 'id'),
            'low_priority'    => array_column($low, 'id'),
            'total'           => count($recommendations),
        ];
    }

    private function error(int $code, string $message): array
    {
        http_response_code($code);
        return ['status' => 'error', 'code' => $code, 'message' => $message];
    }
}
