<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;
use Sangia\Core\Modules\ORCID\OrcidModule;

class OrcidController extends BaseController
{
    public function profile(): void
    {
        set_time_limit(60);
        ignore_user_abort(true);

        $orcid   = trim($_GET['orcid']   ?? '');
        $refresh = filter_var($_GET['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $limit   = min(200, max(1, (int) ($_GET['limit'] ?? 50)));

        if (empty($orcid)) {
            Response::json(['status' => 'error', 'message' => 'orcid is required'], 400);
        }

        Response::json((new OrcidModule())->getProfile($orcid, $refresh, $limit));
    }
}
