<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\SDG\Config;

class SdgDictionary
{
    private array $keywords = [];

    public function __construct()
    {
        $this->loadDictionaries();
    }

    /**
     * Memuat semua file kamus Sdg1.php - Sdg17.php secara dinamis
     */
    private function loadDictionaries(): void
    {
        $dictPath = __DIR__ . '/Dictionaries/';
        
        for ($i = 1; $i <= 17; $i++) {
            $key = 'SDG' . $i;
            $file = $dictPath . 'Sdg' . $i . '.php';
            
            if (file_exists($file)) {
                // 'require' akan langsung mengeksekusi return array dari file
                $this->keywords[$key] = require $file;
            } else {
                // Fallback aman jika ada file yang terhapus/belum dibuat
                $this->keywords[$key] = []; 
            }
        }
    }

    /**
     * Mengambil seluruh array SDG
     */
    public function getAllKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * Mengambil array spesifik untuk 1 SDG saja (misal: 'SDG1')
     */
    public function getKeywordsFor(string $sdgCode): array
    {
        return $this->keywords[$sdgCode] ?? [];
    }
}