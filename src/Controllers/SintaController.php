<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;
use Sangia\Core\Modules\Sinta\SintaModule;

class SintaController extends BaseController
{
    public function score(): void
    {
        set_time_limit(45);

        $issn    = trim($_GET['issn']    ?? '');
        $refresh = filter_var($_GET['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($issn)) {
            Response::json(['status' => 'error', 'message' => 'issn is required'], 400);
        }

        Response::json((new SintaModule())->getScore($issn, $refresh));
    }
}
