<?php
declare(strict_types=1);

namespace Sangia\Core\Shared\ApiClients;

use RuntimeException;

class CrossrefClient
{
    private const CROSSREF_URL = 'https://api.crossref.org/works/';
    private const SEMANTIC_SCHOLAR_URL = 'https://api.semanticscholar.org/v1/paper/';
    private int $timeout;
    private string $userAgent;

    public function __construct(int $timeout = 10, string $contactEmail = 'developer@sangia.org')
    {
        $this->timeout = $timeout;
        // Crossref merekomendasikan mailto di User-Agent agar masuk ke "Polite Pool" (jalur cepat)
        $this->userAgent = "Sangia-API-Engine/1.0 (mailto:{$contactEmail})"; 
    }

    /**
     * Mengambil data metadata artikel berdasarkan DOI
     */
    public function getWorkData(string $doi): array
    {
        $url = self::CROSSREF_URL . urlencode($doi);
        return $this->executeRequest($url);
    }

    /**
     * Mengambil abstrak alternatif jika Crossref tidak memilikinya
     */
    public function getAlternativeAbstract(string $doi): string
    {
        $url = self::SEMANTIC_SCHOLAR_URL . urlencode($doi);
        $data = $this->executeRequest($url, false); // false = jangan pakai header Crossref

        return $data['abstract'] ?? '';
    }

    /**
     * Eksekusi cURL terpusat
     */
    private function executeRequest(string $url, bool $useCrossrefHeader = true): array
    {
        $ch = curl_init($url);
        
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent
        ];

        if ($useCrossrefHeader) {
            $options[CURLOPT_HTTPHEADER] = ['Accept: application/json'];
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("API Client Error fetching $url: $error");
            return [];
        }

        $decoded = json_decode((string)$response, true);
        return is_array($decoded) ? $decoded : [];
    }
}