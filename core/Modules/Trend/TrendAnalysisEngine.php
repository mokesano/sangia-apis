<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\Trend;

use Sangia\Core\Modules\ORCID\OrcidModule;
use Sangia\Core\Modules\Scopus\ScopusModule;
use Sangia\Core\Modules\SDG\Services\SdgAnalyzer;
use Sangia\Core\Modules\SDG\Services\SdgClassifier;
use Sangia\Core\Modules\SDG\Services\Evaluator\LevelV4Evaluator;
use Sangia\Core\Modules\SDG\Config\SdgDictionary;

/**
 * Trend Analysis Engine — API #7
 *
 * Analyzes research trends over time for a researcher or institution.
 * Stateless: no result caching. Sangia Scieco owns all persistence.
 *
 * Supported analysis types:
 *   impact_trajectory    — publication & citation growth over time
 *   sdg_evolution        — SDG contribution changes per year
 *   collaboration_network — co-authorship patterns
 *   citation_growth      — citation accumulation by year
 *
 * Pass $suppliedWorks (with publication_year) from Sangia Scieco DB to skip ORCID cURL.
 */
class TrendAnalysisEngine
{
    private OrcidModule  $orcidModule;
    private ScopusModule $scopusModule;
    private SdgAnalyzer  $sdgAnalyzer;

    public function __construct()
    {
        $this->orcidModule  = new OrcidModule();
        $this->scopusModule = new ScopusModule();

        $dictionary        = new SdgDictionary();
        $classifier        = new SdgClassifier($dictionary);
        $evaluator         = new LevelV4Evaluator();
        $this->sdgAnalyzer = new SdgAnalyzer($classifier, $evaluator, $dictionary);
    }

    /**
     * @param string      $orcid          ORCID iD
     * @param string      $analysisType   One of the supported analysis types
     * @param string      $timeRange      e.g. '5y', '3y', '10y'
     * @param array       $suppliedWorks  Works from Sangia Scieco DB — skips ORCID cURL
     * @param string|null $scopusId       Scopus Author ID for citation data
     * @param array|null  $suppliedScopus Scopus data from Sangia Scieco DB
     * @param bool        $refresh        Force re-fetch from external APIs
     */
    public function analyze(
        string  $orcid,
        string  $analysisType   = 'impact_trajectory',
        string  $timeRange      = '5y',
        array   $suppliedWorks  = [],
        ?string $scopusId       = null,
        ?array  $suppliedScopus = null,
        bool    $refresh        = false
    ): array {
        $orcid = trim($orcid);
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
            return $this->error(400, "Invalid ORCID: $orcid");
        }

        // Resolve works
        $works      = [];
        $dataSource = 'sangia_scieco_db';
        $rawData    = null;

        if (!$refresh && !empty($suppliedWorks)) {
            $works = $suppliedWorks;
        } else {
            $profile = $this->orcidModule->getProfile($orcid, $refresh, 200);
            if (($profile['status'] ?? '') === 'error') {
                return $this->error(502, $profile['message'] ?? 'ORCID fetch failed');
            }
            $works      = $profile['works'] ?? [];
            $dataSource = 'orcid_api';
            $rawData    = $profile['raw_data'] ?? null;
        }

        $startYear = $this->parseStartYear($timeRange);

        $result = match ($analysisType) {
            'impact_trajectory'    => $this->analyzeImpactTrajectory($works, $startYear, $scopusId, $suppliedScopus, $refresh),
            'sdg_evolution'        => $this->analyzeSdgEvolution($works, $startYear),
            'collaboration_network'=> $this->analyzeCollaborationNetwork($works, $startYear),
            'citation_growth'      => $this->analyzeCitationGrowth($works, $startYear, $scopusId, $suppliedScopus, $refresh),
            default                => $this->error(400, "Invalid analysis_type: $analysisType. Supported: impact_trajectory, sdg_evolution, collaboration_network, citation_growth"),
        };

        if (($result['status'] ?? '') === 'error') {
            return $result;
        }

        return array_merge($result, [
            'orcid'       => $orcid,
            'analysis_type' => $analysisType,
            'time_range'  => $timeRange,
            'data_source' => $dataSource,
            'api_version' => 'v1.0-trend',
        ]) + ($rawData ? ['raw_data' => $rawData] : []);
    }

    // ── Analysis methods ──────────────────────────────────────────────────────

    private function analyzeImpactTrajectory(
        array   $works,
        int     $startYear,
        ?string $scopusId,
        ?array  $suppliedScopus,
        bool    $refresh
    ): array {
        $yearlyPubs = $this->groupWorksByYear($works, $startYear);

        // Cumulative publication count trend
        $pubCounts  = array_map(fn($y) => count($y), $yearlyPubs);
        $pubTrend   = $this->calculateGrowthTrend($pubCounts);

        // Scopus citation data if available
        $citationData = [];
        if ($scopusId) {
            $scopus = !$refresh && $suppliedScopus !== null
                ? $suppliedScopus
                : $this->scopusModule->getAuthor($scopusId, 25, $refresh);

            $pubs         = $scopus['publications'] ?? [];
            $citationData = $this->groupCitationsByYear($pubs, $startYear);
        }

        $citTrend = !empty($citationData) ? $this->calculateGrowthTrend(array_map(fn($y) => array_sum(array_column($y, 'cited_by_count')), $citationData)) : null;

        $yearlyMetrics = [];
        foreach (array_keys($yearlyPubs) as $year) {
            $yearCitations = array_sum(array_column($citationData[$year] ?? [], 'cited_by_count'));
            $yearlyMetrics[(string) $year] = [
                'year'             => $year,
                'publications'     => count($yearlyPubs[$year]),
                'citations'        => $yearCitations,
                'cumulative_pubs'  => array_sum(array_map(fn($y) => count($y), array_filter($yearlyPubs, fn($k) => $k <= $year, ARRAY_FILTER_USE_KEY))),
            ];
        }

        return [
            'status'         => 'success',
            'yearly_metrics' => array_values($yearlyMetrics),
            'trends'         => [
                'publication_trend' => $pubTrend,
                'citation_trend'    => $citTrend,
            ],
            'summary'        => [
                'total_publications'    => count($works),
                'years_active'          => count($yearlyPubs),
                'most_productive_year'  => $this->mostProductiveYear($yearlyPubs),
                'avg_pubs_per_year'     => count($yearlyPubs) > 0 ? round(count($works) / count($yearlyPubs), 1) : 0,
            ],
        ];
    }

    private function analyzeSdgEvolution(array $works, int $startYear): array
    {
        $yearlyWorks = $this->groupWorksByYear($works, $startYear);
        $sdgByYear   = [];

        foreach ($yearlyWorks as $year => $yearWorks) {
            $sdgCounts = [];
            foreach ($yearWorks as $work) {
                try {
                    $analysis = $this->sdgAnalyzer->analyzeWork(
                        $work['title'] ?? '',
                        $work['abstract'] ?? ''
                    );
                } catch (\Throwable) {
                    continue;
                }
                foreach ($analysis['sdg_confidence'] ?? [] as $sdgCode => $score) {
                    $sdgNumber = (int) preg_replace('/\D/', '', $sdgCode);
                    if (!isset($sdgCounts[$sdgNumber])) {
                        $sdgCounts[$sdgNumber] = ['count' => 0, 'total_score' => 0.0];
                    }
                    $sdgCounts[$sdgNumber]['count']++;
                    $sdgCounts[$sdgNumber]['total_score'] += (float) $score;
                }
            }

            $sdgByYear[(string) $year] = array_map(fn($n, $d) => [
                'sdg'          => $n,
                'count'        => $d['count'],
                'avg_score'    => round($d['total_score'] / $d['count'], 3),
            ], array_keys($sdgCounts), $sdgCounts);
        }

        // Identify dominant and emerging SDGs
        $allSdgTotals = [];
        $firstHalfSdgs = [];
        $secondHalfSdgs = [];
        $years = array_keys($sdgByYear);
        $midpoint = (int) (count($years) / 2);

        foreach ($sdgByYear as $year => $sdgs) {
            $yearIndex = array_search($year, $years);
            foreach ($sdgs as $sdgData) {
                $n = $sdgData['sdg'];
                $allSdgTotals[$n] = ($allSdgTotals[$n] ?? 0) + $sdgData['count'];
                if ($yearIndex < $midpoint) $firstHalfSdgs[$n]  = ($firstHalfSdgs[$n]  ?? 0) + $sdgData['count'];
                else                        $secondHalfSdgs[$n] = ($secondHalfSdgs[$n] ?? 0) + $sdgData['count'];
            }
        }

        arsort($allSdgTotals);
        $dominant = array_slice(array_keys($allSdgTotals), 0, 5);

        $emerging = [];
        foreach ($secondHalfSdgs as $n => $count) {
            $growth = $count - ($firstHalfSdgs[$n] ?? 0);
            if ($growth > 0) $emerging[$n] = $growth;
        }
        arsort($emerging);
        $emerging = array_slice(array_keys($emerging), 0, 3);

        return [
            'status'        => 'success',
            'sdg_by_year'   => $sdgByYear,
            'dominant_sdgs' => $dominant,
            'emerging_sdgs' => $emerging,
            'summary'       => [
                'total_works_analyzed' => count($works),
                'years_covered'        => count($sdgByYear),
                'unique_sdgs_touched'  => count($allSdgTotals),
            ],
        ];
    }

    private function analyzeCollaborationNetwork(array $works, int $startYear): array
    {
        $yearlyWorks = $this->groupWorksByYear($works, $startYear);
        $coAuthors   = [];
        $yearlyNewCollabs = [];

        foreach ($yearlyWorks as $year => $yearWorks) {
            $yearlyNewCollabs[(string) $year] = 0;
            foreach ($yearWorks as $work) {
                $authors = $this->extractAuthors($work);
                foreach ($authors as $author) {
                    if (!isset($coAuthors[$author])) {
                        $coAuthors[$author] = ['count' => 0, 'first_year' => $year];
                        $yearlyNewCollabs[(string) $year]++;
                    }
                    $coAuthors[$author]['count']++;
                    $coAuthors[$author]['last_year'] = $year;
                }
            }
        }

        arsort($coAuthors);
        $topCollaborators = array_slice(
            array_map(fn($name, $data) => [
                'name'         => $name,
                'collaboration_count' => $data['count'],
                'first_year'   => $data['first_year'],
                'last_year'    => $data['last_year'] ?? $data['first_year'],
            ], array_keys($coAuthors), $coAuthors),
            0, 10
        );

        $repeatRate = count($coAuthors) > 0
            ? round(count(array_filter($coAuthors, fn($d) => $d['count'] > 1)) / count($coAuthors) * 100, 1)
            : 0;

        return [
            'status'             => 'success',
            'network_stats'      => [
                'total_collaborators'   => count($coAuthors),
                'repeat_collab_rate'    => $repeatRate,
                'avg_authors_per_work'  => count($works) > 0 ? round(array_sum(array_map(fn($w) => count($this->extractAuthors($w)), $works)) / count($works), 1) : 0,
            ],
            'yearly_new_collaborators' => $yearlyNewCollabs,
            'top_collaborators'  => $topCollaborators,
            'summary'            => [
                'total_works'    => count($works),
                'years_covered'  => count($yearlyWorks),
            ],
        ];
    }

    private function analyzeCitationGrowth(
        array   $works,
        int     $startYear,
        ?string $scopusId,
        ?array  $suppliedScopus,
        bool    $refresh
    ): array {
        if (!$scopusId) {
            return $this->error(400, 'scopus_id is required for citation_growth analysis');
        }

        $scopus = !$refresh && $suppliedScopus !== null
            ? $suppliedScopus
            : $this->scopusModule->getAuthor($scopusId, 25, $refresh);

        $pubs         = $scopus['publications'] ?? [];
        $citsByYear   = $this->groupCitationsByYear($pubs, $startYear);

        $yearlyMetrics = [];
        $cumulative    = 0;
        foreach ($citsByYear as $year => $yearPubs) {
            $yearCitations = array_sum(array_column($yearPubs, 'cited_by_count'));
            $cumulative   += $yearCitations;
            $yearlyMetrics[] = [
                'year'               => $year,
                'new_citations'      => $yearCitations,
                'cumulative'         => $cumulative,
                'publications_count' => count($yearPubs),
            ];
        }

        $citCounts = array_column($yearlyMetrics, 'new_citations');
        $trend     = $this->calculateGrowthTrend($citCounts);

        return [
            'status'         => 'success',
            'yearly_metrics' => $yearlyMetrics,
            'trend'          => $trend,
            'summary'        => [
                'total_citations'     => $cumulative,
                'peak_citation_year'  => !empty($citCounts) ? ($yearlyMetrics[array_search(max($citCounts), $citCounts)]['year'] ?? null) : null,
                'h_index'             => (int) ($scopus['author']['h_index'] ?? 0),
            ],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function groupWorksByYear(array $works, int $startYear): array
    {
        $grouped = [];
        foreach ($works as $work) {
            $year = (int) ($work['publication_year'] ?? $work['year'] ?? 0);
            if ($year < $startYear || $year === 0) continue;
            $grouped[$year][] = $work;
        }
        ksort($grouped);
        return $grouped;
    }

    private function groupCitationsByYear(array $publications, int $startYear): array
    {
        $grouped = [];
        foreach ($publications as $pub) {
            $year = (int) ($pub['year'] ?? 0);
            if ($year < $startYear || $year === 0) continue;
            $grouped[$year][] = $pub;
        }
        ksort($grouped);
        return $grouped;
    }

    private function calculateGrowthTrend(array $values): array
    {
        if (count($values) < 2) {
            return ['direction' => 'insufficient_data', 'growth_rate' => 0, 'slope' => 0];
        }

        $n    = count($values);
        $xMean = ($n - 1) / 2;
        $yMean = array_sum($values) / $n;

        $numerator   = 0;
        $denominator = 0;
        foreach ($values as $i => $y) {
            $numerator   += ($i - $xMean) * ($y - $yMean);
            $denominator += ($i - $xMean) ** 2;
        }

        $slope      = $denominator > 0 ? $numerator / $denominator : 0;
        $first      = reset($values);
        $last       = end($values);
        $growthRate = $first > 0 ? round(($last - $first) / $first * 100, 1) : 0;
        $direction  = $slope > 0.1 ? 'increasing' : ($slope < -0.1 ? 'decreasing' : 'stable');

        return [
            'direction'        => $direction,
            'growth_rate_pct'  => $growthRate,
            'slope'            => round($slope, 3),
            'first_value'      => $first,
            'last_value'       => $last,
        ];
    }

    private function mostProductiveYear(array $yearlyPubs): ?int
    {
        if (empty($yearlyPubs)) return null;
        $counts = array_map('count', $yearlyPubs);
        return (int) array_key_first(array_filter($counts, fn($v) => $v === max($counts)));
    }

    private function extractAuthors(array $work): array
    {
        $raw = $work['authors_string'] ?? $work['contributors'] ?? '';
        if (empty($raw)) return [];
        return array_map('trim', preg_split('/[,;]/', $raw) ?: []);
    }

    private function parseStartYear(string $timeRange): int
    {
        if ($timeRange === 'all') return 1900;
        $years = (int) filter_var($timeRange, FILTER_SANITIZE_NUMBER_INT);
        return (int) date('Y') - max(1, $years ?: 5);
    }

    private function error(int $code, string $message): array
    {
        http_response_code($code);
        return ['status' => 'error', 'code' => $code, 'message' => $message];
    }
}
