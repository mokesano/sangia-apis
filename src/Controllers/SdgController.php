<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;
use Sangia\Core\Modules\SDG\Config\VersionConfig;
use Sangia\Core\Modules\SDG\Config\SdgDictionary;
use Sangia\Core\Modules\SDG\Controllers\SdgApi;
use Sangia\Core\Modules\SDG\Services\SdgAnalyzer;
use Sangia\Core\Modules\SDG\Services\SdgClassifier;
use Sangia\Core\Modules\SDG\Services\Evaluator\LevelV4Evaluator;

class SdgController extends BaseController
{
    public function classify(string $version): void
    {
        set_time_limit(60);
        ignore_user_abort(true);

        $body     = $this->jsonBody();
        $orcid    = trim($body['orcid']    ?? $_GET['orcid']   ?? '');
        $doi      = trim($body['doi']      ?? $_GET['doi']     ?? '');
        $title    = trim($body['title']    ?? '');
        $abstract = trim($body['abstract'] ?? '');
        $refresh  = filter_var($body['refresh'] ?? $_GET['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $offset   = max(0, (int) ($body['offset']     ?? $_GET['offset']     ?? 0));
        $batch    = max(1, min(50, (int) ($body['batch_size'] ?? $_GET['batch_size'] ?? 20)));

        // Sangia Sikola supplies pre-fetched works from its DB to avoid redundant cURL
        $suppliedWorks = $body['supplied_works'] ?? [];

        // Merge: VersionConfig defaults ← request-level weights from Sangia Sikola admin
        $versionCfg     = VersionConfig::get($version);
        $requestWeights = $body['weights'] ?? [];
        if (!empty($requestWeights)) {
            $versionCfg = array_merge($versionCfg, array_intersect_key($requestWeights, [
                'keyword' => 1, 'similarity' => 1, 'substantive' => 1, 'causal' => 1, 'max_sdgs' => 1,
            ]));
            if (isset($requestWeights['thresholds'])) {
                $versionCfg['thresholds'] = array_merge(
                    $versionCfg['thresholds'] ?? [],
                    $requestWeights['thresholds']
                );
            }
        }

        $dictionary = new SdgDictionary();
        $classifier = new SdgClassifier($dictionary);
        $evaluator  = new LevelV4Evaluator();
        $analyzer   = new SdgAnalyzer($classifier, $evaluator, $dictionary, $versionCfg);
        $api        = new SdgApi($analyzer);

        if ($orcid) {
            Response::json($api->handleOrcidRequest($orcid, $refresh, $batch, $offset, $suppliedWorks));
        } elseif ($doi) {
            Response::json($api->handleDoiRequest($doi, $refresh));
        } elseif ($title || $abstract) {
            $result = $analyzer->analyzeWork($title, $abstract);
            Response::json(['status' => 'success', 'version' => $version, 'weights_applied' => $versionCfg, 'sdg_analysis' => $result]);
        } else {
            Response::json(['status' => 'error', 'message' => 'Provide orcid, doi, title+abstract, or orcid+supplied_works'], 400);
        }
    }

    public function versions(): void
    {
        $versions = [];
        foreach (VersionConfig::versions() as $v) {
            $cfg          = VersionConfig::get($v);
            $versions[$v] = [
                'label'      => $cfg['label'],
                'weights'    => array_diff_key($cfg, ['label' => 1, 'thresholds' => 1]),
                'thresholds' => $cfg['thresholds'] ?? [],
            ];
        }
        Response::json(['status' => 'success', 'data' => $versions]);
    }

    public function catalogue(): void
    {
        Response::json([
            'service'    => 'Sangia API Engine',
            'version'    => 'v1',
            'endpoints'  => [
                'GET  /health'                         => 'Service health check (no key)',
                'GET  /api/v1/sdg/versions'            => 'List SDG versions + default weights (no key)',
                'POST /api/v1/sdg/v0/classify'         => 'SDG v0 — keyword-only',
                'POST /api/v1/sdg/v1/classify'         => 'SDG v1 — keyword + similarity',
                'POST /api/v1/sdg/v2/classify'         => 'SDG v2 — bilingual dictionary',
                'POST /api/v1/sdg/v3/classify'         => 'SDG v3 — contributor types',
                'POST /api/v1/sdg/v4/classify'         => 'SDG v4 — substantive + causal',
                'POST /api/v1/sdg/v5/classify'         => 'SDG v5 — causal-boosted (default stable)',
                'POST /api/v1/sdg/v5e/classify'        => 'SDG v5e — metadata-enhanced (experimental)',
                'POST /api/v1/sdg/classify'            => 'SDG classify — alias for v5',
                'GET  /api/v1/scopus/author'           => 'Scopus author profile + publications',
                'GET  /api/v1/orcid/profile'           => 'ORCID researcher profile + works',
                'GET  /api/v1/citation/doi'            => 'Multi-source citation data for a DOI',
                'GET  /api/v1/journal/metrics'         => 'Scopus journal metrics (CiteScore, SJR, SNIP)',
                'GET  /api/v1/sinta/score'             => 'SINTA journal impact score',
                'POST /api/v1/impact/calculate'        => 'Sangia Impact Score — composite (batched)',
                'POST /api/v1/trend/analyze'           => 'Trend Analysis — impact_trajectory | sdg_evolution | collaboration_network | citation_growth',
                'POST /api/v1/recommendation/policy'   => 'Policy Recommendations — government | institution | industry | researcher | community',
                'POST /api/v1/admin/keys/revoke'       => 'Revoke an API key (service calls only)',
            ],
            'supplied_data'   => 'All ORCID-based endpoints accept "supplied_works" and "supplied_scopus" in request body to skip external API calls when Sangia Sikola already has the data.',
            'raw_data'        => 'When sangia-apis fetches from external APIs, response includes "raw_data" for Sangia Sikola to persist in its database.',
            'weight_override' => 'All classify + impact endpoints accept a "weights" object to override scoring weights set in the Sangia Sikola admin panel.',
            'batch_info'      => 'ORCID-based endpoints support offset + batch_size to avoid timeout.',
            'auth'            => 'X-API-Key: sg_{user_id}_{timestamp}_{hmac16}',
            'key_source'      => 'sangia_sikola',
            'key_path'        => '/profile/api-keys',
        ]);
    }
}
