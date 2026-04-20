<?php
/**
 * Wizdam: Functions - EXACT COPY from SDG Frontend + Modular Enhancements
 * 
 * @version 2.4 - Modular Enhancement
 * @author Rochmady and Wizdam Team
 * @license MIT
 * 
 * SESUAI PANDUAN: EXACT COPY functions dari SDG Frontend + modular enhancements
 */

// ==============================================
// EXACT COPY FUNCTIONS dari SDG Frontend
// ==============================================

/**
 * Make API request - EXACT COPY dari SDG Frontend
 */
function makeApiRequest($url) {
    if (!function_exists('curl_init')) {
        return array('error' => 'cURL not available');
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_USERAGENT => 'SDG Interface/2.3',
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'Content-Type: application/json'
        )
    ));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        return array('error' => 'Connection failed: ' . $curl_error);
    }
    
    if ($http_code !== 200) {
        return array('error' => 'HTTP Error: ' . $http_code . ' - ' . $response);
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array('error' => 'Invalid response: ' . json_last_error_msg());
    }
    
    return array('data' => $data);
}

/**
 * Detect input type - EXACT COPY dari SDG Frontend
 */
function detectInputType($input) {
    $input = trim($input);
    
    if (preg_match('/orcid\.org\/(\d{4}-\d{4}-\d{4}-\d{3}[\dX])/', $input, $matches)) {
        return 'orcid';
    }
    
    if (preg_match('/^(\d{4}-\d{4}-\d{4}-\d{3}[\dX])$/', $input)) {
        return 'orcid';
    }
    
    if (preg_match('/^0000-\d{4}-\d{4}-\d{3}[\dX]$/', $input)) {
        return 'orcid';
    }
    
    if (strlen($input) >= 7 && strpos($input, '/') !== false) {
        if (preg_match('/^10\.\d+\//', $input) || 
            preg_match('/doi\.org\//', $input) || 
            preg_match('/dx\.doi\.org\//', $input)) {
            return 'doi';
        }
        if (strlen($input) > 10) {
            return 'doi';
        }
    }
    
    return null;
}

/**
 * Clean input - EXACT COPY dari SDG Frontend
 */
function cleanInput($input, $type) {
    $input = trim($input);
    
    if ($type === 'orcid') {
        if (preg_match('/orcid\.org\/(\d{4}-\d{4}-\d{4}-\d{3}[\dX])/', $input, $matches)) {
            $input = $matches[1];
        }
        $input = preg_replace('/[^\d\-X]/i', '', $input);
        
        if (!preg_match('/^0000-/', $input)) {
            if (preg_match('/^\d{15}[\dX]$/', $input)) {
                $input = substr($input, 0, 4) . '-' . substr($input, 4, 4) . '-' . substr($input, 8, 4) . '-' . substr($input, 12);
            }
        }
    }
    
    if ($type === 'doi') {
        $input = str_replace('https://doi.org/', '', $input);
        $input = str_replace('http://doi.org/', '', $input);
        $input = str_replace('https://dx.doi.org/', '', $input);
        $input = str_replace('http://dx.doi.org/', '', $input);
        $input = str_replace('doi:', '', $input);
    }
    
    return $input;
}

/**
 * Validate ORCID - EXACT COPY dari SDG Frontend
 */
function validateOrcid($orcid) {
    $orcid = trim($orcid);
    
    if (!preg_match('/^0000-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
        return false;
    }
    
    $digits = str_replace('-', '', substr($orcid, 0, -1));
    $checkDigit = substr($orcid, -1);
    
    $total = 0;
    for ($i = 0; $i < strlen($digits); $i++) {
        $total = ($total + intval($digits[$i])) * 2;
    }
    
    $remainder = $total % 11;
    $result = (12 - $remainder) % 11;
    $expectedCheckDigit = ($result == 10) ? 'X' : strval($result);
    
    return $checkDigit === $expectedCheckDigit;
}

/**
 * Truncate HTML - EXACT COPY dari SDG Frontend
 */
function truncateHtml($text, $length = 100, $ending = '...', $exact = false, $considerHtml = true) {
    if ($considerHtml) {
        if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
            return $text;
        }
        
        $totalLength = strlen($ending);
        $openTags = array();
        $truncated = '';
        
        preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
        
        foreach ($lines as $lineMatchings) {
            if (!empty($lineMatchings[1])) {
                if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $lineMatchings[1])) {
                } elseif (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $lineMatchings[1], $tagMatchings)) {
                    $pos = array_search($tagMatchings[1], $openTags);
                    if ($pos !== false) {
                        unset($openTags[$pos]);
                    }
                } elseif (preg_match('/^<\s*([^\s>!]+).*?>$/s', $lineMatchings[1], $tagMatchings)) {
                    array_unshift($openTags, strtolower($tagMatchings[1]));
                }
                $truncated .= $lineMatchings[1];
            }
            
            $contentLength = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $lineMatchings[2]));
            if ($totalLength + $contentLength > $length) {
                $left = $length - $totalLength;
                $entitiesLength = 0;
                if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $lineMatchings[2], $entities, PREG_OFFSET_CAPTURE)) {
                    foreach ($entities[0] as $entity) {
                        if ($entity[1] + 1 - $entitiesLength <= $left) {
                            $left--;
                            $entitiesLength += strlen($entity[0]);
                        } else {
                            break;
                        }
                    }
                }
                $truncated .= substr($lineMatchings[2], 0, $left + $entitiesLength);
                break;
            } else {
                $truncated .= $lineMatchings[2];
                $totalLength += $contentLength;
            }
            
            if ($totalLength >= $length) {
                break;
            }
        }
    } else {
        if (strlen($text) <= $length) {
            return $text;
        } else {
            $truncated = substr($text, 0, $length - strlen($ending));
        }
    }
    
    if (!$exact) {
        $spacepos = strrpos($truncated, ' ');
        if (isset($spacepos)) {
            if ($considerHtml) {
                $bits = substr($truncated, $spacepos);
                preg_match_all('/<\/([a-z]+)>/', $bits, $droppedTags, PREG_SET_ORDER);
                if (!empty($droppedTags)) {
                    foreach ($droppedTags as $closingTag) {
                        if (!in_array($closingTag[1], $openTags)) {
                            array_unshift($openTags, $closingTag[1]);
                        }
                    }
                }
            }
            $truncated = substr($truncated, 0, $spacepos);
        }
    }
    
    $truncated .= $ending;
    
    if ($considerHtml) {
        foreach ($openTags as $tag) {
            $truncated .= '</' . $tag . '>';
        }
    }
    
    return $truncated;
}

// ==============================================
// MODULAR ENHANCEMENTS - NEW FUNCTIONS
// ==============================================

/**
 * Fetch analysis from API with auto-detection - ENHANCED
 */
function fetchAnalysisFromAPI($input, $force_refresh = false) {
    $detected_type = detectInputType($input);
    
    if (!$detected_type) {
        return [
            'status' => 'error',
            'message' => 'Invalid input format. Please provide valid ORCID or DOI.'
        ];
    }
    
    $clean_input = cleanInput($input, $detected_type);
    
    // Validate cleaned input
    if ($detected_type === 'orcid' && !validateOrcid($clean_input)) {
        return [
            'status' => 'error',
            'message' => 'Invalid ORCID format or checksum.'
        ];
    }
    
    $api_url = getConfig('API_BASE_URL') . '?' . $detected_type . '=' . urlencode($clean_input);
    
    if ($force_refresh) {
        $api_url .= '&refresh=true';
    }
    
    $api_response = makeApiRequest($api_url);
    
    if (isset($api_response['error'])) {
        return [
            'status' => 'error',
            'message' => 'API error: ' . $api_response['error']
        ];
    }
    
    return $api_response['data'];
}

/**
 * Process form submission - ENHANCED with better error handling
 */
function processFormSubmission() {
    $result = [
        'success' => false,
        'data' => null,
        'error' => null,
        'input_type' => null,
        'clean_input' => null
    ];
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $result;
    }
    
    $input_value = isset($_POST['input_value']) ? trim($_POST['input_value']) : '';
    
    if (empty($input_value)) {
        $result['error'] = 'Please enter a valid ORCID ID or DOI';
        return $result;
    }
    
    $detected_type = detectInputType($input_value);
    $result['input_type'] = $detected_type;
    
    if ($detected_type === null) {
        $result['error'] = 'Input format not recognised. Please enter a valid ORCID ID (format: 0000-0000-0000-0000) or DOI.';
        return $result;
    }
    
    $clean_input = cleanInput($input_value, $detected_type);
    $result['clean_input'] = $clean_input;
    
    if ($detected_type === 'orcid' && !validateOrcid($clean_input)) {
        $result['error'] = 'The ORCID ID is invalid. The correct format is: 0000-0000-0000-0000 (with a valid checksum)';
        return $result;
    }
    
    $force_refresh = isset($_POST['force_refresh']);
    $analysis_result = fetchAnalysisFromAPI($clean_input, $force_refresh);
    
    if (!is_array($analysis_result)) {
        $result['error'] = 'Invalid API response format';
        return $result;
    }
    
    if (!isset($analysis_result['status'])) {
        $result['error'] = 'API response missing status';
        return $result;
    }
    
    if ($analysis_result['status'] !== 'success') {
        $result['error'] = 'API error: ' . ($analysis_result['message'] ?? 'Unknown error');
        return $result;
    }
    
    $result['success'] = true;
    $result['data'] = $analysis_result;
    
    return $result;
}

/**
 * Generate shareable URL - ENHANCED
 */
function generateShareableUrl($type, $input, $refresh = false) {
    $base_url = getConfig('SITE_URL');
    $params = ['page' => 'results', $type => $input];
    
    if ($refresh) {
        $params['refresh'] = 'true';
    }
    
    return $base_url . '?' . http_build_query($params);
}

/**
 * Get chart data for JavaScript - ENHANCED
 */
function getChartDataFromResults($analysis_result) {
    $chart_data = [];
    
    // SDG Distribution Chart
    if (isset($analysis_result['analysis_summary']['sdg_distribution'])) {
        $sdg_data = [];
        $colors = [];
        $labels = [];
        
        foreach ($analysis_result['analysis_summary']['sdg_distribution'] as $sdg_code => $counts) {
            $definition = getSdgDefinition($sdg_code);
            $total = ($counts['primary'] ?? 0) + ($counts['secondary'] ?? 0);
            
            if ($total > 0) {
                $labels[] = $sdg_code;
                $sdg_data[] = $total;
                $colors[] = $definition['color'] ?? '#ccc';
            }
        }
        
        $chart_data['sdg_distribution'] = [
            'labels' => $labels,
            'data' => $sdg_data,
            'colors' => $colors
        ];
    }
    
    // Contributor Types Chart (if applicable)
    if (isset($analysis_result['analysis_summary']['contributor_types'])) {
        $chart_data['contributor_types'] = $analysis_result['analysis_summary']['contributor_types'];
    }
    
    return $chart_data;
}

/**
 * Format display date - UTILITY
 */
function formatDisplayDate($date_string) {
    if (empty($date_string)) return 'Unknown';
    
    $date = DateTime::createFromFormat('Y-m-d', $date_string);
    if ($date) {
        return $date->format('F Y');
    }
    
    return $date_string;
}

/**
 * Get confidence class for styling - UTILITY
 */
function getConfidenceClass($confidence) {
    $confidence = round($confidence * 100);
    if ($confidence >= 80) return 'confidence-high';
    if ($confidence >= 60) return 'confidence-medium';
    return 'confidence-low';
}

/**
 * Check API status - UTILITY
 */
function checkApiStatus() {
    $api_url = getConfig('API_BASE_URL');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}

/**
 * Get summary statistics from results - UTILITY
 */
function getSummaryStats($analysis_result) {
    return [
        'total_works' => $analysis_result['total_works'] ?? 0,
        'analyzed_works' => $analysis_result['analyzed_works'] ?? 0,
        'primary_contributions' => $analysis_result['analysis_summary']['total_primary_contributions'] ?? 0,
        'secondary_contributions' => $analysis_result['analysis_summary']['total_secondary_contributions'] ?? 0,
        'top_sdg' => isset($analysis_result['analysis_summary']['top_sdgs']) ? 
                     array_key_first($analysis_result['analysis_summary']['top_sdgs']) : null
    ];
}

// ==============================================
// SEQUENTIAL ANALYSIS ENHANCEMENTS - PANDUAN REQUIREMENT
// ==============================================

/**
 * Fetch researcher profile ONLY (quick) - untuk sequential step 1
 */
function fetchResearcherProfile($orcid) {
    $api_url = getConfig('API_BASE_URL') . '?orcid=' . urlencode($orcid);
    $api_response = makeApiRequest($api_url);
    
    if (isset($api_response['error'])) {
        return [
            'orcid' => $orcid,
            'name' => 'Unknown Researcher',
            'institutions' => [],
            'error' => $api_response['error']
        ];
    }
    
    $result = $api_response['data'];
    
    // ORCID menggunakan 'personal_info'
    if (isset($result['personal_info'])) {
        return $result['personal_info'];
    }
    
    return [
        'orcid' => $orcid,
        'name' => 'Unknown Researcher', 
        'institutions' => [],
        'error' => 'No personal info found'
    ];
}

/**
 * Fetch works metadata ONLY (no analysis) - untuk sequential step 2
 * ORCID = works, DOI = articles
 */
function fetchWorksMetadata($orcid) {
    $api_url = getConfig('API_BASE_URL') . '?orcid=' . urlencode($orcid);
    $api_response = makeApiRequest($api_url);
    
    if (isset($api_response['error'])) {
        return [];
    }
    
    $result = $api_response['data'];
    $works = [];
    
    // ORCID menggunakan 'works' - METADATA KOMPLIT dari API SDG Enhanced
    if (isset($result['works']) && is_array($result['works'])) {
        foreach ($result['works'] as $index => $work) {
            $works[] = [
                'index' => $index,
                
                // CORE METADATA - KOMPLIT dari API SDG Enhanced
                'title' => $work['title'] ?? 'Unknown Title',
                'doi' => $work['doi'] ?? '',
                'abstract' => $work['abstract'] ?? '',
                'authors' => $work['authors'] ?? [],                    // KOMPLIT!
                'journal' => $work['journal'] ?? '',                    // KOMPLIT!
                'publisher' => $work['publisher'] ?? '',                // KOMPLIT!
                'published_date' => $work['published_date'] ?? '',      // KOMPLIT!
                'language' => $work['language'] ?? 'en',
                'type' => $work['type'] ?? 'journal-article',
                
                // ENHANCED METADATA
                'open_access' => $work['open_access'] ?? [
                    'is_oa' => false,
                    'oa_date' => null,
                    'oa_url' => null,
                    'any_repository_has_fulltext' => false
                ],
                'keywords' => $work['keywords'] ?? [],
                'cited_by_count' => $work['cited_by_count'] ?? 0,
                'openalex_id' => $work['openalex_id'] ?? '',
                'data_source' => $work['data_source'] ?? 'ORCID + Crossref',
                
                // SDG ANALYSIS - SUDAH DIANALISIS API!
                'sdgs' => $work['sdgs'] ?? [],
                'sdg_confidence' => $work['sdg_confidence'] ?? [],
                'contributor_types' => $work['contributor_types'] ?? [],
                'detailed_analysis' => $work['detailed_analysis'] ?? []
            ];
        }
    }
    
    return $works;
}

/**
 * Get single work analysis dari full result - untuk sequential step 3
 * ORCID = works, DOI = articles
 */
function getSingleWorkAnalysis($orcid, $work_index) {
    $api_url = getConfig('API_BASE_URL') . '?orcid=' . urlencode($orcid);
    $api_response = makeApiRequest($api_url);
    
    if (isset($api_response['error'])) {
        return null;
    }
    
    $result = $api_response['data'];
    
    // ORCID menggunakan 'works'
    if (isset($result['works'][$work_index])) {
        $work = $result['works'][$work_index];
        
        return [
            // COMPLETE METADATA - KOMPLIT dari API SDG Enhanced
            'title' => $work['title'] ?? 'Unknown Title',
            'doi' => $work['doi'] ?? '',
            'abstract' => $work['abstract'] ?? '',
            'authors' => $work['authors'] ?? [],                    // KOMPLIT!
            'journal' => $work['journal'] ?? '',                    // KOMPLIT!
            'publisher' => $work['publisher'] ?? '',                // KOMPLIT!
            'published_date' => $work['published_date'] ?? '',      // KOMPLIT!
            'language' => $work['language'] ?? 'en',
            'type' => $work['type'] ?? 'journal-article',
            
            // ENHANCED METADATA
            'open_access' => $work['open_access'] ?? [
                'is_oa' => false,
                'oa_date' => null,
                'oa_url' => null,
                'any_repository_has_fulltext' => false
            ],
            'keywords' => $work['keywords'] ?? [],
            'cited_by_count' => $work['cited_by_count'] ?? 0,
            'openalex_id' => $work['openalex_id'] ?? '',
            'data_source' => $work['data_source'] ?? 'ORCID + Crossref + OpenAlex',
            
            // SDG ANALYSIS - SUDAH KOMPLIT dari API!
            'sdgs' => $work['sdgs'] ?? [],
            'sdg_confidence' => $work['sdg_confidence'] ?? [],
            'contributor_types' => $work['contributor_types'] ?? [],
            'detailed_analysis' => $work['detailed_analysis'] ?? [],
            
            // ANALYSIS METADATA
            'status' => 'success',
            'analyzed_at' => date('c'),
            'work_index' => $work_index
        ];
    }
    
    return null;
}

/**
 * Get single article analysis untuk DOI - ENHANCED
 */
function getSingleArticleAnalysis($doi) {
    $api_url = getConfig('API_BASE_URL') . '?doi=' . urlencode($doi);
    $api_response = makeApiRequest($api_url);
    
    if (isset($api_response['error'])) {
        return null;
    }
    
    $result = $api_response['data'];
    
    // DOI menggunakan 'article'
    if (isset($result['article'])) {
        $article = $result['article'];
        
        return [
            // COMPLETE METADATA - KOMPLIT dari API SDG Enhanced
            'doi' => $article['doi'] ?? '',
            'title' => $article['title'] ?? 'Unknown Title',
            'abstract' => $article['abstract'] ?? '',
            'authors' => $article['authors'] ?? [],                 // KOMPLIT!
            'journal' => $article['journal'] ?? '',                 // KOMPLIT!
            'publisher' => $article['publisher'] ?? '',             // KOMPLIT!
            'published' => $article['published'] ?? '',             // KOMPLIT!
            'language' => $article['language'] ?? 'en',
            'type' => $article['type'] ?? 'article',
            
            // ENHANCED METADATA jika ada
            'enhanced_metadata' => $article['enhanced_metadata'] ?? [],
            
            // SDG ANALYSIS - SUDAH KOMPLIT dari API!
            'sdgs' => $article['sdgs'] ?? [],
            'sdg_confidence' => $article['sdg_confidence'] ?? [],
            'contributor_types' => $article['contributor_types'] ?? [],
            'detailed_analysis' => $article['detailed_analysis'] ?? [],
            
            // ANALYSIS METADATA
            'status' => 'success',
            'analyzed_at' => date('c')
        ];
    }
    
    return null;
}

/**
 * Generate session ID untuk sequential tracking
 */
function generateSessionId() {
    return uniqid('seq_', true);
}

/**
 * Save sequential progress
 */
function saveSequentialProgress($session_id, $progress_data) {
    $cache_dir = __DIR__ . '/../cache/sequential';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $cache_file = $cache_dir . '/' . $session_id . '.json';
    $json_data = json_encode($progress_data);
    
    return file_put_contents($cache_file, $json_data) !== false;
}

/**
 * Get sequential progress
 */
function getSequentialProgress($session_id) {
    $cache_dir = __DIR__ . '/../cache/sequential';
    $cache_file = $cache_dir . '/' . $session_id . '.json';
    
    if (!file_exists($cache_file)) {
        return null;
    }
    
    // Check if cache expired (1 hour)
    if ((time() - filemtime($cache_file)) > 3600) {
        unlink($cache_file);
        return null;
    }
    
    $json_data = file_get_contents($cache_file);
    return json_decode($json_data, true);
}

/**
 * Clean expired sequential cache
 */
function cleanSequentialCache() {
    $cache_dir = __DIR__ . '/../cache/sequential';
    if (!is_dir($cache_dir)) {
        return;
    }
    
    $files = glob($cache_dir . '/*.json');
    foreach ($files as $file) {
        if ((time() - filemtime($file)) > 3600) {
            unlink($file);
        }
    }
}

// ==============================================
// URL INTEGRATION ENHANCEMENTS - PANDUAN REQUIREMENT
// ==============================================

/**
 * Parse URL parameters for direct access
 */
function parseUrlParameters() {
    return [
        'orcid' => $_GET['orcid'] ?? null,
        'doi' => $_GET['doi'] ?? null,
        'input' => $_GET['input'] ?? null,
        'refresh' => ($_GET['refresh'] ?? 'false') === 'true',
        'page' => $_GET['page'] ?? 'home',
        'session' => $_GET['session'] ?? null
    ];
}

/**
 * Validate URL access parameters
 */
function validateUrlAccess($params) {
    $errors = [];
    
    if (!empty($params['orcid'])) {
        if (!validateOrcid($params['orcid'])) {
            $errors[] = 'Invalid ORCID format';
        }
    }
    
    if (!empty($params['doi'])) {
        $clean_doi = str_replace(['https://doi.org/', 'http://doi.org/', 'doi:'], '', $params['doi']);
        if (!preg_match('/^10\.\d{4,}\/\S+$/', $clean_doi)) {
            $errors[] = 'Invalid DOI format';
        }
    }
    
    if (!empty($params['input'])) {
        $detected_type = detectInputType($params['input']);
        if (!$detected_type) {
            $errors[] = 'Invalid input format';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Handle auto-analysis from URL
 */
function handleAutoAnalysis($input) {
    $detected_type = detectInputType($input);
    
    if (!$detected_type) {
        return [
            'success' => false,
            'error' => 'Invalid input format'
        ];
    }
    
    $clean_input = cleanInput($input, $detected_type);
    
    if ($detected_type === 'orcid' && !validateOrcid($clean_input)) {
        return [
            'success' => false,
            'error' => 'Invalid ORCID checksum'
        ];
    }
    
    // Redirect to results page
    $redirect_url = generateShareableUrl($detected_type, $clean_input);
    
    return [
        'success' => true,
        'redirect_url' => $redirect_url,
        'type' => $detected_type,
        'input' => $clean_input
    ];
}

// ==============================================
// ENHANCED CACHING - PANDUAN REQUIREMENT  
// ==============================================

/**
 * Get sequential cache with TTL
 */
function getSequentialCache($cache_key) {
    $cache_dir = __DIR__ . '/../cache/sequential';
    $cache_file = $cache_dir . '/' . md5($cache_key) . '.cache';
    
    if (!file_exists($cache_file)) {
        return false;
    }
    
    $sequential_ttl = getConfig('SEQUENTIAL_CACHE_TTL', 1800); // 30 minutes
    if ((time() - filemtime($cache_file)) > $sequential_ttl) {
        unlink($cache_file);
        return false;
    }
    
    $compressed_data = file_get_contents($cache_file);
    $json_data = gzdecode($compressed_data);
    
    if ($json_data === false) {
        return false;
    }
    
    return json_decode($json_data, true);
}

/**
 * Save sequential cache
 */
function saveSequentialCache($cache_key, $data) {
    $cache_dir = __DIR__ . '/../cache/sequential';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $cache_file = $cache_dir . '/' . md5($cache_key) . '.cache';
    $json_data = json_encode($data);
    $compressed_data = gzencode($json_data, 9);
    
    return file_put_contents($cache_file, $compressed_data) !== false;
}

/**
 * Generate progress update for frontend
 */
function generateProgressUpdate($current_work, $total_works, $current_analysis = null) {
    $percentage = $total_works > 0 ? round(($current_work / $total_works) * 100) : 0;
    
    return [
        'current_work' => $current_work,
        'total_works' => $total_works,
        'percentage' => $percentage,
        'status' => $current_analysis ? "Analyzing: {$current_analysis['title']}" : 'Preparing analysis...',
        'timestamp' => date('c')
    ];
}

/**
 * Initialize sequential analysis session
 */
function initializeSequentialSession($input, $type) {
    $session_id = generateSessionId();
    
    $session_data = [
        'session_id' => $session_id,
        'input' => $input,
        'type' => $type,
        'status' => 'initialized',
        'progress' => 0,
        'current_work' => 0,
        'total_works' => 0,
        'results' => [],
        'created_at' => date('c'),
        'updated_at' => date('c')
    ];
    
    saveSequentialProgress($session_id, $session_data);
    
    return $session_id;
}

/**
 * Update sequential session progress
 */
function updateSequentialSession($session_id, $updates) {
    $session_data = getSequentialProgress($session_id);
    
    if (!$session_data) {
        return false;
    }
    
    foreach ($updates as $key => $value) {
        $session_data[$key] = $value;
    }
    
    $session_data['updated_at'] = date('c');
    
    return saveSequentialProgress($session_id, $session_data);
}

/**
 * Complete sequential analysis session
 */
function completeSequentialSession($session_id, $final_results) {
    $session_data = getSequentialProgress($session_id);
    
    if (!$session_data) {
        return false;
    }
    
    $session_data['status'] = 'completed';
    $session_data['progress'] = 100;
    $session_data['final_results'] = $final_results;
    $session_data['completed_at'] = date('c');
    
    return saveSequentialProgress($session_id, $session_data);
}

?>