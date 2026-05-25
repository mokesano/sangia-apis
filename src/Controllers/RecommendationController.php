<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;
use Sangia\Core\Modules\Recommendation\PolicyRecommendationEngine;

class RecommendationController extends BaseController
{
    public function policy(): void
    {
        set_time_limit(30);

        $body = $this->jsonBody();

        $stakeholderType   = trim($body['stakeholder_type']    ?? 'government');
        $domain            = trim($body['domain']              ?? 'general');
        $timeHorizon       = trim($body['time_horizon']        ?? 'medium');
        $region            = trim($body['region']              ?? '');
        $researchLandscape = $body['research_landscape']       ?? [];

        Response::json(
            (new PolicyRecommendationEngine())->generate(
                $stakeholderType,
                $domain,
                $timeHorizon,
                $region,
                $researchLandscape
            )
        );
    }
}
