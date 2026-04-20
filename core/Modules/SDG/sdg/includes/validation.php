<?php
/**
 * Wizdam: Enhanced Validation for URL Integration & Sequential Analysis
 * HANYA NEW VALIDATION FUNCTIONS - tidak duplikasi dengan includes/functions.php
 * 
 * @version 2.4 - Enhanced Modular
 * @author Rochmady and Wizdam Team
 * @license MIT
 * Last update: 2025-06-22
 * 
 * SESUAI PANDUAN: Enhanced validation untuk URL sharing dan social integration
 * NOTE: Core validation functions (validateOrcid, detectInputType, cleanInput) 
 *       sudah ada di includes/functions.php - file ini HANYA untuk enhancements
 */

// ==============================================
// DOI ENHANCED VALIDATION - NEW FUNCTIONS ONLY
// ==============================================

/**
 * Enhanced DOI validation dengan special characters check
 * Complement untuk existing cleanInput() di functions.php
 */
function validateDoiSpecialChars($doi) {
    // DOI should not contain certain invalid characters
    $invalid_chars = [' ', '\t', '\n', '\r'];
    
    foreach ($invalid_chars as $char) {
        if (strpos($doi, $char) !== false) {
            return false;
        }
    }
    
    return true;
}

/**
 * Enhanced DOI validation - wrapper untuk existing functions
 */
function validateDoiEnhanced($doi) {
    if (empty($doi)) {
        return false;
    }
    
    $doi = trim($doi);
    
    // Use existing cleanInput from functions.php
    $clean_doi = cleanInput($doi, 'doi');
    
    // Enhanced DOI pattern validation
    $pattern = '/^10\.\d{4,}\/[^\s]+$/';
    
    if (!preg_match($pattern, $clean_doi)) {
        return false;
    }
    
    // Additional validation untuk special characters
    return validateDoiSpecialChars($clean_doi);
}

// ==============================================
// ENHANCED VALIDATION HELPERS - NEW FUNCTIONS ONLY
// ==============================================

/**
 * Enhanced input validation wrapper
 * Uses existing functions dari includes/functions.php dengan enhancements
 */
function validateInputEnhanced($input, $type = null) {
    if (empty($input)) {
        return [
            'valid' => false,
            'error' => 'Please enter an ORCID ID or DOI',
            'type' => null,
            'clean_input' => null
        ];
    }
    
    $input = trim($input);
    
    // Use existing detectInputType from functions.php
    if ($type === null) {
        $type = detectInputType($input);
    }
    
    if ($type === null) {
        return [
            'valid' => false,
            'error' => 'Input format not recognised. Please enter a valid ORCID ID (format: 0000-0000-0000-0000) or DOI.',
            'type' => null,
            'clean_input' => null
        ];
    }
    
    // Use existing cleanInput from functions.php
    $clean_input = cleanInput($input, $type);
    
    // Use existing validateOrcid from functions.php
    if ($type === 'orcid') {
        if (!validateOrcid($clean_input)) {
            return [
                'valid' => false,
                'error' => 'Invalid ORCID format or checksum. Expected format: 0000-0000-0000-000X',
                'type' => $type,
                'clean_input' => $clean_input
            ];
        }
    } elseif ($type === 'doi') {
        if (!validateDoiEnhanced($clean_input)) {
            return [
                'valid' => false,
                'error' => 'Invalid DOI format. Expected format: 10.xxxx/xxxxx',
                'type' => $type,
                'clean_input' => $clean_input
            ];
        }
    }
    
    return [
        'valid' => true,
        'error' => null,
        'type' => $type,
        'clean_input' => $clean_input
    ];
}

/**
 * Enhanced URL parameters validation 
 * Complement untuk existing validateUrlAccess() di functions.php
 */
function validateUrlParametersEnhanced($params) {
    $validation_result = [
        'valid' => true,
        'errors' => [],
        'warnings' => [],
        'clean_params' => []
    ];
    
    // Use existing validateUrlAccess logic from functions.php
    $basic_validation = validateUrlAccess($params);
    
    if (!$basic_validation['valid']) {
        $validation_result['valid'] = false;
        $validation_result['errors'] = $basic_validation['errors'];
    }
    
    // Enhanced validations (new)
    
    // Validate session parameter dengan enhanced checks
    if (isset($params['session']) && !empty($params['session'])) {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $params['session'])) {
            $validation_result['warnings']['session'] = 'Invalid session format, will generate new session';
        } else {
            $validation_result['clean_params']['session'] = $params['session'];
        }
    }
    
    // Enhanced refresh parameter validation
    if (isset($params['refresh'])) {
        $validation_result['clean_params']['refresh'] = ($params['refresh'] === 'true' || $params['refresh'] === '1');
    }
    
    // Enhanced page parameter validation
    if (isset($params['page'])) {
        $allowed_pages = ['home', 'results', 'about', 'documentation'];
        if (in_array($params['page'], $allowed_pages)) {
            $validation_result['clean_params']['page'] = $params['page'];
        } else {
            $validation_result['warnings']['page'] = 'Invalid page, redirecting to home';
            $validation_result['clean_params']['page'] = 'home';
        }
    }
    
    return $validation_result;
}

// ==============================================
// FORM VALIDATION - ENHANCED UX (NEW FUNCTIONS)
// ==============================================

/**
 * Enhanced form validation wrapper
 * Uses existing processFormSubmission() dari functions.php dengan enhancements
 */
function validateFormSubmissionEnhanced($form_data) {
    // Use existing processFormSubmission from functions.php
    $basic_result = processFormSubmission();
    
    // Enhanced result structure
    $validation_result = [
        'valid' => $basic_result['success'],
        'data' => $basic_result['data'],
        'error' => $basic_result['error'],
        'field_errors' => [],
        'input_type' => $basic_result['input_type'],
        'clean_input' => $basic_result['clean_input'],
        'enhanced_checks' => true
    ];
    
    // Enhanced error handling
    if (!$validation_result['valid'] && $validation_result['error']) {
        $validation_result['field_errors']['input_value'] = $validation_result['error'];
    }
    
    return $validation_result;
}

/**
 * Enhanced batch validation untuk sequential analysis
 * NEW FUNCTION - tidak ada di functions.php
 */
function validateBatchInputs($inputs) {
    $results = [
        'valid_inputs' => [],
        'invalid_inputs' => [],
        'summary' => [
            'total' => count($inputs),
            'valid' => 0,
            'invalid' => 0
        ]
    ];
    
    foreach ($inputs as $index => $input) {
        // Use existing detectInputType and cleanInput from functions.php
        $type = detectInputType($input);
        
        if ($type) {
            $clean_input = cleanInput($input, $type);
            
            // Use existing validateOrcid from functions.php
            $is_valid = ($type === 'orcid') ? validateOrcid($clean_input) : validateDoiEnhanced($clean_input);
            
            if ($is_valid) {
                $results['valid_inputs'][] = [
                    'index' => $index,
                    'input' => $input,
                    'type' => $type,
                    'clean_input' => $clean_input
                ];
                $results['summary']['valid']++;
            } else {
                $results['invalid_inputs'][] = [
                    'index' => $index,
                    'input' => $input,
                    'error' => 'Invalid ' . strtoupper($type) . ' format'
                ];
                $results['summary']['invalid']++;
            }
        } else {
            $results['invalid_inputs'][] = [
                'index' => $index,
                'input' => $input,
                'error' => 'Unrecognized input format'
            ];
            $results['summary']['invalid']++;
        }
    }
    
    return $results;
}

// ==============================================
// ENHANCED ERROR HANDLING & UTILITIES - NEW FUNCTIONS
// ==============================================

/**
 * Generate enhanced validation response untuk AJAX
 * NEW FUNCTION - advanced dari basic validation
 */
function generateValidationResponse($validation_result) {
    $response = [
        'status' => $validation_result['valid'] ? 'success' : 'error',
        'valid' => $validation_result['valid'],
        'timestamp' => date('c'),
        'version' => '2.4-enhanced'
    ];
    
    if ($validation_result['valid']) {
        $response['data'] = [
            'type' => $validation_result['type'],
            'clean_input' => $validation_result['clean_input'],
            'formatted_input' => formatInputForDisplay($validation_result['clean_input'], $validation_result['type'])
        ];
    } else {
        $response['error'] = $validation_result['error'];
        if (isset($validation_result['field_errors'])) {
            $response['field_errors'] = $validation_result['field_errors'];
        }
        
        // Enhanced error suggestions
        $response['suggestions'] = getErrorSuggestions($validation_result['error']);
    }
    
    return $response;
}

/**
 * Format input untuk display purposes
 * NEW FUNCTION - enhanced formatting
 */
function formatInputForDisplay($input, $type) {
    if ($type === 'orcid') {
        // Use existing cleanInput from functions.php
        return cleanInput($input, 'orcid');
    } elseif ($type === 'doi') {
        // Use existing cleanInput from functions.php  
        return cleanInput($input, 'doi');
    }
    
    return $input;
}

/**
 * Generate enhanced shareable URL dengan validation
 * Enhanced dari basic URL generation
 */
function generateShareableUrlEnhanced($type, $input, $base_url = null) {
    if ($base_url === null) {
        $base_url = getConfig('SITE_URL', 'https://www.wizdam.sangia.org');
    }
    
    // Use existing validation functions
    $clean_input = cleanInput($input, $type);
    
    if ($type === 'orcid' && !validateOrcid($clean_input)) {
        return false;
    }
    
    if ($type === 'doi' && !validateDoiEnhanced($clean_input)) {
        return false;
    }
    
    $params = ['page' => 'results', $type => $clean_input];
    
    return $base_url . '/?' . http_build_query($params);
}

/**
 * Get detailed validation error messages
 * NEW FUNCTION - enhanced error messages
 */
function getValidationErrorMessage($error_type, $input = null) {
    $messages = [
        'empty_input' => 'Please enter an ORCID ID or DOI to begin analysis.',
        'invalid_format' => 'Input format not recognised. Please enter a valid ORCID ID or DOI.',
        'invalid_orcid' => 'The ORCID ID format is invalid. Expected format: 0000-0000-0000-000X',
        'invalid_orcid_checksum' => 'The ORCID ID checksum is invalid. Please verify the ORCID ID.',
        'invalid_doi' => 'The DOI format is invalid. Expected format: 10.xxxx/xxxxx',
        'session_not_found' => 'Analysis session not found or expired. Please start a new analysis.',
        'api_error' => 'Unable to connect to analysis service. Please try again later.',
        'network_error' => 'Network error occurred. Please check your connection and try again.'
    ];
    
    return $messages[$error_type] ?? 'An unknown validation error occurred.';
}

/**
 * Get suggestions untuk validation errors
 * NEW FUNCTION - enhanced UX
 */
function getErrorSuggestions($error_message) {
    if (strpos($error_message, 'ORCID') !== false) {
        return [
            'Check that all digits are correct',
            'ORCID format: 0000-0000-0000-0000',
            'Try copying from your ORCID profile'
        ];
    }
    
    if (strpos($error_message, 'DOI') !== false) {
        return [
            'DOI format: 10.xxxx/xxxxx',
            'Copy DOI from the article page',
            'Remove any extra text or spaces'
        ];
    }
    
    return [
        'Enter an ORCID ID (0000-0000-0000-0000)',
        'Or enter a DOI (10.xxxx/xxxxx)',
        'Check for typos in your input'
    ];
}

/**
 * Handle validation errors dengan user-friendly messages
 * NEW FUNCTION - enhanced error handling
 */
function handleValidationError($error_type, $context = []) {
    $error_data = [
        'type' => $error_type,
        'message' => getValidationErrorMessage($error_type),
        'context' => $context,
        'timestamp' => date('c'),
        'suggestions' => getErrorSuggestions($error_type)
    ];
    
    // Log error jika dalam development mode
    if (getConfig('DEBUG_MODE', false)) {
        error_log('Validation Error: ' . json_encode($error_data));
    }
    
    return $error_data;
}

?>