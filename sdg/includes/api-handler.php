<?php
/**
 * Wizdam: API Handler - Sequential Endpoints Logic
 * Enhanced API orchestration untuk sequential analysis workflow
 * 
 * @version 2.4 - Enhanced Modular
 * @author Rochmady and Wizdam Team
 * @license MIT
 * Last update: 2025-06-22
 * 
 * SESUAI PANDUAN: API logic + sequential endpoints untuk enhanced UX
 */

// ==============================================
// SEQUENTIAL API ORCHESTRATION - CORE ENHANCEMENT
// ==============================================

/**
 * Handle sequential analysis request
 * Transform "spinner of death" menjadi engaging progressive loading
 */
function handleSequentialAnalysis($input, $type, $force_refresh = false) {
    $session_id = initializeSequentialSession($input, $type);
    
    try {
        // STEP 1: Quick researcher profile (5 seconds max)
        updateSequentialSession($session_id, [
            'status' => 'fetching_researcher_info',
            'progress' => 10,
            'current_step' => 'Getting researcher information...'
        ]);
        
        $researcher_info = handleResearcherInfoRequest($input);
        
        if ($researcher_info['status'] !== 'success') {
            completeSequentialSession($session_id, $researcher_info);
            return $researcher_info;
        }
        
        // STEP 2: Quick works list (metadata only)
        updateSequentialSession($session_id, [
            'status' => 'fetching_works_list',
            'progress' => 25,
            'current_step' => 'Loading publications list...'
        ]);
        
        $works_list = handleWorksListRequest($input);
        
        if ($works_list['status'] !== 'success') {
            completeSequentialSession($session_id, $works_list);
            return $works_list;
        }
        
        $total_works = count($works_list['data']['works']);
        
        // STEP 3: Sequential analysis per work
        updateSequentialSession($session_id, [
            'status' => 'analyzing_works',
            'progress' => 30,
            'current_step' => 'Starting analysis...',
            'total_works' => $total_works,
            'current_work' => 0
        ]);
        
        $analyzed_works = [];
        $batch_size = getConfig('BATCH_SIZE', 5);
        
        for ($i = 0; $i < $total_works; $i += $batch_size) {
            $batch_end = min($i + $batch_size, $total_works);
            
            // Process batch
            for ($j = $i; $j < $batch_end; $j++) {
                $work_analysis = handleSingleWorkAnalysis($input, $j);
                
                if ($work_analysis['status'] === 'success') {
                    $analyzed_works[] = $work_analysis['data'];
                }
                
                // Update progress
                $progress = 30 + (($j + 1) / $total_works) * 60; // 30% to 90%
                $current_work_title = $works_list['data']['works'][$j]['title'] ?? 'Unknown Work';
                
                updateSequentialSession($session_id, [
                    'progress' => round($progress),
                    'current_work' => $j + 1,
                    'current_step' => "Analyzing: " . truncateText($current_work_title, 50)
                ]);
                
                // Prevent timeout with small delay
                if ($j % $batch_size === 0 && $j > 0) {
                    usleep(100000); // 0.1 second delay between batches
                }
            }
        }
        
        // STEP 4: Generate final analysis summary
        updateSequentialSession($session_id, [
            'status' => 'generating_summary',
            'progress' => 95,
            'current_step' => 'Generating analysis summary...'
        ]);
        
        $final_results = generateSequentialAnalysisSummary($researcher_info['data'], $analyzed_works);
        
        // STEP 5: Complete
        updateSequentialSession($session_id, [
            'status' => 'completed',
            'progress' => 100,
            'current_step' => 'Analysis complete!'
        ]);
        
        completeSequentialSession($session_id, $final_results);
        
        return $final_results;
        
    } catch (Exception $e) {
        updateSequentialSession($session_id, [
            'status' => 'error',
            'error_message' => $e->getMessage()
        ]);
        
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'session_id' => $session_id,
            'timestamp' => date('c')
        ];
    }
}

// ==============================================
// ENHANCED API ENDPOINT HANDLERS
// ==============================================

/**
 * Handle researcher info request (quick)
 * Sesuai panduan: Quick researcher profile
 */
function handleResearcherInfoRequest($orcid) {
    try {
        // Validate input
        if (!validateOrcid($orcid)) {
            throw new Exception('Invalid ORCID format', 400);
        }
        
        // Check cache first
        $cache_key = "researcher_info_{$orcid}";
        $cached_result = getSequentialCache($cache_key);
        
        if ($cached_result !== false) {
            return [
                'status' => 'success',
                'data' => $cached_result,
                'from_cache' => true,
                'timestamp' => date('c')
            ];
        }
        
        // Fetch from API
        $researcher_info = fetchResearcherProfile($orcid);
        
        if (empty($researcher_info) || isset($researcher_info['error'])) {
            throw new Exception('Failed to fetch researcher information', 500);
        }
        
        // Save to cache
        saveSequentialCache($cache_key, $researcher_info);
        
        return [
            'status' => 'success',
            'data' => $researcher_info,
            'from_cache' => false,
            'timestamp' => date('c')
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'code' => $e->getCode() ?: 500,
            'timestamp' => date('c')
        ];
    }
}

/**
 * Handle works list request (metadata only)
 * Sesuai panduan: List of works (metadata only)
 */
function handleWorksListRequest($orcid) {
    try {
        // Validate input
        if (!validateOrcid($orcid)) {
            throw new Exception('Invalid ORCID format', 400);
        }
        
        // Check cache first
        $cache_key = "works_list_{$orcid}";
        $cached_result = getSequentialCache($cache_key);
        
        if ($cached_result !== false) {
            return [
                'status' => 'success',
                'data' => [
                    'total_works' => count($cached_result),
                    'works' => $cached_result
                ],
                'from_cache' => true,
                'timestamp' => date('c')
            ];
        }
        
        // Fetch from API
        $works_list = fetchWorksMetadata($orcid);
        
        if (empty($works_list)) {
            throw new Exception('No works found for this ORCID', 404);
        }
        
        // Save to cache
        saveSequentialCache($cache_key, $works_list);
        
        return [
            'status' => 'success',
            'data' => [
                'total_works' => count($works_list),
                'works' => $works_list
            ],
            'from_cache' => false,
            'timestamp' => date('c')
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'code' => $e->getCode() ?: 500,
            'timestamp' => date('c')
        ];
    }
}

/**
 * Handle single work analysis
 * Sesuai panduan: Analyze single work untuk sequential
 */
function handleSingleWorkAnalysis($orcid, $work_index) {
    try {
        // Validate input
        if (!validateOrcid($orcid)) {
            throw new Exception('Invalid ORCID format', 400);
        }
        
        if (!is_numeric($work_index) || $work_index < 0) {
            throw new Exception('Invalid work index', 400);
        }
        
        // Check cache first
        $cache_key = "work_analysis_{$orcid}_{$work_index}";
        $cached_result = getSequentialCache($cache_key);
        
        if ($cached_result !== false) {
            return [
                'status' => 'success',
                'data' => $cached_result,
                'from_cache' => true,
                'timestamp' => date('c')
            ];
        }
        
        // Fetch from API
        $work_analysis = getSingleWorkAnalysis($orcid, $work_index);
        
        if (empty($work_analysis)) {
            throw new Exception("Work not found at index {$work_index}", 404);
        }
        
        // Save to cache
        saveSequentialCache($cache_key, $work_analysis);
        
        return [
            'status' => 'success',
            'data' => $work_analysis,
            'from_cache' => false,
            'timestamp' => date('c')
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'code' => $e->getCode() ?: 500,
            'work_index' => $work_index,
            'timestamp' => date('c')
        ];
    }
}

/**
 * Handle single article analysis (DOI)
 * Sesuai panduan: DOI analysis endpoint
 */
function handleSingleArticleAnalysis($doi) {
    try {
        // Validate input
        if (!validateDoi($doi)) {
            throw new Exception('Invalid DOI format', 400);
        }
        
        // Clean DOI
        $clean_doi = cleanInput($doi, 'doi');
        
        // Check cache first
        $cache_key = "article_analysis_" . md5($clean_doi);
        $cached_result = getSequentialCache($cache_key);
        
        if ($cached_result !== false) {
            return [
                'status' => 'success',
                'data' => $cached_result,
                'from_cache' => true,
                'timestamp' => date('c')
            ];
        }
        
        // Fetch from API
        $article_analysis = getSingleArticleAnalysis($clean_doi);
        
        if (empty($article_analysis)) {
            throw new Exception('Article not found or analysis failed', 404);
        }
        
        // Save to cache
        saveSequentialCache($cache_key, $article_analysis);
        
        return [
            'status' => 'success',
            'data' => $article_analysis,
            'from_cache' => false,
            'timestamp' => date('c')
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'code' => $e->getCode() ?: 500,
            'doi' => $doi,
            'timestamp' => date('c')
        ];
    }
}

/**
 * Handle analysis status request
 * Sesuai panduan: Progress tracking
 */
function handleAnalysisStatusRequest($session_id) {
    try {
        if (empty($session_id)) {
            throw new Exception('Session ID required', 400);
        }
        
        $session_data = getSequentialProgress($session_id);
        
        if (!$session_data) {
            throw new Exception('Session not found or expired', 404);
        }
        
        return [
            'status' => 'success',
            'data' => $session_data,
            'timestamp' => date('c')
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'code' => $e->getCode() ?: 500,
            'timestamp' => date('c')
        ];
    }
}

/**
 * Handle legacy analysis request (fallback)
 * Sesuai panduan: Original full analysis (fallback)
 */
function handleLegacyAnalysisRequest($input, $type, $force_refresh = false) {
    try {
        // This is fallback ke original API behavior
        $api_result = fetchAnalysisFromAPI($input, $force_refresh);
        
        if ($api_result['status'] !== 'success') {
            throw new Exception($api_result['message'], 500);
        }
        
        return [
            'status' => 'success',
            'data' => $api_result,
            'analysis_mode' => 'legacy_full',
            'timestamp' => date('c')
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'code' => $e->getCode() ?: 500,
            'timestamp' => date('c')
        ];
    }
}

// ==============================================
// ENHANCED ERROR HANDLING
// ==============================================

/**
 * Standardize API error response
 */
function formatApiError($exception, $context = []) {
    $error_response = [
        'status' => 'error',
        'message' => $exception->getMessage(),
        'code' => $exception->getCode() ?: 500,
        'timestamp' => date('c'),
        'api_version' => 'v2.4-modular'
    ];
    
    // Add context if provided
    if (!empty($context)) {
        $error_response['context'] = $context;
    }
    
    // Add debug info if in development
    if (getConfig('DEBUG_MODE', false)) {
        $error_response['debug'] = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
    }
    
    return $error_response;
}

/**
 * Handle API timeout gracefully
 */
function handleApiTimeout($session_id, $current_progress) {
    updateSequentialSession($session_id, [
        'status' => 'timeout',
        'progress' => $current_progress,
        'error_message' => 'Analysis taking longer than expected. Please try again or use legacy mode.',
        'suggested_action' => 'retry_or_legacy'
    ]);
    
    return [
        'status' => 'timeout',
        'message' => 'Analysis taking longer than expected',
        'session_id' => $session_id,
        'progress' => $current_progress,
        'suggested_action' => 'retry_or_legacy',
        'timestamp' => date('c')
    ];
}

// ==============================================
// API RESPONSE PROCESSING
// ==============================================

/**
 * Process and clean API responses
 */
function processApiResponse($raw_response, $expected_type = 'json') {
    if ($expected_type === 'json') {
        if (is_string($raw_response)) {
            $decoded = json_decode($raw_response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }
            
            return $decoded;
        }
        
        return $raw_response;
    }
    
    return $raw_response;
}

/**
 * Validate API response structure
 */
function validateApiResponse($response, $required_fields = []) {
    if (!is_array($response)) {
        throw new Exception('API response must be an array');
    }
    
    foreach ($required_fields as $field) {
        if (!isset($response[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    
    return true;
}

// ==============================================
// PROGRESS TRACKING INTEGRATION
// ==============================================

/**
 * Generate sequential analysis summary
 */
function generateSequentialAnalysisSummary($researcher_info, $analyzed_works) {
    $summary = [
        'researcher_info' => $researcher_info,
        'total_works' => count($analyzed_works),
        'successfully_analyzed' => 0,
        'sdg_distribution' => [],
        'top_contributions' => [],
        'analysis_metadata' => [
            'analysis_mode' => 'sequential',
            'completion_time' => date('c'),
            'version' => 'v2.4-modular'
        ]
    ];
    
    foreach ($analyzed_works as $work) {
        if (isset($work['status']) && $work['status'] === 'success') {
            $summary['successfully_analyzed']++;
            
            // Process SDG data if available
            if (isset($work['sdgs']) && is_array($work['sdgs'])) {
                foreach ($work['sdgs'] as $sdg) {
                    if (!isset($summary['sdg_distribution'][$sdg])) {
                        $summary['sdg_distribution'][$sdg] = 0;
                    }
                    $summary['sdg_distribution'][$sdg]++;
                }
            }
        }
    }
    
    // Sort SDG distribution
    if (!empty($summary['sdg_distribution'])) {
        arsort($summary['sdg_distribution']);
        $summary['top_contributions'] = array_slice($summary['sdg_distribution'], 0, 5, true);
    }
    
    return [
        'status' => 'success',
        'data' => [
            'summary' => $summary,
            'works' => $analyzed_works
        ],
        'timestamp' => date('c')
    ];
}

/**
 * Get real-time progress updates
 */
function getProgressUpdate($session_id) {
    $session_data = getSequentialProgress($session_id);
    
    if (!$session_data) {
        return [
            'status' => 'error',
            'message' => 'Session not found'
        ];
    }
    
    return [
        'status' => 'success',
        'progress' => $session_data['progress'] ?? 0,
        'current_step' => $session_data['current_step'] ?? 'Unknown',
        'current_work' => $session_data['current_work'] ?? 0,
        'total_works' => $session_data['total_works'] ?? 0,
        'session_status' => $session_data['status'] ?? 'unknown',
        'timestamp' => date('c')
    ];
}

// ==============================================
// CACHE INTEGRATION
// ==============================================

/**
 * Intelligent cache management untuk sequential analysis
 */
function manageSequentialCache() {
    // Clean expired cache
    cleanSequentialCache();
    
    // Get cache statistics
    $cache_stats = [
        'total_cached_items' => 0,
        'cache_size_mb' => 0,
        'oldest_cache' => null,
        'newest_cache' => null
    ];
    
    $cache_dir = __DIR__ . '/../cache/sequential';
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '/*');
        $cache_stats['total_cached_items'] = count($files);
        
        $total_size = 0;
        $oldest_time = PHP_INT_MAX;
        $newest_time = 0;
        
        foreach ($files as $file) {
            $size = filesize($file);
            $time = filemtime($file);
            
            $total_size += $size;
            
            if ($time < $oldest_time) {
                $oldest_time = $time;
                $cache_stats['oldest_cache'] = date('c', $time);
            }
            
            if ($time > $newest_time) {
                $newest_time = $time;
                $cache_stats['newest_cache'] = date('c', $time);
            }
        }
        
        $cache_stats['cache_size_mb'] = round($total_size / (1024 * 1024), 2);
    }
    
    return $cache_stats;
}

// ==============================================
// API ORCHESTRATION UTILITIES
// ==============================================

/**
 * Route API request based on type and parameters
 */
function routeApiRequest($params) {
    $request_type = $params['type'] ?? 'unknown';
    
    switch ($request_type) {
        case 'researcher_info':
            return handleResearcherInfoRequest($params['orcid'] ?? '');
            
        case 'works_list':
            return handleWorksListRequest($params['orcid'] ?? '');
            
        case 'single_work':
            return handleSingleWorkAnalysis($params['orcid'] ?? '', $params['work_index'] ?? 0);
            
        case 'single_article':
            return handleSingleArticleAnalysis($params['doi'] ?? '');
            
        case 'analysis_status':
            return handleAnalysisStatusRequest($params['session_id'] ?? '');
            
        case 'sequential_analysis':
            return handleSequentialAnalysis($params['input'] ?? '', $params['input_type'] ?? '', $params['force_refresh'] ?? false);
            
        case 'legacy_analysis':
            return handleLegacyAnalysisRequest($params['input'] ?? '', $params['input_type'] ?? '', $params['force_refresh'] ?? false);
            
        default:
            return [
                'status' => 'error',
                'message' => 'Unknown API request type',
                'code' => 400,
                'timestamp' => date('c')
            ];
    }
}

/**
 * Enhanced API response with metadata
 */
function enhanceApiResponse($response, $metadata = []) {
    if (!isset($response['timestamp'])) {
        $response['timestamp'] = date('c');
    }
    
    if (!isset($response['api_version'])) {
        $response['api_version'] = 'v2.4-modular';
    }
    
    if (!empty($metadata)) {
        $response['metadata'] = $metadata;
    }
    
    return $response;
}

?>