<?php
declare(strict_types=1);

namespace Sangia\Api\Auth;

use Sangia\Api\Config\Config;

class JWT
{
    private static function secret(): string
    {
        return Config::get('JWT_SECRET', 'default-insecure-secret-change-me');
    }

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function encode(array $payload, int $ttl = null): string
    {
        $ttl = $ttl ?? (int) Config::get('JWT_TTL', 86400);
        $header = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload['iat'] = time();
        $payload['exp'] = time() + $ttl;
        $body = self::b64url(json_encode($payload));
        $sig = self::b64url(hash_hmac('sha256', "$header.$body", self::secret(), true));
        return "$header.$body.$sig";
    }

    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $body, $sig] = $parts;
        $expected = self::b64url(hash_hmac('sha256', "$header.$body", self::secret(), true));
        if (!hash_equals($expected, $sig)) return null;

        $payload = json_decode(self::b64urlDecode($body), true);
        if (!$payload || ($payload['exp'] ?? 0) < time()) return null;

        return $payload;
    }
}
