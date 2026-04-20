<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\SDG\Services;

use Sangia\Core\Modules\SDG\Config\SdgDictionary;
use Sangia\Core\Modules\SDG\Config\SdgConfig;
use Sangia\Core\Shared\Helpers\TextHelper;
use Sangia\Core\Shared\Helpers\MathHelper;

class SdgClassifier
{
    private SdgDictionary $dictionary;
    private array $sdgVectors = [];

    public function __construct(SdgDictionary $dictionary)
    {
        $this->dictionary = $dictionary;
    }

    /**
     * Tahap 1: Deteksi awal SDG berdasarkan kemunculan keyword (Ringan)
     */
    public function detectPotentialSdgs(string $preprocessedText): array
    {
        $matchedSdgs = [];
        $allKeywords = $this->dictionary->getAllKeywords();

        foreach ($allKeywords as $sdgCode => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($preprocessedText, $keyword)) {
                    $matchedSdgs[] = $sdgCode;
                    break; // Jika sudah ketemu satu, lanjut ke SDG berikutnya
                }
            }
        }
        return $matchedSdgs;
    }

    /**
     * Tahap 2: Scoring berbasis Frekuensi & Similarity
     */
    public function calculateBaseScores(string $text): array
    {
        $preprocessed = TextHelper::preprocess($text);
        $potentialSdgs = $this->detectPotentialSdgs($preprocessed);
        $scores = [];

        $textVector = MathHelper::createVector($preprocessed);

        foreach ($potentialSdgs as $sdgCode) {
            // 1. Keyword Score (Frekuensi)
            $keywords = $this->dictionary->getKeywordsFor($sdgCode);
            $count = 0;
            foreach ($keywords as $kw) {
                $count += substr_count($preprocessed, $kw);
            }
            
            // 2. Similarity Score (Vektor)
            $sdgText = implode(' ', $keywords);
            $sdgVector = MathHelper::createVector($sdgText);
            $similarity = MathHelper::calculateCosineSimilarity($textVector, $sdgVector);

            $scores[$sdgCode] = [
                'keyword_freq' => $count,
                'similarity' => $similarity
            ];
        }

        return $scores;
    }
}