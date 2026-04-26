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
        // SDG analysis on large ORCID profiles can take tens of seconds
        set_time_limit(120);
        ignore_user_abort(true);

        $body     = $this->jsonBody();
        $orcid    = trim($body['orcid']    ?? $_GET['orcid']   ?? '');
        $doi      = trim($body['doi']      ?? $_GET['doi']     ?? '');
        $title    = trim($body['title']    ?? '');
        $abstract = trim($body['abstract'] ?? '');
        $refresh  = filter_var($body['refresh'] ?? $_GET['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $versionCfg = VersionConfig::get($version);
        $dictionary = new SdgDictionary();
        $classifier = new SdgClassifier($dictionary);
        $evaluator  = new LevelV4Evaluator();
        $analyzer   = new SdgAnalyzer($classifier, $evaluator, $dictionary, $versionCfg);
        $api        = new SdgApi($analyzer);

        if ($orcid) {
            Response::json($api->handleOrcidRequest($orcid, $refresh));
        } elseif ($doi) {
            Response::json($api->handleDoiRequest($doi, $refresh));
        } elseif ($title || $abstract) {
            $result = $analyzer->analyzeWork($title, $abstract);
            Response::json(['status' => 'success', 'version' => $version, 'sdg_analysis' => $result]);
        } else {
            Response::json(['status' => 'error', 'message' => 'Provide orcid, doi, or title+abstract'], 400);
        }
    }

    public function versions(): void
    {
        $versions = [];
        foreach (VersionConfig::versions() as $v) {
            $cfg          = VersionConfig::get($v);
            $versions[$v] = [
                'label'   => $cfg['label'],
                'weights' => array_diff_key($cfg, ['label' => 1, 'thresholds' => 1]),
            ];
        }
        Response::json(['status' => 'success', 'data' => $versions]);
    }

    public function catalogue(): void
    {
        Response::json([
            'service'   => 'Sangia API Engine',
            'version'   => 'v1',
            'endpoints' => [
                'GET  /health'                  => 'Service health check (no key)',
                'GET  /api/v1/sdg/versions'     => 'List SDG analysis versions (no key)',
                'POST /api/v1/sdg/v0/classify'  => 'SDG v0 — keyword-only (v1.1.7)',
                'POST /api/v1/sdg/v1/classify'  => 'SDG v1 — keyword + similarity',
                'POST /api/v1/sdg/v2/classify'  => 'SDG v2 — bilingual dict (v2.1.7)',
                'POST /api/v1/sdg/v3/classify'  => 'SDG v3 — contributor types (v3.1.7)',
                'POST /api/v1/sdg/v4/classify'  => 'SDG v4 — substantive+causal (v4.1.7)',
                'POST /api/v1/sdg/v5/classify'  => 'SDG v5 — causal-boosted stable (v5.1.8)',
                'POST /api/v1/sdg/v5e/classify' => 'SDG v5e — metadata-enhanced (experimental)',
                'POST /api/v1/sdg/classify'     => 'SDG classify — alias for v5',
                'GET  /api/v1/scopus/author'    => 'Scopus author profile + publications',
                'GET  /api/v1/orcid/profile'    => 'ORCID researcher profile + works',
                'GET  /api/v1/citation/doi'     => 'Multi-source citation data for a DOI',
                'GET  /api/v1/journal/metrics'  => 'Scopus journal metrics (CiteScore, SJR, SNIP)',
                'GET  /api/v1/sinta/score'      => 'SINTA journal impact score',
                'POST /api/v1/impact/calculate' => 'Wizdam Impact Score (composite)',
                'POST /api/v1/admin/keys/revoke'=> 'Revoke an API key (service calls only)',
            ],
            'auth'     => 'X-API-Key: wz_{user_id}_{timestamp}_{hmac16}',
            'key_info' => 'Dapatkan API key di Wizdam Sikola → Profil → API Keys',
        ]);
    }
}
