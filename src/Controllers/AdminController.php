<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;
use Sangia\Database\Connection;

class AdminController extends BaseController
{
    private const REVOKE_FILE = __DIR__ . '/../../writable/revoked_keys.txt'; // fallback

    public function revokeKey(): void
    {
        $body = $this->jsonBody();
        $key  = trim($body['key'] ?? '');

        if (empty($key)) {
            Response::json(['status' => 'error', 'message' => 'key is required'], 400);
        }

        $hash   = hash('sha256', $key);
        $parts  = explode('_', $key, 4);
        $userId = $parts[1] ?? 'unknown';

        $pdo = Connection::get();
        if ($pdo !== null) {
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'pgsql') {
                $sql = 'INSERT INTO api_keys (key_hash, user_id, revoked_at)
                        VALUES (?, ?, NOW())
                        ON CONFLICT (key_hash) DO UPDATE SET revoked_at = NOW()';
            } else {
                $sql = 'INSERT INTO api_keys (key_hash, user_id, revoked_at)
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE revoked_at = NOW()';
            }
            $pdo->prepare($sql)->execute([$hash, $userId]);
        } else {
            // Fallback: write sha256 hash to file (read back by ApiKeyMiddleware)
            @mkdir(dirname(self::REVOKE_FILE), 0755, true);
            file_put_contents(self::REVOKE_FILE, $hash . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        Response::json(['status' => 'success', 'message' => 'Key revoked']);
    }
}
