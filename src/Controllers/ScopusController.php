<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;
use Sangia\Core\Modules\Scopus\ScopusModule;

class ScopusController extends BaseController
{
    public function author(): void
    {
        set_time_limit(45);

        $authorId = trim($_GET['authorid'] ?? '');
        $count    = min(25, max(1, (int) ($_GET['count'] ?? 10)));
        $refresh  = filter_var($_GET['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($authorId)) {
            Response::json(['status' => 'error', 'message' => 'authorid is required'], 400);
        }

        Response::json((new ScopusModule())->getAuthor($authorId, $count, $refresh));
    }
}
