<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\Recommendation;

/**
 * Policy Recommendation Engine — API #8
 *
 * Returns language-agnostic structured data (keys, codes, numbers).
 * All human-readable text (titles, descriptions, activity labels) is intentionally
 * excluded — i18n and display are the responsibility of the UI layer (Sangia Sikola).
 *
 * Each recommendation item carries:
 *   'id'            — stable i18n key (e.g. "GOV-01") for Sangia Sikola translation map
 *   'priority'      — high | medium | low
 *   'category'      — machine-readable category code
 *   'target_sdgs'   — SDG codes array
 *   'activity_keys' — array of snake_case keys → Sangia Sikola maps to localized text
 *   'expected_impact' — key:percentage pairs (language-neutral)
 *
 * Supported stakeholder types: government, institution, industry, researcher, community
 */
class PolicyRecommendationEngine
{
    public function generate(
        string $stakeholderType   = 'government',
        string $domain            = 'general',
        string $timeHorizon       = 'medium',
        string $region            = '',
        array  $researchLandscape = []
    ): array {
        $validTypes = ['government', 'institution', 'industry', 'researcher', 'community'];
        if (!in_array($stakeholderType, $validTypes, true)) {
            return $this->error(400, "Invalid stakeholder_type. Supported: " . implode(', ', $validTypes));
        }

        $landscape = $this->buildLandscape($researchLandscape, $region);

        $recommendations = match ($stakeholderType) {
            'government'  => $this->forGovernment($domain, $landscape),
            'institution' => $this->forInstitution($domain, $landscape),
            'industry'    => $this->forIndustry($domain, $landscape),
            'researcher'  => $this->forResearcher($domain, $landscape),
            'community'   => $this->forCommunity($domain, $landscape),
        };

        return [
            'status'           => 'success',
            'stakeholder_type' => $stakeholderType,
            'domain'           => $domain,
            'region'           => $region,
            'time_horizon_key' => $this->resolveHorizonKey($timeHorizon),
            'context_summary'  => $landscape['summary'],
            'recommendations'  => $this->enrichRecommendations($recommendations, $timeHorizon),
            'priority_matrix'  => $this->buildPriorityMatrix($recommendations),
            'api_version'      => 'v1.0-recommendation',
            'data_driven'      => !empty($researchLandscape),
        ];
    }

    // ── Stakeholder recommendation sets ──────────────────────────────────────

    private function forGovernment(string $domain, array $landscape): array
    {
        $base = [
            [
                'id'              => 'GOV-01',
                'priority'        => 'high',
                'category'        => 'infrastructure',
                'target_sdgs'     => ['SDG4', 'SDG9', 'SDG17'],
                'activity_keys'   => [
                    'modernize_research_labs',
                    'expand_digital_library',
                    'build_hpc_center',
                    'cross_institution_collab_platform',
                    'develop_international_partnerships',
                ],
                'expected_impact' => [
                    'research_capacity_increase'        => '40%',
                    'international_collaboration_growth' => '60%',
                    'publication_quality_improvement'   => '35%',
                ],
            ],
            [
                'id'              => 'GOV-02',
                'priority'        => 'high',
                'category'        => 'human_resources',
                'target_sdgs'     => ['SDG4', 'SDG8', 'SDG10'],
                'activity_keys'   => [
                    'expand_research_scholarships',
                    'postdoctoral_fellowship_program',
                    'international_researcher_exchange',
                    'industry_academia_incentives',
                    'researcher_career_pathway',
                ],
                'expected_impact' => [
                    'phd_graduation_increase'         => '50%',
                    'researcher_retention_rate'       => '75%',
                    'international_researcher_inflow' => '25%',
                ],
            ],
            [
                'id'              => 'GOV-03',
                'priority'        => 'medium',
                'category'        => 'innovation',
                'target_sdgs'     => ['SDG8', 'SDG9', 'SDG17'],
                'activity_keys'   => [
                    'strengthen_technology_transfer_office',
                    'reform_ip_policy',
                    'research_startup_incubation_support',
                    'industry_academia_matching_platform',
                    'research_commercialization_incentives',
                ],
                'expected_impact' => [
                    'patent_applications_increase'  => '80%',
                    'startup_formation_rate'        => '60%',
                    'industry_collaboration_growth' => '45%',
                ],
            ],
        ];

        if ($domain === 'sdg_achievement' || $domain === 'general') {
            $weakSdgs = $landscape['weak_sdgs'] ?? ['SDG14', 'SDG15'];
            $base[] = [
                'id'              => 'GOV-04',
                'priority'        => 'high',
                'category'        => 'sdg_focus',
                'target_sdgs'     => $weakSdgs,
                'activity_keys'   => [
                    'dedicated_sdg_research_funding',
                    'international_sdg_collaboration',
                    'sdg_thematic_study_programs',
                ],
                'expected_impact' => [
                    'sdg_coverage_increase' => '30%',
                    'targeted_publications' => '200+',
                ],
            ];
        }

        return $base;
    }

    private function forInstitution(string $domain, array $landscape): array
    {
        return [
            [
                'id'            => 'INST-01',
                'priority'      => 'high',
                'category'      => 'collaboration',
                'target_sdgs'   => ['SDG4', 'SDG17'],
                'activity_keys' => [
                    'identify_sdg_aligned_partners',
                    'international_research_mou',
                    'joint_doctoral_supervision',
                    'q1_q2_joint_publications',
                    'international_research_consortium',
                ],
            ],
            [
                'id'            => 'INST-02',
                'priority'      => 'high',
                'category'      => 'funding',
                'activity_keys' => [
                    'apply_international_research_grants',
                    'industry_research_contract_partnership',
                    'priority_research_endowment_fund',
                    'ip_revenue_sharing',
                ],
            ],
            [
                'id'            => 'INST-03',
                'priority'      => 'medium',
                'category'      => 'capacity',
                'target_sdgs'   => ['SDG4', 'SDG17'],
                'activity_keys' => [
                    'integrate_sangia_sikola_tracking',
                    'researcher_sdg_aligned_training',
                    'annual_sdg_impact_reporting',
                    'sdg_research_clusters',
                ],
            ],
        ];
    }

    private function forIndustry(string $domain, array $landscape): array
    {
        return [
            [
                'id'            => 'IND-01',
                'priority'      => 'high',
                'category'      => 'partnership',
                'target_sdgs'   => ['SDG8', 'SDG9'],
                'activity_keys' => [
                    'thematic_research_contracts',
                    'industry_researcher_internship',
                    'co_authorship_innovation',
                    'endowed_chair_sponsorship',
                    'preferential_research_ip_access',
                ],
            ],
            [
                'id'            => 'IND-02',
                'priority'      => 'medium',
                'category'      => 'sustainability',
                'target_sdgs'   => ['SDG12', 'SDG13', 'SDG9'],
                'activity_keys' => [
                    'value_chain_sdg_mapping',
                    'eco_product_rd',
                    'research_based_sustainability_reporting',
                    'deep_tech_sdg_investment',
                ],
            ],
        ];
    }

    private function forResearcher(string $domain, array $landscape): array
    {
        return [
            [
                'id'            => 'RES-01',
                'priority'      => 'high',
                'category'      => 'profile',
                'activity_keys' => [
                    'sync_orcid_scopus_sinta',
                    'sdg_keyword_abstract_writing',
                    'open_access_publication',
                    'sdg_research_network_participation',
                    'sangia_sikola_impact_monitoring',
                ],
            ],
            [
                'id'            => 'RES-02',
                'priority'      => 'medium',
                'category'      => 'collaboration',
                'activity_keys' => [
                    'collaborate_high_hindex_researchers',
                    'review_reputable_journals',
                    'tier1_international_conferences',
                    'preprint_arxiv_ssrn',
                ],
            ],
        ];
    }

    private function forCommunity(string $domain, array $landscape): array
    {
        return [
            [
                'id'            => 'COM-01',
                'priority'      => 'high',
                'category'      => 'access',
                'target_sdgs'   => ['SDG4', 'SDG10', 'SDG16'],
                'activity_keys' => [
                    'national_open_access_repository',
                    'translate_research_local_languages',
                    'science_communication',
                    'citizen_science_program',
                ],
            ],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveHorizonKey(string $timeHorizon): string
    {
        return match ($timeHorizon) {
            'short' => 'short_term',
            'long'  => 'long_term',
            default => 'medium_term',
        };
    }

    private function buildLandscape(array $supplied, string $region): array
    {
        return [
            'total_researchers'         => $supplied['total_researchers'] ?? 0,
            'total_institutions'        => $supplied['total_institutions'] ?? 0,
            'total_publications'        => $supplied['total_publications'] ?? 0,
            'avg_h_index'               => $supplied['avg_h_index'] ?? 0,
            'strong_sdgs'               => $supplied['strong_sdgs'] ?? ['SDG3', 'SDG4', 'SDG9'],
            'weak_sdgs'                 => $supplied['weak_sdgs'] ?? ['SDG14', 'SDG15'],
            'top_fields'                => $supplied['top_fields'] ?? [],
            'international_collab_rate' => $supplied['international_collab_rate'] ?? 0,
            'region'                    => $region,
            'summary'                   => [
                'data_driven'  => !empty($supplied),
                'researchers'  => $supplied['total_researchers'] ?? null,
                'institutions' => $supplied['total_institutions'] ?? null,
                'strong_sdgs'  => $supplied['strong_sdgs'] ?? ['SDG3', 'SDG4', 'SDG9'],
                'weak_sdgs'    => $supplied['weak_sdgs'] ?? ['SDG14', 'SDG15'],
                'region'       => $region,
            ],
        ];
    }

    private function enrichRecommendations(array $recommendations, string $timeHorizon): array
    {
        $horizonKey = $this->resolveHorizonKey($timeHorizon);

        return array_map(function ($rec) use ($horizonKey) {
            $rec['time_horizon_key'] = $horizonKey;
            $rec['implementation']   = $this->buildImplementationSteps($rec, $horizonKey);
            $rec['success_metrics']  = $this->defineSuccessMetrics($rec);
            return $rec;
        }, $recommendations);
    }

    private function buildImplementationSteps(array $rec, string $horizonKey): array
    {
        $activityKeys = $rec['activity_keys'] ?? [];
        $steps        = [];
        $total        = count($activityKeys);

        foreach ($activityKeys as $i => $activityKey) {
            $phase   = $i < $total / 3 ? 'phase_1' : ($i < $total * 2 / 3 ? 'phase_2' : 'phase_3');
            $steps[] = ['phase' => $phase, 'activity_key' => $activityKey];
        }

        return ['horizon_key' => $horizonKey, 'steps' => $steps];
    }

    private function defineSuccessMetrics(array $rec): array
    {
        $base = [
            'tracking_period'  => 'annual',
            'review_mechanism' => 'periodic_committee_review',
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
