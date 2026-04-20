<?php
declare(strict_types=1);

namespace Sangia\Core\Shared\Helpers;

class TextHelper
{
    /**
     * Membersihkan teks dari tag HTML dan karakter khusus
     */
    public static function preprocess(string $text): string
    {
        $text = strtolower(strip_tags($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Ekstraksi konteks kalimat di sekitar kata kunci yang ditemukan
     */
    public static function extractKeywordContext(string $text, string $keyword, int $contextLength = 100): string
    {
        $position = stripos($text, $keyword);
        if ($position === false) {
            return '';
        }

        $start = max(0, $position - (int)($contextLength / 2));
        $length = strlen($keyword) + $contextLength;

        if ($start + $length > strlen($text)) {
            $length = strlen($text) - $start;
        }

        $context = substr($text, $start, $length);

        if ($start > 0) $context = '...' . $context;
        if ($start + $length < strlen($text)) $context .= '...';

        // Opsional: Highlight keyword
        $context = preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<strong>$1</strong>', $context);

        return $context;
    }

    /**
     * Ekstraksi frasa penting (2-3 kata berurutan yang bermakna)
     */
    public static function extractPhrases(string $text): array
    {
        if (empty($text)) return [];

        $pattern = '/\b[a-z]{3,}\s+[a-z]{3,}(\s+[a-z]{3,})?\b/i';
        preg_match_all($pattern, $text, $matches);
        
        $phrases = $matches[0] ?? [];
        $stopwords = ['the', 'and', 'of', 'to', 'a', 'in', 'for', 'on', 'with', 'at', 'by', 'as'];
        $filteredPhrases = [];

        foreach ($phrases as $phrase) {
            $words = explode(' ', strtolower($phrase));
            if (empty($words)) continue;

            $firstWord = $words[0];
            $lastWord = end($words);

            if (!in_array($firstWord, $stopwords) && !in_array($lastWord, $stopwords)) {
                $filteredPhrases[] = $phrase;
            }
        }

        return array_unique($filteredPhrases);
    }
}