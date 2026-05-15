<?php
declare(strict_types=1);

namespace Sangia\Core\Shared\ApiClients;

use Sangia\Api\Config\Config;

/**
 * Shared HTTP client base for all external API clients.
 * Provides a single cURL wrapper with consistent timeout, user-agent, and error handling.
 */
abstract class HttpClient
{
    protected int    $timeout      = 15;
    protected int    $connectTimeout = 5;
    protected string $userAgent    = 'Sangia-API-Engine/1.0 (mailto:api@sangia.org)';

    protected function httpGet(string $url, array $headers = []): array
    {
        $body = $this->httpGetRaw($url, $headers);
        if ($body === '') return [];
        return json_decode($body, true) ?? [];
    }

    protected function httpGetRaw(string $url, array $headers = []): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($body === false || $httpCode < 200 || $httpCode >= 300) {
            error_log(sprintf('[HttpClient] %s HTTP %d %s', $url, $httpCode, $error));
            return '';
        }

        return (string) $body;
    }

    protected function cfg(string $key, string $default = ''): string
    {
        return (string) Config::get($key, $default);
    }
}
