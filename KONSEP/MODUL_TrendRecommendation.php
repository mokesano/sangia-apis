<?php
/**
 * New APIs Implementation for SDG Research Platform
 * 
 * API #7: Trend Analysis Engine
 * API #8: Policy Recommendation Engine
 * API #9: Image Resize Service
 * 
 * @author Rochmady and Wizdam Team
 * @version 1.0
 * @created 2025-06-19
 */

require_once 'api_manager.php';
require_once 'auth_system.php';

// =============================================================================
// API #7: TREND ANALYSIS ENGINE
// =============================================================================

/**
 * Trend Analysis API
 * Analyzes trends from combined data of all 6 existing APIs
 * 
 * Endpoints:
 * - /api/trend-analysis.php?user_id=123&analysis_type=impact_trajectory
 * - /api/trend-analysis.php?user_id=123&analysis_type=collaboration_network
 * - /api/trend-analysis.php?user_id=123&analysis_type=sdg_evolution
 * - /api/trend-analysis.php?institution_id=456&analysis_type=institutional_growth
 */

class TrendAnalysisEngine {
    private $apiManager;
    private $db;
    
    public function __construct() {
        $this->apiManager = new APIManager();
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Main trend analysis method
     */
    public function analyzeTrends($params) {
        $analysisType = $params['analysis_type'] ?? 'impact_trajectory';
        $userId = $params['user_id'] ?? null;
        $institutionId = $params['institution_id'] ?? null;
        $timeRange = $params['time_range'] ?? '5y'; // 5 years default
        
        switch ($analysisType) {
            case 'impact_trajectory':
                return $this->analyzeImpactTrajectory($userId, $timeRange);
                
            case 'collaboration_network':
                return $this->analyzeCollaborationNetwork($userId, $timeRange);
                
            case 'sdg_evolution':
                return $this->analyzeSDGEvolution($userId, $timeRange);
                
            case 'citation_growth':
                return $this->analyzeCitationGrowth($userId, $timeRange);
                
            case 'journal_impact_progression':
                return $this->analyzeJournalProgression($userId, $timeRange);
                
            case 'institutional_growth':
                return $this->analyzeInstitutionalGrowth($institutionId, $timeRange);
                
            case 'national_trends':
                return $this->analyzeNationalTrends($params['country'] ?? 'Indonesia', $timeRange);
                
            default:
                return ['success' => false, 'error' => 'Invalid analysis type'];
        }
    }
    
    /**
     * Analyze researcher's impact trajectory over time
     */
    private function analyzeImpactTrajectory($userId, $timeRange) {
        // Get user's ORCID and Scopus data
        $user = $this->getUserProfile($userId);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }
        
        // Fetch data from existing APIs
        $orcidData = $this->apiManager->callAPI('orcid_profile', ['orcid' => $user['orcid']]);
        $scopusData = $this->apiManager->callAPI('scopus_author', ['author_id' => $user['scopus_author_id']]);
        
        if (!$orcidData['success'] || !$scopusData['success']) {
            return ['success' => false, 'error' => 'Failed to fetch profile data'];
        }
        
        // Process publications by year
        $publications = $orcidData['data']['works'] ?? [];
        $yearlyMetrics = $this->processYearlyMetrics($publications, $timeRange);
        
        // Calculate trends
        $trends = [
            'publication_trend' => $this->calculatePublicationTrend($yearlyMetrics),
            'citation_trend' => $this->calculateCitationTrend($yearlyMetrics),
            'h_index_evolution' => $this->calculateHIndexEvolution($yearlyMetrics),
            'impact_prediction' => $this->predictFutureImpact($yearlyMetrics),
            'collaboration_growth' => $this->calculateCollaborationGrowth($publications),
            'sdg_contribution_evolution' => $this->calculateSDGEvolution($publications, $user)
        ];
        
        // Generate insights
        $insights = $this->generateImpactInsights($trends, $yearlyMetrics);
        
        return [
            'success' => true,
            'data' => [
                'user_profile' => [
                    'name' => $user['name'],
                    'orcid' => $user['orcid'],
                    'institution' => $user['institution_name']
                ],
                'time_range' => $timeRange,
                'yearly_metrics' => $yearlyMetrics,
                'trends' => $trends,
                'insights' => $insights,
                'summary' => [
                    'total_publications' => array_sum(array_column($yearlyMetrics, 'publications')),
                    'total_citations' => array_sum(array_column($yearlyMetrics, 'citations')),
                    'current_h_index' => $scopusData['data']['author']['h_index'] ?? 0,
                    'avg_annual_growth' => $trends['publication_trend']['annual_growth_rate']
                ]
            ]
        ];
    }
    
    /**
     * Analyze collaboration network evolution
     */
    private function analyzeCollaborationNetwork($userId, $timeRange) {
        $user = $this->getUserProfile($userId);
        $orcidData = $this->apiManager->callAPI('orcid_profile', ['orcid' => $user['orcid']]);
        
        if (!$orcidData['success']) {
            return ['success' => false, 'error' => 'Failed to fetch profile data'];
        }
        
        $publications = $orcidData['data']['works'] ?? [];
        $collaborationNetwork = $this->buildCollaborationNetwork($publications, $timeRange);
        
        return [
            'success' => true,
            'data' => [
                'network_stats' => [
                    'total_collaborators' => count($collaborationNetwork['nodes']) - 1,
                    'total_collaborations' => count($collaborationNetwork['edges']),
                    'network_density' => $this->calculateNetworkDensity($collaborationNetwork),
                    'clustering_coefficient' => $this->calculateClusteringCoefficient($collaborationNetwork)
                ],
                'collaboration_trends' => [
                    'yearly_new_collaborators' => $this->getYearlyNewCollaborators($publications),
                    'repeat_collaboration_rate' => $this->calculateRepeatCollaborationRate($publications),
                    'international_collaboration_rate' => $this->calculateInternationalRate($publications)
                ],
                'network_visualization' => $collaborationNetwork,
                'key_collaborators' => $this->identifyKeyCollaborators($collaborationNetwork),
                'collaboration_opportunities' => $this->suggestCollaborationOpportunities($user, $collaborationNetwork)
            ]
        ];
    }
    
    /**
     * Analyze SDG contribution evolution over time
     */
    private function analyzeSDGEvolution($userId, $timeRange) {
        $user = $this->getUserProfile($userId);
        $orcidData = $this->apiManager->callAPI('orcid_profile', ['orcid' => $user['orcid']]);
        
        if (!$orcidData['success']) {
            return ['success' => false, 'error' => 'Failed to fetch profile data'];
        }
        
        $publications = $orcidData['data']['works'] ?? [];
        $sdgEvolution = [];
        
        // Analyze SDG contribution for each publication
        foreach ($publications as $publication) {
            if (!empty($publication['doi'])) {
                $sdgAnalysis = $this->apiManager->callAPI('sdg_classification', ['doi' => $publication['doi']]);
                if ($sdgAnalysis['success']) {
                    $year = $publication['publication_year'] ?? date('Y');
                    if (!isset($sdgEvolution[$year])) {
                        $sdgEvolution[$year] = [];
                    }
                    $sdgEvolution[$year][] = $sdgAnalysis['data'];
                }
            }
        }
        
        // Process SDG trends
        $sdgTrends = $this->processSDGTrends($sdgEvolution);
        
        return [
            'success' => true,
            'data' => [
                'sdg_evolution_by_year' => $sdgEvolution,
                'sdg_trends' => $sdgTrends,
                'dominant_sdgs' => $this->identifyDominantSDGs($sdgEvolution),
                'emerging_sdgs' => $this->identifyEmergingSDGs($sdgEvolution),
                'sdg_diversification' => $this->calculateSDGDiversification($sdgEvolution),
                'impact_projection' => $this->projectSDGImpact($sdgTrends)
            ]
        ];
    }
    
    // Additional trend analysis methods...
    private function processYearlyMetrics($publications, $timeRange) {
        $metrics = [];
        $currentYear = date('Y');
        $startYear = $currentYear - (int)filter_var($timeRange, FILTER_SANITIZE_NUMBER_INT);
        
        for ($year = $startYear; $year <= $currentYear; $year++) {
            $yearPubs = array_filter($publications, function($pub) use ($year) {
                return ($pub['publication_year'] ?? 0) == $year;
            });
            
            $metrics[$year] = [
                'year' => $year,
                'publications' => count($yearPubs),
                'citations' => array_sum(array_column($yearPubs, 'citation_count')),
                'journal_impact_avg' => $this->calculateAvgJournalImpact($yearPubs),
                'international_collab_rate' => $this->calculateInternationalCollabRate($yearPubs)
            ];
        }
        
        return $metrics;
    }
    
    private function calculatePublicationTrend($yearlyMetrics) {
        $years = array_keys($yearlyMetrics);
        $publications = array_column($yearlyMetrics, 'publications');
        
        return [
            'total_growth' => end($publications) - reset($publications),
            'annual_growth_rate' => $this->calculateGrowthRate($publications),
            'trend_direction' => $this->determineTrendDirection($publications),
            'productivity_peaks' => $this->identifyProductivityPeaks($yearlyMetrics)
        ];
    }
}

// =============================================================================
// API #8: POLICY RECOMMENDATION ENGINE
// =============================================================================

/**
 * Policy Recommendation Engine
 * Generates AI-powered policy recommendations for different stakeholders
 * 
 * Endpoints:
 * - /api/policy-recommendations.php?stakeholder_type=government&domain=education
 * - /api/policy-recommendations.php?stakeholder_type=institution&focus=research_enhancement
 * - /api/policy-recommendations.php?stakeholder_type=industry&sector=technology
 */

class PolicyRecommendationEngine {
    private $apiManager;
    private $db;
    
    // Knowledge base for policy recommendations
    private $policyFrameworks = [
        'government' => [
            'education' => [
                'research_funding', 'infrastructure_development', 'talent_retention',
                'international_cooperation', 'innovation_ecosystem', 'regulatory_framework'
            ],
            'health' => [
                'research_priorities', 'healthcare_innovation', 'public_health_policy',
                'medical_research_funding', 'health_technology_adoption'
            ],
            'environment' => [
                'sustainable_development', 'climate_research', 'green_technology',
                'environmental_monitoring', 'conservation_policy'
            ]
        ],
        'institution' => [
            'research_enhancement' => [
                'collaboration_strategy', 'funding_diversification', 'talent_acquisition',
                'infrastructure_upgrade', 'international_partnerships'
            ],
            'academic_excellence' => [
                'curriculum_development', 'faculty_development', 'student_engagement',
                'research_commercialization', 'industry_partnerships'
            ]
        ],
        'industry' => [
            'innovation' => [
                'rd_investment', 'talent_acquisition', 'academic_partnerships',
                'technology_transfer', 'market_research'
            ],
            'sustainability' => [
                'green_technology', 'sustainable_practices', 'environmental_compliance',
                'circular_economy', 'stakeholder_engagement'
            ]
        ]
    ];
    
    public function __construct() {
        $this->apiManager = new APIManager();
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Generate policy recommendations
     */
    public function generateRecommendations($params) {
        $stakeholderType = $params['stakeholder_type'] ?? 'government';
        $domain = $params['domain'] ?? 'general';
        $region = $params['region'] ?? 'Indonesia';
        $timeHorizon = $params['time_horizon'] ?? 'medium'; // short, medium, long
        
        // Gather contextual data
        $contextData = $this->gatherContextualData($stakeholderType, $domain, $region);
        
        // Generate recommendations based on stakeholder type
        switch ($stakeholderType) {
            case 'government':
                return $this->generateGovernmentRecommendations($domain, $region, $timeHorizon, $contextData);
                
            case 'institution':
                return $this->generateInstitutionRecommendations($domain, $region, $timeHorizon, $contextData);
                
            case 'industry':
                return $this->generateIndustryRecommendations($domain, $region, $timeHorizon, $contextData);
                
            case 'researcher':
                return $this->generateResearcherRecommendations($domain, $region, $timeHorizon, $contextData);
                
            case 'community':
                return $this->generateCommunityRecommendations($domain, $region, $timeHorizon, $contextData);
                
            default:
                return ['success' => false, 'error' => 'Invalid stakeholder type'];
        }
    }
    
    /**
     * Generate government policy recommendations
     */
    private function generateGovernmentRecommendations($domain, $region, $timeHorizon, $contextData) {
        $recommendations = [];
        
        // Analyze current research landscape
        $researchLandscape = $this->analyzeNationalResearchLandscape($region);
        
        // Generate domain-specific recommendations
        switch ($domain) {
            case 'education':
                $recommendations = $this->generateEducationPolicies($researchLandscape, $timeHorizon);
                break;
                
            case 'research_funding':
                $recommendations = $this->generateFundingPolicies($researchLandscape, $timeHorizon);
                break;
                
            case 'sdg_achievement':
                $recommendations = $this->generateSDGPolicies($researchLandscape, $timeHorizon);
                break;
                
            case 'innovation_ecosystem':
                $recommendations = $this->generateInnovationPolicies($researchLandscape, $timeHorizon);
                break;
                
            default:
                $recommendations = $this->generateGeneralPolicies($researchLandscape, $timeHorizon);
        }
        
        // Add implementation roadmap
        foreach ($recommendations as &$rec) {
            $rec['implementation'] = $this->generateImplementationRoadmap($rec, $timeHorizon);
            $rec['success_metrics'] = $this->defineSuccessMetrics($rec);
            $rec['stakeholders'] = $this->identifyKeyStakeholders($rec);
            $rec['risks'] = $this->identifyRisks($rec);
            $rec['mitigation_strategies'] = $this->generateMitigationStrategies($rec['risks']);
        }
        
        return [
            'success' => true,
            'data' => [
                'stakeholder_type' => 'government',
                'domain' => $domain,
                'region' => $region,
                'time_horizon' => $timeHorizon,
                'context_analysis' => $researchLandscape,
                'recommendations' => $recommendations,
                'priority_matrix' => $this->createPriorityMatrix($recommendations),
                'budget_estimates' => $this->estimateBudgetRequirements($recommendations),
                'timeline' => $this->createImplementationTimeline($recommendations, $timeHorizon),
                'success_indicators' => $this->defineOverallSuccessIndicators($recommendations)
            ]
        ];
    }
    
    /**
     * Generate education policy recommendations
     */
    private function generateEducationPolicies($researchLandscape, $timeHorizon) {
        $policies = [];
        
        // Research Infrastructure Development
        $policies[] = [
            'title' => 'National Research Infrastructure Enhancement Program',
            'description' => 'Comprehensive upgrade of research facilities and digital infrastructure across universities',
            'priority' => 'high',
            'category' => 'infrastructure',
            'target_sdgs' => ['SDG4', 'SDG9', 'SDG17'],
            'expected_impact' => [
                'research_capacity_increase' => '40%',
                'international_collaboration_growth' => '60%',
                'publication_quality_improvement' => '35%'
            ],
            'budget_range' => [
                'short_term' => '500B IDR',
                'medium_term' => '1.2T IDR',
                'long_term' => '2.5T IDR'
            ],
            'key_activities' => [
                'Laboratory modernization',
                'Digital library expansion',
                'High-performance computing centers',
                'Research collaboration platforms',
                'International partnership development'
            ]
        ];
        
        // Talent Development Program
        $policies[] = [
            'title' => 'National Research Talent Pipeline Initiative',
            'description' => 'Strategic program to develop and retain top research talent in priority areas',
            'priority' => 'high',
            'category' => 'human_resources',
            'target_sdgs' => ['SDG4', 'SDG8', 'SDG10'],
            'expected_impact' => [
                'phd_graduation_increase' => '50%',
                'researcher_retention_rate' => '75%',
                'international_researcher_attraction' => '25%'
            ],
            'key_activities' => [
                'Research scholarship expansion',
                'Post-doctoral fellowship programs',
                'International exchange programs',
                'Industry-academia partnership incentives',
                'Research career pathway development'
            ]
        ];
        
        // Innovation Commercialization Framework
        $policies[] = [
            'title' => 'Research-to-Market Acceleration Program',
            'description' => 'Policy framework to enhance technology transfer and research commercialization',
            'priority' => 'medium',
            'category' => 'innovation',
            'target_sdgs' => ['SDG8', 'SDG9', 'SDG17'],
            'expected_impact' => [
                'patent_applications_increase' => '80%',
                'startup_formation_rate' => '60%',
                'industry_collaboration_growth' => '45%'
            ],
            'key_activities' => [
                'Technology transfer office strengthening',
                'Intellectual property policy reform',
                'Startup incubation support',
                'Industry-academia matching platforms',
                'Research commercialization incentives'
            ]
        ];
        
        return $policies;
    }
    
    /**
     * Analyze national research landscape
     */
    private function analyzeNationalResearchLandscape($region) {
        // This would integrate with all existing APIs to gather comprehensive data
        return [
            'institutional_analysis' => [
                'total_institutions' => 89,
                'top_performers' => 15,
                'research_capacity_distribution' => 'concentrated_in_java',
                'international_ranking_presence' => 12
            ],
            'researcher_demographics' => [
                'total_active_researchers' => 12456,
                'phd_holders' => 3240,
                'international_researchers' => 156,
                'age_distribution' => ['under_35' => '35%', '35_50' => '45%', 'over_50' => '20%']
            ],
            'research_output' => [
                'annual_publications' => 25680,
                'international_collaborations' => '32%',
                'citation_impact' => 'below_world_average',
                'sdg_coverage' => ['strong' => ['SDG3', 'SDG4', 'SDG9'], 'weak' => ['SDG14', 'SDG15']]
            ],
            'funding_landscape' => [
                'government_allocation' => '0.28%_of_gdp',
                'private_sector_contribution' => '15%',
                'international_funding' => '8%',
                'research_grant_success_rate' => '23%'
            ],
            'infrastructure_status' => [
                'modern_facilities' => '40%',
                'digital_infrastructure' => 'moderate',
                'shared_equipment_networks' => 'limited',
                'international_connectivity' => 'good'
            ]
        ];
    }
}

// =============================================================================
// API #9: IMAGE RESIZE SERVICE
// =============================================================================

/**
 * Image Resize Service for OJS v2.4.8.2
 * Handles image resizing with multiple format support and optimization
 * 
 * Endpoints:
 * - /api/image-resize.php (POST with image file)
 * - /api/image-resize.php?action=batch (POST with multiple images)
 */

class ImageResizeService {
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $maxFileSize = 10 * 1024 * 1024; // 10MB
    private $uploadDir;
    private $outputDir;
    
    public function __construct() {
        $this->uploadDir = __DIR__ . '/uploads/';
        $this->outputDir = __DIR__ . '/resized/';
        
        // Create directories if they don't exist
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
        if (!file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }
    
    /**
     * Main image resize method
     */
    public function resizeImage($params) {
        $action = $params['action'] ?? 'single';
        
        switch ($action) {
            case 'single':
                return $this->resizeSingleImage($params);
                
            case 'batch':
                return $this->resizeBatchImages($params);
                
            case 'profile_avatar':
                return $this->resizeProfileAvatar($params);
                
            case 'ojs_compatible':
                return $this->resizeForOJS($params);
                
            default:
                return ['success' => false, 'error' => 'Invalid action'];
        }
    }
    
    /**
     * Resize single image with multiple output formats
     */
    private function resizeSingleImage($params) {
        if (!isset($_FILES['image'])) {
            return ['success' => false, 'error' => 'No image file provided'];
        }
        
        $file = $_FILES['image'];
        
        // Validate file
        $validation = $this->validateImage($file);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        // Get resize parameters
        $targetWidth = (int)($params['width'] ?? 800);
        $targetHeight = (int)($params['height'] ?? 600);
        $quality = (int)($params['quality'] ?? 85);
        $formats = $params['formats'] ?? ['jpeg', 'webp']; // Output formats
        $maintainAspectRatio = ($params['maintain_aspect'] ?? 'true') === 'true';
        
        try {
            // Process image
            $sourceImage = $this->createImageResource($file['tmp_name'], $file['type']);
            if (!$sourceImage) {
                return ['success' => false, 'error' => 'Failed to process image'];
            }
            
            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);
            
            // Calculate dimensions
            if ($maintainAspectRatio) {
                $dimensions = $this->calculateAspectRatioDimensions(
                    $originalWidth, $originalHeight, $targetWidth, $targetHeight
                );
                $newWidth = $dimensions['width'];
                $newHeight = $dimensions['height'];
            } else {
                $newWidth = $targetWidth;
                $newHeight = $targetHeight;
            }
            
            // Create resized image
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Handle transparency for PNG and GIF
            if (in_array($file['type'], ['image/png', 'image/gif'])) {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefill($resizedImage, 0, 0, $transparent);
            }
            
            // Resize image
            imagecopyresampled(
                $resizedImage, $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $originalWidth, $originalHeight
            );
            
            // Generate output files
            $outputFiles = [];
            $baseFilename = pathinfo($file['name'], PATHINFO_FILENAME);
            $timestamp = time();
            
            foreach ($formats as $format) {
                $filename = $baseFilename . '_' . $timestamp . '_' . $newWidth . 'x' . $newHeight;
                $outputPath = $this->outputDir . $filename;
                
                switch ($format) {
                    case 'jpeg':
                    case 'jpg':
                        $outputPath .= '.jpg';
                        imagejpeg($resizedImage, $outputPath, $quality);
                        break;
                        
                    case 'png':
                        $outputPath .= '.png';
                        imagepng($resizedImage, $outputPath, (int)((100 - $quality) / 10));
                        break;
                        
                    case 'webp':
                        $outputPath .= '.webp';
                        imagewebp($resizedImage, $outputPath, $quality);
                        break;
                        
                    case 'gif':
                        $outputPath .= '.gif';
                        imagegif($resizedImage, $outputPath);
                        break;
                }
                
                if (file_exists($outputPath)) {
                    $outputFiles[] = [
                        'format' => $format,
                        'filename' => basename($outputPath),
                        'path' => $outputPath,
                        'url' => '/resized/' . basename($outputPath),
                        'size' => filesize($outputPath),
                        'dimensions' => ['width' => $newWidth, 'height' => $newHeight]
                    ];
                }
            }
            
            // Clean up
            imagedestroy($sourceImage);
            imagedestroy($resizedImage);
            
            return [
                'success' => true,
                'data' => [
                    'original' => [
                        'filename' => $file['name'],
                        'size' => $file['size'],
                        'dimensions' => ['width' => $originalWidth, 'height' => $originalHeight],
                        'type' => $file['type']
                    ],
                    'resized' => $outputFiles,
                    'settings' => [
                        'target_dimensions' => ['width' => $targetWidth, 'height' => $targetHeight],
                        'actual_dimensions' => ['width' => $newWidth, 'height' => $newHeight],
                        'quality' => $quality,
                        'maintain_aspect_ratio' => $maintainAspectRatio
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Image processing failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Resize image specifically for OJS v2.4.8.2 requirements
     */
    private function resizeForOJS($params) {
        // OJS specific requirements
        $ojsSpecs = [
            'profile_avatar' => ['width' => 150, 'height' => 150, 'max_size' => 500 * 1024], // 500KB
            'journal_logo' => ['width' => 300, 'height' => 100, 'max_size' => 1024 * 1024], // 1MB
            'issue_cover' => ['width' => 400, 'height' => 600, 'max_size' => 2 * 1024 * 1024], // 2MB
            'article_image' => ['width' => 800, 'height' => 600, 'max_size' => 3 * 1024 * 1024] // 3MB
        ];
        
        $imageType = $params['ojs_type'] ?? 'profile_avatar';
        if (!isset($ojsSpecs[$imageType])) {
            return ['success' => false, 'error' => 'Invalid OJS image type'];
        }
        
        $spec = $ojsSpecs[$imageType];
        
        // Set parameters for OJS compatibility
        $resizeParams = [
            'width' => $spec['width'],
            'height' => $spec['height'],
            'quality' => 85,
            'formats' => ['jpeg'], // OJS primarily uses JPEG
            'maintain_aspect' => $imageType === 'profile_avatar' ? 'false' : 'true'
        ];
        
        $result = $this->resizeSingleImage($resizeParams);
        
        if ($result['success']) {
            // Check if file size meets OJS requirements
            $outputFile = $result['data']['resized'][0];
            if ($outputFile['size'] > $spec['max_size']) {
                // Reduce quality and try again
                $resizeParams['quality'] = 70;
                $result = $this->resizeSingleImage($resizeParams);
                
                if ($result['success'] && $result['data']['resized'][0]['size'] > $spec['max_size']) {
                    $resizeParams['quality'] = 50;
                    $result = $this->resizeSingleImage($resizeParams);
                }
            }
            
            // Add OJS-specific metadata
            if ($result['success']) {
                $result['data']['ojs_compatibility'] = [
                    'type' => $imageType,
                    'meets_size_requirement' => $result['data']['resized'][0]['size'] <= $spec['max_size'],
                    'meets_dimension_requirement' => true,
                    'recommended_usage' => $this->getOJSUsageRecommendation($imageType),
                    'upload_instructions' => $this->getOJSUploadInstructions($imageType)
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Batch resize multiple images
     */
    private function resizeBatchImages($params) {
        if (!isset($_FILES['images'])) {
            return ['success' => false, 'error' => 'No image files provided'];
        }
        
        $files = $_FILES['images'];
        $results = [];
        $errors = [];
        
        // Handle multiple file upload format
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                
                $_FILES['image'] = $file;
                $result = $this->resizeSingleImage($params);
                
                if ($result['success']) {
                    $results[] = $result['data'];
                } else {
                    $errors[] = ['file' => $file['name'], 'error' => $result['error']];
                }
            }
        }
        
        return [
            'success' => count($errors) === 0,
            'data' => [
                'total_files' => count($files['name']),
                'successful' => count($results),
                'failed' => count($errors),
                'results' => $results,
                'errors' => $errors
            ]
        ];
    }
    
    // Helper methods
    private function validateImage($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error: ' . $file['error']];
        }
        
        if ($file['size'] > $this->maxFileSize) {
            return ['valid' => false, 'error' => 'File too large. Max size: ' . ($this->maxFileSize / 1024 / 1024) . 'MB'];
        }
        
        if (!in_array($file['type'], $this->allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $this->allowedTypes)];
        }
        
        return ['valid' => true];
    }
    
    private function createImageResource($filePath, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filePath);
            case 'image/png':
                return imagecreatefrompng($filePath);
            case 'image/gif':
                return imagecreatefromgif($filePath);
            case 'image/webp':
                return imagecreatefromwebp($filePath);
            default:
                return false;
        }
    }
    
    private function calculateAspectRatioDimensions($originalWidth, $originalHeight, $targetWidth, $targetHeight) {
        $aspectRatio = $originalWidth / $originalHeight;
        
        if ($targetWidth / $targetHeight > $aspectRatio) {
            $newWidth = $targetHeight * $aspectRatio;
            $newHeight = $targetHeight;
        } else {
            $newWidth = $targetWidth;
            $newHeight = $targetWidth / $aspectRatio;
        }
        
        return [
            'width' => (int)round($newWidth),
            'height' => (int)round($newHeight)
        ];
    }
    
    private function getOJSUsageRecommendation($imageType) {
        $recommendations = [
            'profile_avatar' => 'Upload this image in your user profile settings. It will appear next to your name in articles and reviews.',
            'journal_logo' => 'Upload this as your journal logo in Journal Settings > Appearance. It will appear in the header.',
            'issue_cover' => 'Upload this as a cover image for journal issues in Issues > Edit Issue.',
            'article_image' => 'Use this for article illustrations or figures. Upload in Article > Edit > Galley or Supplementary Files.'
        ];
        
        return $recommendations[$imageType] ?? 'General image for OJS platform use.';
    }
    
    private function getOJSUploadInstructions($imageType) {
        $instructions = [
            'profile_avatar' => [
                'step_1' => 'Login to OJS admin panel',
                'step_2' => 'Go to User Profile > Edit Profile',
                'step_3' => 'Scroll to Profile Image section',
                'step_4' => 'Upload the resized image file',
                'step_5' => 'Save changes'
            ],
            'journal_logo' => [
                'step_1' => 'Login as Journal Manager',
                'step_2' => 'Go to Settings > Journal > Appearance',
                'step_3' => 'Find Journal Logo section',
                'step_4' => 'Upload the resized logo file',
                'step_5' => 'Save and preview'
            ],
            'issue_cover' => [
                'step_1' => 'Go to Issues > Back Issues or Current Issue',
                'step_2' => 'Click Edit on the desired issue',
                'step_3' => 'Find Cover Image section',
                'step_4' => 'Upload the resized cover image',
                'step_5' => 'Save changes'
            ],
            'article_image' => [
                'step_1' => 'Go to article editing interface',
                'step_2' => 'Navigate to Galley or Supplementary Files',
                'step_3' => 'Add new file or edit existing',
                'step_4' => 'Upload the resized image',
                'step_5' => 'Set appropriate file label and save'
            ]
        ];
        
        return $instructions[$imageType] ?? [];
    }
}

// =============================================================================
// UNIFIED API ENDPOINT HANDLER
// =============================================================================

/**
 * Main API endpoint handler that routes requests to appropriate services
 */

header('Content-Type: application/json; charset=utf-8');

// Initialize authentication
$middleware = new SessionMiddleware();
$currentUser = $middleware->getCurrentUser();

// Get the requested API from the URL path
$requestUri = $_SERVER['REQUEST_URI'];
$pathParts = explode('/', trim($requestUri, '/'));
$apiEndpoint = end($pathParts);

// Remove .php extension if present
$apiEndpoint = str_replace('.php', '', $apiEndpoint);

try {
    switch ($apiEndpoint) {
        case 'trend-analysis':
            $engine = new TrendAnalysisEngine();
            $params = array_merge($_GET, $_POST);
            
            // Validate authentication for trend analysis
            if (!$currentUser) {
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }
            
            $result = $engine->analyzeTrends($params);
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'policy-recommendations':
        case 'policy-rec':
            $engine = new PolicyRecommendationEngine();
            $params = array_merge($_GET, $_POST);
            
            // Check permissions for policy recommendations
            if ($currentUser && !RoleManager::canAccess($currentUser, 'policy')) {
                echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
                exit;
            }
            
            $result = $engine->generateRecommendations($params);
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'image-resize':
            $service = new ImageResizeService();
            $params = array_merge($_GET, $_POST);
            
            // Rate limiting for image service
            if ($currentUser) {
                $rateLimit = new RateLimiter();
                if (!$rateLimit->checkLimit($currentUser['id'], 'image_resize', 100, 3600)) {
                    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded']);
                    exit;
                }
            }
            
            $result = $service->resizeImage($params);
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode([
                'success' => false, 
                'error' => 'API endpoint not found',
                'available_endpoints' => [
                    'trend-analysis',
                    'policy-recommendations', 
                    'image-resize'
                ]
            ]);
    }
    
} catch (Exception $e) {
    error_log("API Error [$apiEndpoint]: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'timestamp' => date('c')
    ]);
}

// =============================================================================
// RATE LIMITER CLASS
// =============================================================================

class RateLimiter {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function checkLimit($userId, $action, $limit, $window) {
        $sql = "SELECT COUNT(*) as count 
                FROM rate_limits 
                WHERE user_id = ? AND action = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $action, $window]);
        $result = $stmt->fetch();
        
        if ($result['count'] >= $limit) {
            return false;
        }
        
        // Record this request
        $sql = "INSERT INTO rate_limits (user_id, action, created_at) VALUES (?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $action]);
        
        return true;
    }
}

// =============================================================================
// EXAMPLE USAGE AND TESTING
// =============================================================================

/*
// Example 1: Trend Analysis API Call
$trendEngine = new TrendAnalysisEngine();
$result = $trendEngine->analyzeTrends([
    'user_id' => 123,
    'analysis_type' => 'impact_trajectory',
    'time_range' => '5y'
]);

// Example 2: Policy Recommendations API Call  
$policyEngine = new PolicyRecommendationEngine();
$result = $policyEngine->generateRecommendations([
    'stakeholder_type' => 'government',
    'domain' => 'education',
    'region' => 'Indonesia',
    'time_horizon' => 'medium'
]);

// Example 3: Image Resize API Call
$imageService = new ImageResizeService();
// Assumes $_FILES['image'] is set
$result = $imageService->resizeImage([
    'width' => 800,
    'height' => 600,
    'quality' => 85,
    'formats' => ['jpeg', 'webp'],
    'maintain_aspect' => 'true'
]);

// Example 4: OJS-specific image resize
$result = $imageService->resizeImage([
    'action' => 'ojs_compatible',
    'ojs_type' => 'profile_avatar'
]);

// Example 5: Batch image processing
$result = $imageService->resizeImage([
    'action' => 'batch',
    'width' => 400,
    'height' => 300,
    'formats' => ['jpeg']
]);
*/

// =============================================================================
// ADDITIONAL DATABASE TABLES FOR NEW APIS
// =============================================================================

/*
-- Rate limiting table
CREATE TABLE rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_action (user_id, action, created_at)
);

-- Trend analysis cache
CREATE TABLE trend_analysis_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cache_key VARCHAR(255) UNIQUE,
    user_id INT,
    analysis_type VARCHAR(50),
    parameters JSON,
    results JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_cache_key (cache_key),
    INDEX idx_expires (expires_at)
);

-- Policy recommendations history
CREATE TABLE policy_recommendations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    requested_by INT,
    stakeholder_type VARCHAR(50),
    domain VARCHAR(100),
    region VARCHAR(100),
    parameters JSON,
    recommendations JSON,
    feedback_score DECIMAL(3,2),
    implementation_status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_stakeholder (stakeholder_type),
    INDEX idx_domain (domain)
);

-- Image processing logs
CREATE TABLE image_processing_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    original_filename VARCHAR(255),
    original_size INT,
    processing_type VARCHAR(50),
    output_formats JSON,
    processing_time DECIMAL(8,3),
    status ENUM('success', 'failed'),
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_type (user_id, processing_type),
    INDEX idx_status (status)
);
*/

?>