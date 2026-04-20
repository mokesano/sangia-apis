<?php
declare(strict_types=1);

namespace Sangia\Core\Shared\Helpers;

class MathHelper
{
    /**
     * Membuat vektor frekuensi kata dari teks
     */
    public static function createVector(string $text): array
    {
        $words = explode(' ', $text);
        $vector = [];
        
        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $vector[$word] = ($vector[$word] ?? 0) + 1;
            }
        }
        return $vector;
    }

    /**
     * Menghitung Cosine Similarity antara dua vektor
     */
    public static function calculateCosineSimilarity(array $vec1, array $vec2): float
    {
        // Gunakan vektor yang lebih kecil sebagai iterator untuk efisiensi
        if (count($vec1) > count($vec2)) {
            [$vec1, $vec2] = [$vec2, $vec1];
        }

        $dotProduct = 0.0;
        $mag1 = 0.0;
        $mag2 = 0.0;

        foreach ($vec1 as $word => $count) {
            if (isset($vec2[$word])) {
                $dotProduct += $count * $vec2[$word];
            }
            $mag1 += $count ** 2;
        }

        foreach ($vec2 as $count) {
            $mag2 += $count ** 2;
        }

        $divisor = sqrt($mag1) * sqrt($mag2);
        return $divisor > 0 ? round($dotProduct / $divisor, 3) : 0.0;
    }
}