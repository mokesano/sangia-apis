<?php
declare(strict_types=1);

namespace Sangia\Gateway;

use Sangia\Api\Database\Connection;

/**
 * HMAC-signed API key middleware.
 *
 * Key format:
 *   wz_{user_id}_{issued_ts}_{hmac16}
 *
 * Any ecosystem app (sangia-sikola, sdg-mapper, sdgs-analytics, sdg-mono) that
 * holds SANGIA_SHARED_SECRET can call generateKey() to mint a valid key.
 * sangia-apis validates by recomputing the HMAC — it does not care which app
 * generated the key, only that the HMAC matches the shared secret.
 *
 * Shared secret: SANGIA_SHARED_SECRET must be identical in ALL ecosystem .env files.
 * Revocation   : sha256(key) stored in api_keys table (DB), or writable/revoked_keys.txt (fallback)
 */
class ApiKeyMiddleware
{
    private const PREFIX      = 'wz_';
    private const KEY_TTL     = 31536000; // 1 year in seconds
    private const REVOKE_FILE = __DIR__ . '/../writable/revoked_keys.txt'; // legacy fallback

    public static function validate(): void
    {
        $key = self::extractKey();

        if ($key === null) {
            self::reject('API key missing. Include X-API-Key header or ?api_key= parameter.');
        }

        if (!self::isValid($key)) {
            self::reject('Invalid or expired API key.');
        }

        if (self::isRevoked($key)) {
            self::reject('API key has been revoked.');
        }
    }

    /** Returns the caller's user_id from the key, or null if invalid. */
    public static function getUserId(): ?string
    {
        $key = self::extractKey();
        if ($key === null || !self::isValid($key)) return null;
        $parts = explode('_', $key, 4);
        return $parts[1] ?? null;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private static function extractKey(): ?string
    {
        // Header takes precedence over query param
        $key = $_SERVER['HTTP_X_API_KEY']
            ?? $_SERVER['HTTP_AUTHORIZATION']  // "Bearer wz_..."
            ?? $_GET['api_key']
            ?? null;

        if ($key === null) return null;

        // Strip "Bearer " prefix if present
        if (str_starts_with($key, 'Bearer ')) {
            $key = substr($key, 7);
        }

        return str_starts_with($key, self::PREFIX) ? $key : null;
    }

    private static function isValid(string $key): bool
    {
        // wz_{user_id}_{issued_ts}_{hmac16}
        $parts = explode('_', $key, 4);
        if (count($parts) !== 4 || $parts[0] !== 'wz') return false;

        [, $userId, $issuedTs, $hmacPart] = $parts;

        if (!ctype_digit($issuedTs)) return false;
        if (time() - (int) $issuedTs > self::KEY_TTL) return false;

        $secret   = self::secret();
        $expected = substr(
            hash_hmac('sha256', $userId . ':' . $issuedTs, $secret),
            0, 16
        );

        return hash_equals($expected, $hmacPart);
    }

    private static function isRevoked(string $key): bool
    {
        $hash = hash('sha256', $key);

        $pdo = Connection::get();
        if ($pdo !== null) {
            // Unified schema: api_keys.is_active = 0 means revoked
            $stmt = $pdo->prepare(
                'SELECT 1 FROM api_keys WHERE key_hash = ? AND is_active = 0 LIMIT 1'
            );
            $stmt->execute([$hash]);
            return (bool) $stmt->fetchColumn();
        }

        // Fallback: file stores sha256 hashes (written by AdminController when DB unavailable)
        if (!file_exists(self::REVOKE_FILE)) return false;
        $list = file(self::REVOKE_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return in_array($hash, $list, true);
    }

    private static function secret(): string
    {
        $secret = $_ENV['SANGIA_SHARED_SECRET'] ?? getenv('SANGIA_SHARED_SECRET') ?: null;
        if (!$secret) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'code' => 500, 'message' => 'Server misconfiguration.']);
            exit;
        }
        return $secret;
    }

    private static function reject(string $message): never
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => 'error',
            'code'    => 401,
            'message' => $message,
        ]);
        exit;
    }

    // ── Key generation helper (can be called by any ecosystem app) ──────────

    /**
     * Generates a signed API key for a given user_id.
     * Any app that holds SANGIA_SHARED_SECRET can call this to mint a valid key.
     * Equivalent formula for non-PHP apps:
     *   prefix = "wz_"
     *   hmac16 = HMAC-SHA256(userId + ":" + timestamp, SANGIA_SHARED_SECRET)[0..15]
     *   key    = "wz_" + userId + "_" + timestamp + "_" + hmac16
     *
     * @param string $userId   User identifier (e.g. "42" or "user@email.com")
     * @param string $secret   The SANGIA_SHARED_SECRET (identical across all ecosystem apps)
     * @return string          API key string to give to the user
     */
    public static function generateKey(string $userId, string $secret): string
    {
        $ts   = (string) time();
        $hmac = substr(hash_hmac('sha256', $userId . ':' . $ts, $secret), 0, 16);
        return self::PREFIX . $userId . '_' . $ts . '_' . $hmac;
    }
}
