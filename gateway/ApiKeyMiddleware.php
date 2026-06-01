<?php
declare(strict_types=1);

namespace Sangia\Gateway;

use Sangia\Api\Database\Connection;

/**
 * HMAC-signed API key middleware.
 *
 * Key format:
 *   sg_{user_id}_{issued_ts}_{hmac16}
 *
 * Any ecosystem app (sangia-scieco, sciecola, sangia-analytics, sangia-mono) that
 * holds SANGIA_SHARED_SECRET can call generateKey() to mint a valid key.
 * sangia-apis validates by recomputing the HMAC — it does not care which app
 * generated the key, only that the HMAC matches the shared secret.
 *
 * Shared secret: SANGIA_SHARED_SECRET must be identical in ALL ecosystem .env files.
 * Revocation   : sha256(key) stored in api_keys table (DB), or writable/revoked_keys.txt (fallback)
 */
class ApiKeyMiddleware
{
    private const PREFIX             = 'sg_';
    /** @var list<string> */
    private const LEGACY_PREFIXES    = ['wz_'];
    /** @var list<string> */
    private const ACCEPTED_PREFIXES  = [self::PREFIX, ...self::LEGACY_PREFIXES];
    private const KEY_TTL            = 31536000; // 1 year in seconds
    private const FUTURE_SKEW_SECS   = 300; // allow small clock skew only
    private const MIN_SECRET_LENGTH  = 32;
    private const REVOKE_FILE        = __DIR__ . '/../writable/revoked_keys.txt'; // legacy fallback

    public static function validate(): void
    {
        $key = self::extractKey();

        if ($key === null) {
            self::reject('API key missing. Include X-API-Key or Authorization: Bearer header.');
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
        return self::parseKey($key)['user_id'] ?? null;
    }

    /** Returns true when a key uses a supported prefix and field shape. */
    public static function isWellFormed(string $key): bool
    {
        return self::parseKey($key) !== null;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private static function extractKey(): ?string
    {
        // Header takes precedence. Query-string API keys are disabled by default
        // because URLs are commonly logged by proxies, analytics, and browsers.
        $key = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        if ($key === null && self::allowQueryApiKey()) {
            $key = $_GET['api_key'] ?? null;
        }

        if (!is_string($key) || $key === '') return null;

        $key = trim($key);

        // Strip "Bearer " prefix if present (case-insensitive).
        if (preg_match('/^Bearer\s+(.+)$/i', $key, $matches) === 1) {
            $key = trim($matches[1]);
        }

        foreach (self::ACCEPTED_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return $key;
            }
        }

        return null;
    }

    private static function isValid(string $key): bool
    {
        $parsed = self::parseKey($key);
        if ($parsed === null) return false;

        $issuedAt = (int) $parsed['issued_ts'];
        $now      = time();

        if ($issuedAt > $now + self::FUTURE_SKEW_SECS) return false;
        if ($now - $issuedAt > self::KEY_TTL) return false;

        $expected = substr(
            hash_hmac('sha256', $parsed['user_id'] . ':' . $parsed['issued_ts'], self::secret()),
            0,
            16
        );

        return hash_equals($expected, $parsed['hmac']);
    }

    /** @return array{prefix:string,user_id:string,issued_ts:string,hmac:string}|null */
    private static function parseKey(string $key): ?array
    {
        if (preg_match('/^(sg|wz)_([A-Za-z0-9@.:-]{1,128})_([0-9]{10})_([a-f0-9]{16})$/', $key, $matches) !== 1) {
            return null;
        }

        $prefix = $matches[1] . '_';
        if (!in_array($prefix, self::ACCEPTED_PREFIXES, true)) {
            return null;
        }

        return [
            'prefix'    => $prefix,
            'user_id'   => $matches[2],
            'issued_ts' => $matches[3],
            'hmac'      => $matches[4],
        ];
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
        if (!is_string($secret) || strlen($secret) < self::MIN_SECRET_LENGTH) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'code' => 500, 'message' => 'Server misconfiguration.']);
            exit;
        }
        return $secret;
    }

    private static function allowQueryApiKey(): bool
    {
        return filter_var($_ENV['SANGIA_ALLOW_QUERY_API_KEY'] ?? getenv('SANGIA_ALLOW_QUERY_API_KEY') ?: false, FILTER_VALIDATE_BOOLEAN);
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
     *   prefix = "sg_"
     *   hmac16 = HMAC-SHA256(userId + ":" + timestamp, SANGIA_SHARED_SECRET)[0..15]
     *   key    = "sg_" + userId + "_" + timestamp + "_" + hmac16
     *
     * @param string $userId   User identifier (e.g. "42" or "user@email.com")
     * @param string $secret   The SANGIA_SHARED_SECRET (identical across all ecosystem apps)
     * @return string          API key string to give to the user
     */
    public static function generateKey(string $userId, string $secret): string
    {
        if (!preg_match('/^[A-Za-z0-9@.:-]{1,128}$/', $userId)) {
            throw new \InvalidArgumentException('userId contains unsupported characters.');
        }

        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new \InvalidArgumentException('SANGIA_SHARED_SECRET must be at least 32 characters.');
        }

        $ts   = (string) time();
        $hmac = substr(hash_hmac('sha256', $userId . ':' . $ts, $secret), 0, 16);
        return self::PREFIX . $userId . '_' . $ts . '_' . $hmac;
    }
}
