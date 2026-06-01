<?php
declare(strict_types=1);

namespace Sangia\Api\Middleware;

use Sangia\Api\Config\Config;

class CorsMiddleware
{
    public static function handle(): void
    {
        $allowedOrigins = array_map('trim', explode(',', Config::get(
            'CORS_ALLOWED_ORIGINS',
            'http://localhost:3000,https://www.sangia.org'
        )));

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin && in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: $origin");
        } elseif (in_array('*', $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: *');
        } else {
            header('Access-Control-Allow-Origin: ' . ($allowedOrigins[0] ?? 'http://localhost:3000'));
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        header('Vary: Origin');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
