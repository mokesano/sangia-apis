<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;
use Sangia\Core\Modules\Journal\JournalModule;

class JournalController extends BaseController
{
    public function metrics(): void
    {
        set_time_limit(30);

        $issn    = trim($_GET['issn']    ?? '');
        $refresh = filter_var($_GET['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($issn)) {
            Response::json(['status' => 'error', 'message' => 'issn is required'], 400);
        }

        Response::json((new JournalModule())->getMetrics($issn, $refresh));
    }
}
