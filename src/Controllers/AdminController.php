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
            // Try updating existing row first (row created by wizdam-sikola on key generation)
            $stmt = $pdo->prepare('UPDATE api_keys SET is_active = 0 WHERE key_hash = ?');
            $stmt->execute([$hash]);
            if ($stmt->rowCount() === 0) {
                // Key not registered yet — insert it as revoked so isRevoked() will find it in DB
                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                if ($driver === 'pgsql') {
                    $pdo->prepare('INSERT INTO api_keys (key_hash, is_active) VALUES (?, 0)
                                   ON CONFLICT (key_hash) DO UPDATE SET is_active = 0')
                        ->execute([$hash]);
                } else {
                    $pdo->prepare('INSERT INTO api_keys (key_hash, is_active) VALUES (?, 0)
                                   ON DUPLICATE KEY UPDATE is_active = 0')
                        ->execute([$hash]);
                }
            }
        } else {
            // No DB — write sha256 hash to file (read back by ApiKeyMiddleware)
            @mkdir(dirname(self::REVOKE_FILE), 0755, true);
            file_put_contents(self::REVOKE_FILE, $hash . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        Response::json(['status' => 'success', 'message' => 'Key revoked']);
    }
}
