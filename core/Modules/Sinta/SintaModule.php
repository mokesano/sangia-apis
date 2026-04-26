<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\Sinta;

use Sangia\Core\Shared\Services\CacheService;

/**
 * SINTA Module — scrapes SINTA (Kemenristekdikti) journal metrics.
 * OOP replacement for the procedural SINTA_impact_Score.php.
 */
class SintaModule
{
    private const BASE_URL   = 'https://sinta.kemdiktisaintek.go.id';
    private const TIMEOUT    = 20;
    private const CACHE_TTL  = 604800; // 7 days

    private CacheService $cache;

    public function __construct()
    {
        $this->cache = new CacheService('SintaJournal');
    }

    public function getScore(string $issn, bool $refresh = false): array
    {
        $issn = $this->normalizeIssn($issn);
        if (!$issn) {
            return $this->error(400, 'Invalid ISSN format. Expected XXXX-XXXX.');
        }

        if (!$refresh) {
            $cached = $this->cache->get('sinta', $issn);
            if ($cached !== false) {
                $cached['cache_info'] = ['from_cache' => true];
                return $cached;
            }
        }

        $data = $this->scrape($issn);
        if (empty($data)) {
            return $this->error(404, "Journal with ISSN $issn not found in SINTA");
        }

        $result = array_merge(['status' => 'success', 'api_version' => 'v2.1-modular'], $data, [
            'meta'       => ['last_update' => date('Y-m-d H:i:s'), 'cache_version' => '2.1-modular'],
            'cache_info' => ['from_cache' => false],
        ]);

        $this->cache->set('sinta', $issn, $result);
        return $result;
    }

    private function scrape(string $issn): array
    {
        // Try the SINTA journals search endpoint
        $url  = self::BASE_URL . '/journals?q=' . urlencode($issn) . '&search=1&sinta=1';
        $html = $this->fetchHtml($url);
        if (!$html) return [];

        // Try to parse journal card from HTML
        $data = $this->parseJournalCard($html, $issn);
        if (!empty($data)) return $data;

        // Try direct ISSN lookup
        $url2 = self::BASE_URL . '/journals/profile?issn=' . urlencode(str_replace('-', '', $issn));
        $html2 = $this->fetchHtml($url2);
        return $html2 ? $this->parseJournalCard($html2, $issn) : [];
    }

    private function parseJournalCard(string $html, string $issn): array
    {
        // Extract journal name
        preg_match('/<h3[^>]*class="[^"]*journal-title[^"]*"[^>]*>(.*?)<\/h3>/si', $html, $titleMatch);
        $title = trim(strip_tags($titleMatch[1] ?? ''));

        // Extract SINTA grade (Q1-Q4 or S1-S6)
        preg_match('/sinta[-\s]?(?:grade|score|rank)?[:\s]*([SQ][1-6]|\d+\.?\d*)/i', $html, $gradeMatch);
        $grade = $gradeMatch[1] ?? null;

        // Extract impact score
        preg_match('/impact[:\s]*([0-9]+\.?[0-9]*)/i', $html, $impactMatch);
        $impact = $impactMatch[1] ?? null;

        // Extract SINTA ID from URL patterns
        preg_match('/\/journals\/profile\/(\d+)/', $html, $idMatch);
        $sintaId = $idMatch[1] ?? null;

        if (empty($title) && empty($grade) && empty($sintaId)) return [];

        return [
            'issn'       => $issn,
            'title'      => $title ?: null,
            'impact'     => $impact,
            'grade'      => $grade,
            'sinta_id'   => $sintaId,
            'sinta_url'  => $sintaId ? self::BASE_URL . '/journals/profile/' . $sintaId : null,
        ];
    }

    private function fetchHtml(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SangiaBot/1.0; +https://sangia.org)',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml'],
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 && $body) ? $body : null;
    }

    private function normalizeIssn(string $issn): ?string
    {
        $clean = preg_replace('/[^0-9Xx]/', '', $issn);
        if (strlen($clean) !== 8) return null;
        return substr($clean, 0, 4) . '-' . strtoupper(substr($clean, 4));
    }

    private function error(int $code, string $message): array
    {
        http_response_code($code);
        return ['status' => 'error', 'code' => $code, 'message' => $message];
    }
}
