<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;

class AdminController extends BaseController
{
    private const REVOKE_FILE = __DIR__ . '/../../writable/revoked_keys.txt';

    public function revokeKey(): void
    {
        $body = $this->jsonBody();
        $key  = trim($body['key'] ?? '');

        if (empty($key)) {
            Response::json(['status' => 'error', 'message' => 'key is required'], 400);
        }

        @mkdir(dirname(self::REVOKE_FILE), 0755, true);
        file_put_contents(self::REVOKE_FILE, $key . PHP_EOL, FILE_APPEND | LOCK_EX);

        Response::json(['status' => 'success', 'message' => 'Key revoked']);
    }
}
