<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;
use Sangia\Core\Modules\Trend\TrendAnalysisEngine;

class TrendController extends BaseController
{
    public function analyze(): void
    {
        set_time_limit(120);
        ignore_user_abort(true);

        $body = $this->jsonBody();

        $orcid         = trim($body['orcid']          ?? '');
        $analysisType  = trim($body['analysis_type']  ?? 'impact_trajectory');
        $timeRange     = trim($body['time_range']      ?? '5y');
        $scopusId      = trim($body['scopus_id']       ?? '') ?: null;
        $refresh       = filter_var($body['refresh']   ?? false, FILTER_VALIDATE_BOOLEAN);
        $suppliedWorks = $body['supplied_works']        ?? [];
        $suppliedScopus = isset($body['supplied_scopus']) ? $body['supplied_scopus'] : null;

        if (empty($orcid)) {
            Response::json(['status' => 'error', 'message' => 'orcid is required'], 400);
            return;
        }

        Response::json(
            (new TrendAnalysisEngine())->analyze(
                $orcid,
                $analysisType,
                $timeRange,
                $suppliedWorks,
                $scopusId,
                $suppliedScopus,
                $refresh
            )
        );
    }
}
