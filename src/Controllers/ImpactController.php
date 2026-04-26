<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;
use Sangia\Core\Modules\WizdamScore\WizdamScoreEngine;

class ImpactController extends BaseController
{
    public function calculate(): void
    {
        set_time_limit(60);
        ignore_user_abort(true);

        $body     = $this->jsonBody();
        $orcid    = trim($body['orcid']      ?? '');
        $scopusId = trim($body['scopus_id']  ?? '') ?: null;
        $social   = $body['social']           ?? [];
        $economic = $body['economic']         ?? [];
        $refresh  = filter_var($body['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $offset   = max(0, (int) ($body['offset']     ?? $_GET['offset']     ?? 0));
        $batch    = max(1, min(50, (int) ($body['batch_size'] ?? $_GET['batch_size'] ?? 20)));
        $weights  = $body['weights']          ?? [];

        // Wizdam Sikola supplies pre-fetched data from its DB to avoid redundant cURL
        $suppliedWorks  = $body['supplied_works']                         ?? [];
        $suppliedPerson = isset($body['supplied_person']) ? $body['supplied_person'] : null;
        $suppliedScopus = isset($body['supplied_scopus']) ? $body['supplied_scopus'] : null;

        if (empty($orcid)) {
            Response::json(['status' => 'error', 'message' => 'orcid is required'], 400);
            return;
        }

        Response::json(
            (new WizdamScoreEngine())->calculate(
                $orcid, $scopusId, $social, $economic,
                $refresh, $batch, $offset, $weights,
                $suppliedWorks, $suppliedPerson, $suppliedScopus
            )
        );
    }
}
