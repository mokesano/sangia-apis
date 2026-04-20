<?php
/**
 * Wizdam: SDG Definitions Configuration
 * EXACT COPY from SDG Frontend - maintaining complete consistency
 * 
 * @version 2.4 - Enhanced Modular
 * @author Rochmady and Wizdam Team
 * @license MIT
 * Last update: 2025-06-22
 */

// ==============================================
// DEFINISI SDG DENGAN SVG ICONS RESMI UN
// EXACT COPY dari SDG Frontend untuk konsistensi
// ==============================================
$SDG_DEFINITIONS = [
    'SDG1' => [
        'title' => 'No Poverty',
        'color' => '#e5243b',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_1.svg'
    ],
    'SDG2' => [
        'title' => 'Zero Hunger',
        'color' => '#dda63a',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_2.svg'
    ],
    'SDG3' => [
        'title' => 'Good Health and Well-being',
        'color' => '#4c9f38',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_3.svg'
    ],
    'SDG4' => [
        'title' => 'Quality Education',
        'color' => '#c5192d',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_4.svg'
    ],
    'SDG5' => [
        'title' => 'Gender Equality',
        'color' => '#ff3a21',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_5.svg'
    ],
    'SDG6' => [
        'title' => 'Clean Water and Sanitation',
        'color' => '#26bde2',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_6.svg'
    ],
    'SDG7' => [
        'title' => 'Affordable and Clean Energy',
        'color' => '#fcc30b',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_7.svg'
    ],
    'SDG8' => [
        'title' => 'Decent Work and Economic Growth',
        'color' => '#a21942',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_8.svg'
    ],
    'SDG9' => [
        'title' => 'Industry, Innovation and Infrastructure',
        'color' => '#fd6925',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_9.svg'
    ],
    'SDG10' => [
        'title' => 'Reduced Inequalities',
        'color' => '#dd1367',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_10.svg'
    ],
    'SDG11' => [
        'title' => 'Sustainable Cities and Communities',
        'color' => '#fd9d24',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_11.svg'
    ],
    'SDG12' => [
        'title' => 'Responsible Consumption and Production',
        'color' => '#bf8b2e',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_12.svg'
    ],
    'SDG13' => [
        'title' => 'Climate Action',
        'color' => '#3f7e44',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_13.svg'
    ],
    'SDG14' => [
        'title' => 'Life Below Water',
        'color' => '#0a97d9',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_14.svg'
    ],
    'SDG15' => [
        'title' => 'Life on Land',
        'color' => '#56c02b',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_15.svg'
    ],
    'SDG16' => [
        'title' => 'Peace, Justice and Strong Institutions',
        'color' => '#00689d',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_16.svg'
    ],
    'SDG17' => [
        'title' => 'Partnerships for the Goals',
        'color' => '#19486a',
        'svg_url' => 'https://assets.sangia.org/img/SDGs_icon_SVG/Artboard_17.svg'
    ]
];

// ==============================================
// HELPER FUNCTIONS UNTUK SDG DEFINITIONS
// ==============================================

/**
 * Get SDG definition by code
 * @param string $sdg_code SDG code (e.g., 'SDG1')
 * @return array|null SDG definition or null if not found
 */
function getSdgDefinition($sdg_code) {
    global $SDG_DEFINITIONS;
    return isset($SDG_DEFINITIONS[$sdg_code]) ? $SDG_DEFINITIONS[$sdg_code] : null;
}

/**
 * Get all SDG definitions
 * @return array All SDG definitions
 */
function getAllSdgDefinitions() {
    global $SDG_DEFINITIONS;
    return $SDG_DEFINITIONS;
}

/**
 * Get SDG color by code
 * @param string $sdg_code SDG code (e.g., 'SDG1')
 * @return string|null SDG color or null if not found
 */
function getSdgColor($sdg_code) {
    global $SDG_DEFINITIONS;
    return isset($SDG_DEFINITIONS[$sdg_code]) ? $SDG_DEFINITIONS[$sdg_code]['color'] : null;
}

/**
 * Get SDG title by code
 * @param string $sdg_code SDG code (e.g., 'SDG1')
 * @return string|null SDG title or null if not found
 */
function getSdgTitle($sdg_code) {
    global $SDG_DEFINITIONS;
    return isset($SDG_DEFINITIONS[$sdg_code]) ? $SDG_DEFINITIONS[$sdg_code]['title'] : null;
}

/**
 * Get SDG SVG URL by code
 * @param string $sdg_code SDG code (e.g., 'SDG1')
 * @return string|null SDG SVG URL or null if not found
 */
function getSdgSvgUrl($sdg_code) {
    global $SDG_DEFINITIONS;
    return isset($SDG_DEFINITIONS[$sdg_code]) ? $SDG_DEFINITIONS[$sdg_code]['svg_url'] : null;
}

/**
 * Generate SDG badge HTML
 * @param string $sdg_code SDG code (e.g., 'SDG1')
 * @param string $additional_classes Additional CSS classes
 * @return string HTML for SDG badge
 */
function generateSdgBadge($sdg_code, $additional_classes = '') {
    $definition = getSdgDefinition($sdg_code);
    if (!$definition) {
        return '';
    }
    
    $color = $definition['color'];
    $title = $definition['title'];
    $svg_url = $definition['svg_url'];
    
    return sprintf(
        '<div class="sdg-badge %s" style="background-color: %s;" title="%s" data-sdg="%s">
            <img src="%s" alt="%s" class="sdg-icon" loading="lazy">
            <span class="sdg-code">%s</span>
        </div>',
        $additional_classes,
        $color,
        htmlspecialchars($title),
        $sdg_code,
        $svg_url,
        htmlspecialchars($title),
        $sdg_code
    );
}

/**
 * Generate SDG colors array for charts
 * @return array Array of SDG colors
 */
function getSdgColorsArray() {
    global $SDG_DEFINITIONS;
    $colors = [];
    
    foreach ($SDG_DEFINITIONS as $sdg_code => $definition) {
        $colors[$sdg_code] = $definition['color'];
    }
    
    return $colors;
}

/**
 * Generate SDG titles array
 * @return array Array of SDG titles
 */
function getSdgTitlesArray() {
    global $SDG_DEFINITIONS;
    $titles = [];
    
    foreach ($SDG_DEFINITIONS as $sdg_code => $definition) {
        $titles[$sdg_code] = $definition['title'];
    }
    
    return $titles;
}

/**
 * Validate SDG code
 * @param string $sdg_code SDG code to validate
 * @return bool True if valid, false otherwise
 */
function isValidSdgCode($sdg_code) {
    global $SDG_DEFINITIONS;
    return isset($SDG_DEFINITIONS[$sdg_code]);
}

/**
 * Get SDG number from code
 * @param string $sdg_code SDG code (e.g., 'SDG1')
 * @return int|null SDG number or null if invalid
 */
function getSdgNumber($sdg_code) {
    if (preg_match('/^SDG(\d+)$/', $sdg_code, $matches)) {
        return intval($matches[1]);
    }
    return null;
}

/**
 * Get SDG code from number
 * @param int $sdg_number SDG number (1-17)
 * @return string|null SDG code or null if invalid
 */
function getSdgCodeFromNumber($sdg_number) {
    if ($sdg_number >= 1 && $sdg_number <= 17) {
        return 'SDG' . $sdg_number;
    }
    return null;
}

// ==============================================
// CSS GENERATION FOR SDG STYLES
// ==============================================

/**
 * Generate CSS for SDG colors
 * @return string CSS rules for SDG colors
 */
function generateSdgCss() {
    global $SDG_DEFINITIONS;
    $css = "/* Auto-generated SDG Colors CSS */\n";
    
    foreach ($SDG_DEFINITIONS as $sdg_code => $definition) {
        $color = $definition['color'];
        $sdg_number = getSdgNumber($sdg_code);
        
        $css .= sprintf(
            ".sdg-%d, .%s { background-color: %s !important; }\n",
            $sdg_number,
            strtolower($sdg_code),
            $color
        );
        
        $css .= sprintf(
            ".sdg-%d-border, .%s-border { border-color: %s !important; }\n",
            $sdg_number,
            strtolower($sdg_code),
            $color
        );
        
        $css .= sprintf(
            ".sdg-%d-text, .%s-text { color: %s !important; }\n",
            $sdg_number,
            strtolower($sdg_code),
            $color
        );
    }
    
    return $css;
}

// ==============================================
// JAVASCRIPT GENERATION FOR SDG DATA
// ==============================================

/**
 * Generate JavaScript object for SDG definitions
 * @return string JavaScript object
 */
function generateSdgJavaScript() {
    global $SDG_DEFINITIONS;
    return 'const SDG_DEFINITIONS = ' . json_encode($SDG_DEFINITIONS, JSON_PRETTY_PRINT) . ';';
}

// ==============================================
// EXPORT FUNCTIONS
// ==============================================

/**
 * Export SDG definitions as JSON
 * @return string JSON string
 */
function exportSdgDefinitionsAsJson() {
    global $SDG_DEFINITIONS;
    return json_encode($SDG_DEFINITIONS, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Export SDG definitions as XML
 * @return string XML string
 */
function exportSdgDefinitionsAsXml() {
    global $SDG_DEFINITIONS;
    
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<sdg_definitions>\n";
    
    foreach ($SDG_DEFINITIONS as $sdg_code => $definition) {
        $xml .= sprintf(
            "  <sdg code=\"%s\">\n    <title>%s</title>\n    <color>%s</color>\n    <svg_url>%s</svg_url>\n  </sdg>\n",
            $sdg_code,
            htmlspecialchars($definition['title']),
            $definition['color'],
            htmlspecialchars($definition['svg_url'])
        );
    }
    
    $xml .= "</sdg_definitions>";
    return $xml;
}

// ==============================================
// MAKE DEFINITIONS GLOBALLY ACCESSIBLE
// ==============================================
$GLOBALS['SDG_DEFINITIONS'] = $SDG_DEFINITIONS;
?>