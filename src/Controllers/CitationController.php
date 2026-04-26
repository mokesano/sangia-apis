<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;
use Sangia\Core\Modules\Citation\CitationModule;

class CitationController extends BaseController
{
    public function doi(): void
    {
        // 5 sequential external API calls — allow adequate time
        set_time_limit(120);
        ignore_user_abort(true);

        $doi     = trim($_GET['doi']     ?? '');
        $limit   = min(50, max(1, (int) ($_GET['limit'] ?? 15)));
        $refresh = filter_var($_GET['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($doi)) {
            Response::json(['status' => 'error', 'message' => 'doi is required'], 400);
        }

        Response::json((new CitationModule())->getCitations($doi, $limit, $refresh));
    }
}
