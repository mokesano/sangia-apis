<?php
declare(strict_types=1);

namespace Sangia\Api;

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success(mixed $data, array $meta = [], int $status = 200): never
    {
        $response = ['success' => true, 'data' => $data];
        if (!empty($meta)) $response['meta'] = $meta;
        self::json($response, $status);
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(['success' => false, 'message' => $message], $status);
    }

    public static function notFound(string $message = 'Resource not found'): never
    {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error($message, 401);
    }

    public static function serverError(string $message = 'Internal server error'): never
    {
        self::error($message, 500);
    }
}
