<?php
declare(strict_types=1);

namespace Sangia\Core\Shared\ApiClients;

use RuntimeException;

class OrcidClient
{
    private const BASE_URL = 'https://pub.orcid.org/v3.0/';
    private int $timeout;

    public function __construct(int $timeout = 10)
    {
        $this->timeout = $timeout; // Set default timeout 10 detik agar tidak hang
    }

    /**
     * Mengambil profil peneliti (Nama, Institusi, dll)
     */
    public function getPersonData(string $orcid): array
    {
        $endpoint = self::BASE_URL . $orcid . '/person';
        return $this->executeRequest($endpoint);
    }

    /**
     * Mengambil daftar karya publikasi dari ORCID
     */
    public function getWorksData(string $orcid, int $pageSize = 50): array
    {
        $endpoint = self::BASE_URL . $orcid . '/works?pageSize=' . $pageSize;
        return $this->executeRequest($endpoint);
    }

    /**
     * Eksekusi cURL terpusat dengan penanganan error
     */
    private function executeRequest(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'Sangia-API-Engine/1.0'
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("OrcidClient Error: Failed fetching $url. HTTP $httpCode. Error: $error");
            return []; // Return array kosong sebagai fallback yang aman
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("OrcidClient JSON Error: " . json_last_error_msg());
            return [];
        }

        return $decoded;
    }
}