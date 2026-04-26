<?php
declare(strict_types=1);

namespace Sangia\Api\Middleware;

use Sangia\Gateway\ApiKeyMiddleware;

/**
 * File-based sliding-window rate limiter.
 * Default: 60 requests per 60-second window per API key.
 * Override via env: RATE_LIMIT_REQUESTS, RATE_LIMIT_WINDOW
 */
class RateLimitMiddleware
{
    private const STORE_DIR = __DIR__ . '/../../writable/ratelimit';

    public static function check(): void
    {
        $maxRequests = (int) ($_ENV['RATE_LIMIT_REQUESTS'] ?? getenv('RATE_LIMIT_REQUESTS') ?: 60);
        $windowSecs  = (int) ($_ENV['RATE_LIMIT_WINDOW']   ?? getenv('RATE_LIMIT_WINDOW')   ?: 60);

        $userId = ApiKeyMiddleware::getUserId() ?? 'anon';
        $file   = self::STORE_DIR . '/' . md5($userId) . '.json';

        @mkdir(self::STORE_DIR, 0755, true);

        $now  = time();
        $data = self::readStore($file);

        // Slide window: keep only timestamps within current window
        $data['hits'] = array_values(array_filter(
            $data['hits'] ?? [],
            fn(int $ts) => $ts > $now - $windowSecs
        ));

        if (count($data['hits']) >= $maxRequests) {
            $retryAfter = ($data['hits'][0] + $windowSecs) - $now;
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . $retryAfter);
            header('X-RateLimit-Limit: ' . $maxRequests);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . ($data['hits'][0] + $windowSecs));
            echo json_encode([
                'status'      => 'error',
                'code'        => 429,
                'message'     => 'Rate limit exceeded.',
                'retry_after' => $retryAfter,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data['hits'][] = $now;
        self::writeStore($file, $data);

        header('X-RateLimit-Limit: ' . $maxRequests);
        header('X-RateLimit-Remaining: ' . ($maxRequests - count($data['hits'])));
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
