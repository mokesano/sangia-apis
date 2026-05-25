<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\SDG\Config;

/**
 * Per-version weight configuration for the SDG classifier.
 *
 * Each version introduced a different scoring algorithm:
 *  v0  — keyword frequency only (legacy)
 *  v1  — keyword + cosine similarity
 *  v2  — v1 with richer bilingual dictionary
 *  v3  — v2 + contributor-type classification
 *  v4  — v3 + substantive-contribution & causal-relationship analysis
 *  v5  — v4 with boosted causal weight (current stable)
 *  v5e — v5 + metadata-enrichment (Enhanced, experimental)
 */
class VersionConfig
{
    /** @return array{keyword:float, similarity:float, substantive:float, causal:float, thresholds:array} */
    public static function get(string $version): array
    {
        return match ($version) {
            'v0' => [
                'keyword'     => 1.00,
                'similarity'  => 0.00,
                'substantive' => 0.00,
                'causal'      => 0.00,
                'thresholds'  => ['min' => 0.10, 'confidence' => 0.20, 'high' => 0.50],
                'label'       => 'Keyword-only (v1.1.7 legacy)',
            ],
            'v1' => [
                'keyword'     => 0.50,
                'similarity'  => 0.50,
                'substantive' => 0.00,
                'causal'      => 0.00,
                'thresholds'  => ['min' => 0.15, 'confidence' => 0.25, 'high' => 0.55],
                'label'       => 'Keyword + cosine similarity (v1.x)',
            ],
            'v2' => [
                'keyword'     => 0.45,
                'similarity'  => 0.45,
                'substantive' => 0.05,
                'causal'      => 0.05,
                'thresholds'  => ['min' => 0.18, 'confidence' => 0.27, 'high' => 0.55],
                'label'       => 'Enhanced bilingual dictionary (v2.1.7)',
            ],
            'v3' => [
                'keyword'     => 0.40,
                'similarity'  => 0.40,
                'substantive' => 0.10,
                'causal'      => 0.10,
                'thresholds'  => ['min' => 0.20, 'confidence' => 0.28, 'high' => 0.58],
                'label'       => 'Contributor-type classification (v3.1.7)',
            ],
            'v4' => [
                'keyword'     => 0.35,
                'similarity'  => 0.30,
                'substantive' => 0.20,
                'causal'      => 0.15,
                'thresholds'  => ['min' => 0.20, 'confidence' => 0.30, 'high' => 0.60],
                'label'       => 'Substantive & causal analysis (v4.1.7)',
            ],
            'v5e' => [
                'keyword'     => 0.25,
                'similarity'  => 0.30,
                'substantive' => 0.25,
                'causal'      => 0.20,
                'thresholds'  => ['min' => 0.20, 'confidence' => 0.30, 'high' => 0.60],
                'label'       => 'Metadata-enhanced (v5.2.0 experimental)',
            ],
            // v5 is the default / stable
            default => [
                'keyword'     => 0.30,
                'similarity'  => 0.30,
                'substantive' => 0.20,
                'causal'      => 0.20,
                'thresholds'  => ['min' => 0.20, 'confidence' => 0.30, 'high' => 0.60],
                'label'       => 'Causal-boosted stable (v5.1.8)',
            ],
        };
    }

    public static function versions(): array
    {
        return ['v0', 'v1', 'v2', 'v3', 'v4', 'v5', 'v5e'];
    }

    /**
     * Get all available versions with their configurations
     */
    public static function getAllVersions(): array
    {
        $versions = [];
        foreach (self::versions() as $version) {
            $versions[$version] = self::get($version);
        }
        return $versions;
    }
}
