<?php
/**
 * Wizdam: SDG Classification Presentation Interface Configuration
 * Enhanced modular configuration maintaining Wizdam identity
 * 
 * @version 2.3 - Enhanced with Navbar, Footer, Chatbot & Back to Top
 * @author Rochmady and Wizdam Team
 * @license MIT
 * Last update: 2025-06-16
 */

// ==============================================
// SITE IDENTITY - MAINTAIN WIZDAM BRANDING
// ==============================================
define('SITE_NAME', 'Wizdam: SDG Classification Presentation Interface');
define('SITE_TITLE', 'Welcome! Wizdam AI-sikola');
define('SITE_DESCRIPTION', 'Advanced AI-powered platform for analyzing research contributions to Sustainable Development Goals');
define('SITE_URL', 'https://www.wizdam.sangia.org');
define('SITE_VERSION', '2.3');
define('SITE_AUTHOR', 'Rochmady and Wizdam Team');
define('SITE_GENERATOR', 'Wizdam AI v5.1.8');
define('LICENSE', 'MIT');
define('LAST_UPDATE', '2025-06-16');

// ==============================================
// ENVIRONMENT SETTINGS
// ==============================================
define('ENVIRONMENT', 'production'); // 'development' atau 'production'
define('DEBUG_MODE', false); // Set true untuk debugging

// Timezone
date_default_timezone_set('Asia/Jakarta');

// ==============================================
// API CONFIGURATION - FROM STANDALONE
// ==============================================
$API_BASE_URL = 'https://www.journals.sangia.org/api/sdg_v5';

// Enhanced API Configuration
$CONFIG = [
    // Primary API endpoint - MAINTAIN dari standalone
    'API_BASE_URL' => 'https://www.journals.sangia.org/api/sdg_v5',
    
    // External APIs
    'ORCID_API_URL' => 'https://pub.orcid.org/v3.0',
    'CROSSREF_API_URL' => 'https://api.crossref.org/works',
    'OPENALEX_API_URL' => 'https://api.openalex.org/works',
    
    // Timeout settings
    'TIMEOUT_CONNECT' => 10,
    'TIMEOUT_EXECUTE' => 120,
    'MAX_EXECUTION_TIME' => 300,
    
    // Cache settings
    'CACHE_TTL' => 3600, // 1 jam
    'CACHE_DIR' => __DIR__ . '/../cache',
    'ENABLE_CACHE' => true,
    
    // Limits
    'MAX_WORKS_LIMIT' => 100,
    'MAX_ORCID_WORKS' => 50,
    'API_RATE_LIMIT' => 60, // requests per minute
    
    // Performance
    'ENABLE_COMPRESSION' => true,
    'MEMORY_LIMIT' => '512M',
];

// ==============================================
// DATABASE CONFIGURATION (OPTIONAL)
// ==============================================
$DB_CONFIG = [
    'host' => 'localhost',
    'database' => 'wizdam_sdg_analysis',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'enabled' => false // Set true jika menggunakan database
];

// ==============================================
// SECURITY CONFIGURATION
// ==============================================
define('CSRF_TOKEN_NAME', '_token');
define('SESSION_TIMEOUT', 3600); // 1 jam

// Content Security Policy
$CSP_POLICY = [
    'default-src' => "'self'",
    'script-src' => "'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
    'style-src' => "'self* 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
    'font-src' => "'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
    'img-src' => "'self' data: https: blob:",
    'connect-src' => "'self' https://www.journals.sangia.org https://pub.orcid.org https://api.crossref.org",
];

// ==============================================
// LOGGING CONFIGURATION
// ==============================================
$LOG_CONFIG = [
    'enabled' => true,
    'level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
    'file' => __DIR__ . '/../logs/wizdam_app.log',
    'max_size' => 10485760, // 10MB
];

// ==============================================
// ERROR HANDLING
// ==============================================
if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// ==============================================
// UTILITY FUNCTIONS
// ==============================================

/**
 * Get configuration value with default fallback
 */
function getConfig($key, $default = null) {
    global $CONFIG;
    return isset($CONFIG[$key]) ? $CONFIG[$key] : $default;
}

/**
 * Check if feature is enabled
 */
function isFeatureEnabled($feature) {
    global $CONFIG;
    return isset($CONFIG[$feature]) && $CONFIG[$feature] === true;
}

/**
 * Get cache directory path
 */
function getCacheDir() {
    global $CONFIG;
    return $CONFIG['CACHE_DIR'];
}

/**
 * Get API base URL
 */
function getApiBaseUrl() {
    global $CONFIG;
    return $CONFIG['API_BASE_URL'];
}

// ==============================================
// CONSTANTS TAMBAHAN
// ==============================================
define('SUCCESS_CODE', 200);
define('ERROR_CODE', 500);
define('NOT_FOUND_CODE', 404);
define('UNAUTHORIZED_CODE', 401);

// File extensions yang diizinkan
define('ALLOWED_UPLOAD_EXTENSIONS', ['pdf', 'doc', 'docx', 'txt']);
define('MAX_UPLOAD_SIZE', 5242880); // 5MB

// Default values
define('DEFAULT_CACHE_TTL', 3600);
define('DEFAULT_TIMEOUT', 30);
define('DEFAULT_LIMIT', 20);

// Make API_BASE_URL globally accessible
$GLOBALS['API_BASE_URL'] = $API_BASE_URL;
?>