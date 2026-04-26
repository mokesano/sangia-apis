<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;
use Sangia\Core\Modules\WizdamScore\WizdamScoreEngine;

class ImpactController extends BaseController
{
    public function calculate(): void
    {
        // ORCID fetch + Scopus + SDG analysis across up to 50 works
        set_time_limit(300);
        ignore_user_abort(true);

        $body     = $this->jsonBody();
        $orcid    = trim($body['orcid']     ?? '');
        $scopusId = trim($body['scopus_id'] ?? '') ?: null;
        $social   = $body['social']          ?? [];
        $economic = $body['economic']         ?? [];
        $refresh  = filter_var($body['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($orcid)) {
            Response::json(['status' => 'error', 'message' => 'orcid is required'], 400);
        }

        Response::json((new WizdamScoreEngine())->calculate($orcid, $scopusId, $social, $economic, $refresh));
    }
}
