<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Response;

class SdgController extends BaseController
{
    private const SDG_LABELS = [
        1  => 'Tanpa Kemiskinan',      2  => 'Tanpa Kelaparan',
        3  => 'Kehidupan Sehat',       4  => 'Pendidikan Berkualitas',
        5  => 'Kesetaraan Gender',     6  => 'Air Bersih & Sanitasi',
        7  => 'Energi Bersih',        8  => 'Pekerjaan Layak',
        9  => 'Industri & Inovasi',   10 => 'Berkurangnya Kesenjangan',
        11 => 'Kota Berkelanjutan',   12 => 'Konsumsi Bertanggung Jawab',
        13 => 'Penanganan Iklim',     14 => 'Ekosistem Laut',
        15 => 'Ekosistem Darat',      16 => 'Perdamaian & Keadilan',
        17 => 'Kemitraan Global',
    ];

    public function classify(): void
    {
        $body = $this->jsonBody();

        $title    = trim($body['title'] ?? '');
        $abstract = trim($body['abstract'] ?? '');

        if (empty($title) && empty($abstract)) {
            Response::error('title or abstract is required');
        }

        // Try local Sangia engine first, fall back to built-in classifier
        $results = $this->classifyViaSangiaEngine($title, $abstract)
            ?? $this->classifyLocal($title, $abstract);

        Response::success($results);
    }

    private function classifyViaSangiaEngine(string $title, string $abstract): ?array
    {
        $url = rtrim(\Sangia\Api\Config\Config::get('SANGIA_AI_ENGINE_URL', ''), '/');
        if (!$url) return null;

        $ch = curl_init($url . '/api/sdg/classify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode(['title' => $title, 'abstract' => $abstract]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) return null;

        $data = json_decode($response, true);
        if (!is_array($data)) return null;

        // Normalise to expected shape
        $items = $data['data'] ?? $data;
        if (!is_array($items) || empty($items)) return null;

        return array_map(function ($item) {
            return [
                'sdg'   => (int) ($item['sdg'] ?? 0),
                'score' => (float) ($item['score'] ?? 0),
                'label' => $item['label'] ?? (self::SDG_LABELS[(int)($item['sdg'] ?? 0)] ?? ''),
            ];
        }, $items);
    }

    private function classifyLocal(string $title, string $abstract): array
    {
        // Use the built-in SDG classification engine from core/Modules/SDG
        $text = strtolower($title . ' ' . $abstract);

        $dictionaryPath = dirname(__DIR__, 2) . '/core/Modules/SDG/Config/Dictionaries/';
        $results = [];

        for ($sdg = 1; $sdg <= 17; $sdg++) {
            $dictFile = $dictionaryPath . "Sdg$sdg.php";
            if (!file_exists($dictFile)) continue;

            $keywords = include $dictFile;
            if (!is_array($keywords)) continue;

            $matchCount = 0;
            $totalWords = count($keywords);

            foreach ($keywords as $keyword) {
                if (str_contains($text, strtolower($keyword))) {
                    $matchCount++;
                }
            }

            if ($totalWords > 0 && $matchCount > 0) {
                $score = round(min(1.0, $matchCount / max(1, $totalWords * 0.1)), 3);
                if ($score >= 0.10) {
                    $results[] = [
                        'sdg'   => $sdg,
                        'score' => $score,
                        'label' => self::SDG_LABELS[$sdg] ?? "SDG $sdg",
                    ];
                }
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, 7);
    }
}
