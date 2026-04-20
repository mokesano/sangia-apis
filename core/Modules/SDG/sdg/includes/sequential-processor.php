<?php
/**
 * Wizdam: Sequential Analysis Processor
 * Transform timeout-prone UX to engaging progressive loading
 * 
 * @version 2.4 - Enhanced Modular
 * @author Rochmady and Wizdam Team
 * @license MIT
 * Last update: 2025-06-22
 * 
 * SESUAI PANDUAN: Transform "Submit → 💀 SPINNER OF DEATH → 💥 TIMEOUT"
 * Menjadi: "Clean Input → Progressive Steps → Real-time Updates → Results"
 */

// ==============================================
// SEQUENTIAL PROCESSING ENGINE - CORE ENHANCEMENT
// ==============================================

/**
 * Main sequential processor class
 * Core engine untuk transformasi UX sesuai panduan
 */
class SequentialProcessor {
    
    private $session_id;
    private $total_works;
    private $current_work;
    private $batch_size;
    private $progress_callback;
    private $error_callback;
    
    public function __construct($session_id = null) {
        $this->session_id = $session_id ?: generateSessionId();
        $this->batch_size = getConfig('BATCH_SIZE', 5);
        $this->total_works = 0;
        $this->current_work = 0;
    }
    
    /**
     * Start sequential analysis process
     * Transform spinner of death → engaging progressive experience
     */
    public function startSequentialAnalysis($input, $type, $options = []) {
        $start_time = microtime(true);
        
        try {
            // Initialize session
            $this->initializeSession($input, $type, $options);
            
            // PHASE 1: Quick researcher info (5 seconds max)
            $this->updateProgress(5, 'Fetching researcher information...');
            $researcher_info = $this->processResearcherInfo($input);
            
            // PHASE 2: Quick works list (metadata only)
            $this->updateProgress(15, 'Loading publications list...');
            $works_list = $this->processWorksList($input);
            $this->total_works = count($works_list);
            
            // PHASE 3: Sequential work analysis (intelligent batching)
            $this->updateProgress(25, 'Starting sequential analysis...');
            $analyzed_works = $this->processWorksSequentially($input, $works_list);
            
            // PHASE 4: Generate comprehensive summary
            $this->updateProgress(95, 'Generating analysis summary...');
            $final_summary = $this->generateFinalSummary($researcher_info, $analyzed_works);
            
            // Complete
            $this->updateProgress(100, 'Analysis complete!');
            $this->completeSession($final_summary);
            
            $processing_time = microtime(true) - $start_time;
            
            return [
                'status' => 'success',
                'session_id' => $this->session_id,
                'data' => $final_summary,
                'processing_time' => round($processing_time, 2),
                'total_works_processed' => count($analyzed_works),
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            $this->handleProcessingError($e);
            
            return [
                'status' => 'error',
                'session_id' => $this->session_id,
                'message' => $e->getMessage(),
                'progress' => $this->getProgressPercentage(),
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Process researcher info (quick step)
     */
    private function processResearcherInfo($input) {
        $start_time = microtime(true);
        
        try {
            $researcher_info = fetchResearcherProfile($input);
            
            if (empty($researcher_info) || isset($researcher_info['error'])) {
                throw new Exception('Failed to fetch researcher information');
            }
            
            $processing_time = microtime(true) - $start_time;
            $this->logProcessingStep('researcher_info', $processing_time);
            
            return $researcher_info;
            
        } catch (Exception $e) {
            throw new Exception('Researcher info processing failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Process works list (metadata only)
     */
    private function processWorksList($input) {
        $start_time = microtime(true);
        
        try {
            $works_list = fetchWorksMetadata($input);
            
            if (empty($works_list)) {
                throw new Exception('No works found for this researcher');
            }
            
            $processing_time = microtime(true) - $start_time;
            $this->logProcessingStep('works_list', $processing_time, count($works_list));
            
            return $works_list;
            
        } catch (Exception $e) {
            throw new Exception('Works list processing failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Process works sequentially (core transformation)
     * Transform timeout → engaging progressive loading
     */
    private function processWorksSequentially($input, $works_list) {
        $analyzed_works = [];
        $failed_works = [];
        $batch_start_time = microtime(true);
        
        // Process dalam batches untuk prevent timeout
        for ($i = 0; $i < $this->total_works; $i += $this->batch_size) {
            $batch_end = min($i + $this->batch_size, $this->total_works);
            
            // Process current batch
            for ($work_index = $i; $work_index < $batch_end; $work_index++) {
                $this->current_work = $work_index + 1;
                $work_data = $works_list[$work_index];
                
                // Update progress dengan detail work
                $progress = 25 + (($this->current_work / $this->total_works) * 65); // 25% to 90%
                $work_title = truncateText($work_data['title'] ?? 'Unknown Work', 50);
                $this->updateProgress($progress, "Analyzing ({$this->current_work}/{$this->total_works}): {$work_title}");
                
                try {
                    $work_analysis = $this->analyzeWork($input, $work_index);
                    
                    if ($work_analysis && $work_analysis['status'] === 'success') {
                        $analyzed_works[] = $work_analysis;
                    } else {
                        $failed_works[] = [
                            'index' => $work_index,
                            'title' => $work_data['title'] ?? 'Unknown',
                            'error' => 'Analysis failed'
                        ];
                    }
                    
                } catch (Exception $e) {
                    $failed_works[] = [
                        'index' => $work_index,
                        'title' => $work_data['title'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                }
                
                // Real-time progress update
                $this->sendProgressUpdate();
                
                // Micro-delay untuk prevent overwhelming
                usleep(50000); // 0.05 seconds
            }
            
            // Batch completion delay untuk prevent timeout
            $batch_time = microtime(true) - $batch_start_time;
            if ($batch_time > getConfig('ANALYSIS_TIMEOUT', 30)) {
                $this->logWarning("Batch processing approaching timeout limit");
                
                // Implement smart timeout prevention
                if ($i + $this->batch_size < $this->total_works) {
                    usleep(200000); // 0.2 second delay between batches
                    $batch_start_time = microtime(true);
                }
            }
        }
        
        // Log batch processing results
        $this->logProcessingStep('sequential_analysis', microtime(true) - $batch_start_time, [
            'total_works' => $this->total_works,
            'analyzed_successfully' => count($analyzed_works),
            'failed_works' => count($failed_works)
        ]);
        
        return $analyzed_works;
    }
    
    /**
     * Analyze single work with enhanced error handling
     */
    private function analyzeWork($input, $work_index) {
        $max_retries = 2;
        $retry_count = 0;
        
        while ($retry_count < $max_retries) {
            try {
                $work_analysis = getSingleWorkAnalysis($input, $work_index);
                
                if ($work_analysis) {
                    return [
                        'status' => 'success',
                        'data' => $work_analysis,
                        'work_index' => $work_index,
                        'processed_at' => date('c')
                    ];
                }
                
                throw new Exception('Empty analysis result');
                
            } catch (Exception $e) {
                $retry_count++;
                
                if ($retry_count >= $max_retries) {
                    $this->logError("Work analysis failed after {$max_retries} retries", [
                        'work_index' => $work_index,
                        'error' => $e->getMessage()
                    ]);
                    
                    return [
                        'status' => 'error',
                        'work_index' => $work_index,
                        'error' => $e->getMessage(),
                        'retries' => $retry_count
                    ];
                }
                
                // Short delay before retry
                usleep(100000); // 0.1 second
            }
        }
        
        return null;
    }
    
    /**
     * Generate final comprehensive summary
     */
    private function generateFinalSummary($researcher_info, $analyzed_works) {
        $summary_start = microtime(true);
        
        // Basic statistics
        $stats = [
            'total_works_analyzed' => count($analyzed_works),
            'successful_analyses' => 0,
            'failed_analyses' => 0,
            'sdg_contributions' => [],
            'top_sdgs' => [],
            'contribution_types' => []
        ];
        
        // Process analyzed works
        foreach ($analyzed_works as $work) {
            if ($work['status'] === 'success' && isset($work['data'])) {
                $stats['successful_analyses']++;
                $work_data = $work['data'];
                
                // Count SDG contributions
                if (isset($work_data['sdgs']) && is_array($work_data['sdgs'])) {
                    foreach ($work_data['sdgs'] as $sdg) {
                        if (!isset($stats['sdg_contributions'][$sdg])) {
                            $stats['sdg_contributions'][$sdg] = 0;
                        }
                        $stats['sdg_contributions'][$sdg]++;
                    }
                }
                
                // Count contribution types
                if (isset($work_data['contributor_types']) && is_array($work_data['contributor_types'])) {
                    foreach ($work_data['contributor_types'] as $sdg => $type) {
                        if (!isset($stats['contribution_types'][$type])) {
                            $stats['contribution_types'][$type] = 0;
                        }
                        $stats['contribution_types'][$type]++;
                    }
                }
            } else {
                $stats['failed_analyses']++;
            }
        }
        
        // Sort SDG contributions
        if (!empty($stats['sdg_contributions'])) {
            arsort($stats['sdg_contributions']);
            $stats['top_sdgs'] = array_slice($stats['sdg_contributions'], 0, 5, true);
        }
        
        $processing_time = microtime(true) - $summary_start;
        $this->logProcessingStep('final_summary', $processing_time);
        
        return [
            'researcher_info' => $researcher_info,
            'analysis_statistics' => $stats,
            'analyzed_works' => $analyzed_works,
            'processing_metadata' => [
                'session_id' => $this->session_id,
                'processing_mode' => 'sequential',
                'batch_size' => $this->batch_size,
                'total_processing_time' => $processing_time,
                'completed_at' => date('c')
            ]
        ];
    }
}

// ==============================================
// PROGRESS MANAGEMENT - REAL-TIME FEEDBACK
// ==============================================

/**
 * Global sequential processor instance
 */
$GLOBALS['sequential_processor'] = null;

/**
 * Initialize sequential processing
 */
function initializeSequentialProcessing($input, $type, $options = []) {
    global $sequential_processor;
    
    $session_id = $options['session_id'] ?? generateSessionId();
    $sequential_processor = new SequentialProcessor($session_id);
    
    return $sequential_processor->startSequentialAnalysis($input, $type, $options);
}

/**
 * Get real-time progress untuk frontend
 */
function getSequentialProgress($session_id) {
    $session_data = getSequentialProgress($session_id);
    
    if (!$session_data) {
        return [
            'status' => 'not_found',
            'message' => 'Session not found or expired'
        ];
    }
    
    return [
        'status' => 'success',
        'progress' => $session_data['progress'] ?? 0,
        'current_step' => $session_data['current_step'] ?? 'Unknown',
        'current_work' => $session_data['current_work'] ?? 0,
        'total_works' => $session_data['total_works'] ?? 0,
        'session_status' => $session_data['status'] ?? 'unknown',
        'estimated_time_remaining' => calculateEstimatedTimeRemaining($session_data),
        'timestamp' => date('c')
    ];
}

/**
 * Calculate estimated time remaining
 */
function calculateEstimatedTimeRemaining($session_data) {
    if (!isset($session_data['progress']) || $session_data['progress'] <= 0) {
        return null;
    }
    
    $progress = $session_data['progress'];
    $elapsed_time = time() - strtotime($session_data['created_at']);
    
    if ($progress >= 100) {
        return 0;
    }
    
    $rate = $progress / $elapsed_time; // Progress per second
    $remaining_progress = 100 - $progress;
    
    return round($remaining_progress / $rate);
}

// ==============================================
// BATCH OPTIMIZATION - TIMEOUT PREVENTION
// ==============================================

/**
 * Smart batch size calculation
 */
function calculateOptimalBatchSize($total_works, $available_memory = null) {
    $base_batch_size = getConfig('BATCH_SIZE', 5);
    
    // Adjust based on total works
    if ($total_works <= 10) {
        return min($total_works, 3);
    } elseif ($total_works <= 50) {
        return min($total_works, $base_batch_size);
    } else {
        // For large datasets, use smaller batches to prevent timeout
        return min($total_works, max(3, $base_batch_size - 2));
    }
}

/**
 * Timeout prevention mechanism
 */
function preventTimeout($current_processing_time, $max_execution_time = null) {
    $max_time = $max_execution_time ?: getConfig('MAX_EXECUTION_TIME', 300);
    $buffer_time = 30; // 30 seconds buffer
    
    if ($current_processing_time > ($max_time - $buffer_time)) {
        return [
            'should_pause' => true,
            'reason' => 'approaching_timeout',
            'remaining_time' => $max_time - $current_processing_time
        ];
    }
    
    return ['should_pause' => false];
}

/**
 * Memory usage optimization
 */
function optimizeMemoryUsage() {
    $memory_limit = ini_get('memory_limit');
    $current_usage = memory_get_usage(true);
    $peak_usage = memory_get_peak_usage(true);
    
    // Convert memory limit to bytes
    $limit_bytes = convertToBytes($memory_limit);
    $usage_percentage = ($current_usage / $limit_bytes) * 100;
    
    if ($usage_percentage > 80) {
        // Trigger garbage collection
        gc_collect_cycles();
        
        return [
            'memory_optimized' => true,
            'usage_before' => $current_usage,
            'usage_after' => memory_get_usage(true),
            'percentage' => $usage_percentage
        ];
    }
    
    return [
        'memory_optimized' => false,
        'current_usage' => $current_usage,
        'percentage' => $usage_percentage
    ];
}

/**
 * Convert memory string to bytes
 */
function convertToBytes($memory_string) {
    $unit = strtolower(substr($memory_string, -1));
    $size = (int) substr($memory_string, 0, -1);
    
    switch ($unit) {
        case 'g':
            return $size * 1024 * 1024 * 1024;
        case 'm':
            return $size * 1024 * 1024;
        case 'k':
            return $size * 1024;
        default:
            return $size;
    }
}

// ==============================================
// REAL-TIME UPDATES - ENGAGING UX
// ==============================================

/**
 * Send real-time progress update
 */
function sendProgressUpdate($session_id, $progress_data) {
    // Update session data
    updateSequentialSession($session_id, $progress_data);
    
    // If SSE is enabled, send real-time update
    if (getConfig('ENABLE_SSE', false)) {
        sendSSEUpdate($progress_data);
    }
    
    return true;
}

/**
 * Send Server-Sent Events update
 */
function sendSSEUpdate($data) {
    if (headers_sent()) {
        return false;
    }
    
    echo "data: " . json_encode($data) . "\n\n";
    
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
    
    return true;
}

/**
 * Generate engaging progress messages
 */
function generateProgressMessage($current_work, $total_works, $work_title = null) {
    $percentage = round(($current_work / $total_works) * 100);
    $work_title = $work_title ? truncateText($work_title, 40) : "Work {$current_work}";
    
    $messages = [
        "🔍 Analyzing ({$current_work}/{$total_works}): {$work_title}",
        "📊 Processing research #{$current_work} of {$total_works}...",
        "🎯 SDG analysis in progress: {$work_title}",
        "⚡ Working on publication {$current_work}/{$total_works}..."
    ];
    
    $message_index = ($current_work - 1) % count($messages);
    return $messages[$message_index];
}

// ==============================================
// UX ORCHESTRATION - ENHANCED EXPERIENCE
// ==============================================

/**
 * Orchestrate enhanced user experience
 */
function orchestrateEnhancedUX($session_id, $phase, $data = []) {
    $ux_updates = [];
    
    switch ($phase) {
        case 'start':
            $ux_updates = [
                'show_progress_bar' => true,
                'hide_form' => true,
                'display_researcher_placeholder' => true,
                'animate_transition' => true
            ];
            break;
            
        case 'researcher_info_complete':
            $ux_updates = [
                'display_researcher_info' => $data,
                'show_works_loading' => true,
                'animate_researcher_card' => true
            ];
            break;
            
        case 'works_list_complete':
            $ux_updates = [
                'display_works_overview' => $data,
                'show_analysis_progress' => true,
                'prepare_results_container' => true
            ];
            break;
            
        case 'work_analysis_complete':
            $ux_updates = [
                'append_work_result' => $data,
                'update_charts_progressive' => true,
                'animate_new_result' => true
            ];
            break;
            
        case 'analysis_complete':
            $ux_updates = [
                'show_final_summary' => $data,
                'enable_sharing' => true,
                'show_celebration_animation' => true,
                'generate_shareable_url' => true
            ];
            break;
    }
    
    // Send UX updates to session
    updateSequentialSession($session_id, [
        'ux_updates' => $ux_updates,
        'phase' => $phase,
        'updated_at' => date('c')
    ]);
    
    return $ux_updates;
}

/**
 * Generate shareable results URL
 */
function generateShareableResultsUrl($session_id, $input, $type) {
    $base_url = getConfig('SITE_URL', 'https://www.wizdam.sangia.org');
    $params = [
        'page' => 'results',
        $type => $input,
        'session' => $session_id
    ];
    
    return $base_url . '?' . http_build_query($params);
}

// ==============================================
// LOGGING & MONITORING
// ==============================================

/**
 * Log processing steps untuk monitoring
 */
function logSequentialProcessing($session_id, $step, $data = []) {
    $log_entry = [
        'timestamp' => date('c'),
        'session_id' => $session_id,
        'step' => $step,
        'data' => $data,
        'memory_usage' => memory_get_usage(true),
        'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
    ];
    
    // Log to file if enabled
    if (getConfig('ENABLE_LOGGING', true)) {
        error_log("Sequential Processing: " . json_encode($log_entry));
    }
    
    return $log_entry;
}

/**
 * Monitor performance metrics
 */
function getPerformanceMetrics($session_id) {
    $metrics = [
        'memory_usage' => [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ],
        'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
        'session_data_size' => 0
    ];
    
    // Get session data size
    $session_data = getSequentialProgress($session_id);
    if ($session_data) {
        $metrics['session_data_size'] = strlen(json_encode($session_data));
    }
    
    return $metrics;
}

?>