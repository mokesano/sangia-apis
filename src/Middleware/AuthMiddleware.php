<?php
declare(strict_types=1);

namespace Sangia\Api\Middleware;

use Sangia\Api\Auth\JWT;
use Sangia\Api\Response;

class AuthMiddleware
{
    public static function require(): array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('Missing or invalid authorization token');
        }

        $token = substr($authHeader, 7);
        $payload = JWT::decode($token);

        if (!$payload) {
            Response::unauthorized('Token expired or invalid');
        }

        return $payload;
    }

    public static function optional(): ?array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return JWT::decode(substr($authHeader, 7));
    }
}
