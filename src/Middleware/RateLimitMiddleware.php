<?php
declare(strict_types=1);

namespace Sangia\Api\Middleware;

use PDO;
use Sangia\Api\Config\Config;
use Sangia\Database\Connection;
use Sangia\Gateway\ApiKeyMiddleware;

/**
 * Sliding-window rate limiter.
 * Uses DB (api_rate_limits) when available; falls back to file-based store.
 * Default: 60 requests per 60-second window per API key user_id.
 * Override via env: RATE_LIMIT_REQUESTS, RATE_LIMIT_WINDOW
 */
class RateLimitMiddleware
{
    private const STORE_DIR = __DIR__ . '/../../writable/ratelimit';

    public static function check(): void
    {
        $maxRequests = (int) (Config::get('RATE_LIMIT_REQUESTS') ?: 60);
        $windowSecs  = (int) (Config::get('RATE_LIMIT_WINDOW')   ?: 60);
        $userId      = ApiKeyMiddleware::getUserId() ?? 'anon';

        $pdo = Connection::get();
        if ($pdo !== null) {
            try {
                self::checkDb($pdo, $userId, $maxRequests, $windowSecs);
                return; // checkDb emits headers and exits on limit, or returns normally
            } catch (\Throwable) {
                // api_rate_limits table absent or other DB error — fall through to file
            }
        }
        self::checkFile($userId, $maxRequests, $windowSecs);
    }

    // ── DB-backed fixed-window counter ────────────────────────────────────────

    private static function checkDb(PDO $pdo, string $userId, int $maxRequests, int $windowSecs): void
    {
        $now         = time();
        $windowStart = (int) floor($now / $windowSecs) * $windowSecs;

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $upsertSql = 'INSERT INTO api_rate_limits (user_id, window_start, hit_count)
                          VALUES (?, ?, 1)
                          ON CONFLICT (user_id, window_start)
                          DO UPDATE SET hit_count = api_rate_limits.hit_count + 1';
        } else {
            $upsertSql = 'INSERT INTO api_rate_limits (user_id, window_start, hit_count)
                          VALUES (?, ?, 1)
                          ON DUPLICATE KEY UPDATE hit_count = hit_count + 1';
        }
        $pdo->prepare($upsertSql)->execute([$userId, $windowStart]);

        $stmt = $pdo->prepare(
            'SELECT hit_count FROM api_rate_limits WHERE user_id = ? AND window_start = ?'
        );
        $stmt->execute([$userId, $windowStart]);
        $count = (int) $stmt->fetchColumn();

        // Prune stale windows (best-effort, failure is non-critical)
        try {
            $pdo->prepare('DELETE FROM api_rate_limits WHERE window_start < ?')
                ->execute([$windowStart - $windowSecs * 2]);
        } catch (\Throwable) {
        }

        $windowReset = $windowStart + $windowSecs;
        self::emitHeaders($maxRequests, $count);

        if ($count > $maxRequests) {
            self::tooManyRequests($maxRequests, $windowReset - $now);
        }
    }

    // ── File-based sliding-window fallback ────────────────────────────────────

    private static function checkFile(string $userId, int $maxRequests, int $windowSecs): void
    {
        $dir  = self::STORE_DIR;
        $file = $dir . '/' . md5($userId) . '.json';
        @mkdir($dir, 0755, true);

        $now  = time();
        $data = self::readStore($file);

        $data['hits'] = array_values(array_filter(
            $data['hits'] ?? [],
            fn(int $ts) => $ts > $now - $windowSecs
        ));

        $count = count($data['hits']);
        self::emitHeaders($maxRequests, $count + 1);

        if ($count >= $maxRequests) {
            $retryAfter = ($data['hits'][0] + $windowSecs) - $now;
            self::tooManyRequests($maxRequests, $retryAfter, $data['hits'][0] + $windowSecs);
        }

        $data['hits'][] = $now;
        self::writeStore($file, $data);
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    private static function emitHeaders(int $limit, int $used): void
    {
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . max(0, $limit - $used));
    }

    private static function tooManyRequests(int $limit, int $retryAfter, int $reset = 0): never
    {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        header('Retry-After: ' . $retryAfter);
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: 0');
        if ($reset) header('X-RateLimit-Reset: ' . $reset);
        echo json_encode([
            'status'      => 'error',
            'code'        => 429,
            'message'     => 'Rate limit exceeded.',
            'retry_after' => $retryAfter,
        ]);
        exit;
    }

    private static function readStore(string $file): array
    {
        if (!file_exists($file)) return ['hits' => []];
        $raw = file_get_contents($file);
        return $raw ? (json_decode($raw, true) ?? ['hits' => []]) : ['hits' => []];
    }

    private static function writeStore(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
