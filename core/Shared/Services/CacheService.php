<?php
declare(strict_types=1);

namespace Sangia\Core\Shared\Services;

use Sangia\Core\Modules\SDG\Config\SdgConfig;

class CacheService
{
    private string $cacheDir;
    private int $ttl;

    public function __construct(string $moduleName = 'sdg')
    {
        // Arahkan ke writable/cache/{nama_modul}/
        $this->cacheDir = __DIR__ . '/../../../../writable/cache/' . strtolower($moduleName);
        $this->ttl = 604800; // 7 hari dalam detik (Default dari SdgConfig)
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Membaca data dari cache gzip
     */
    public function get(string $type, string $id): array|false
    {
        $filename = $this->getFilename($type, $id);

        if (!file_exists($filename)) {
            return false;
        }

        // Cek apakah cache sudah kadaluarsa
        if ((time() - filemtime($filename)) > $this->ttl) {
            unlink($filename); // Hapus file usang
            return false; 
        }

        $compressedData = file_get_contents($filename);
        if ($compressedData === false) return false;

        $jsonData = gzdecode($compressedData);
        if ($jsonData === false) return false;

        $data = json_decode($jsonData, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : false;
    }

    /**
     * Menyimpan data dengan kompresi gzip level 9
     */
    public function set(string $type, string $id, array $data): void
    {
        $filename = $this->getFilename($type, $id);
        $jsonData = json_encode($data);
        
        // Kompresi maksimal untuk menghemat disk space server
        $compressedData = gzencode($jsonData, 9);
        file_put_contents($filename, $compressedData);
    }

    /**
     * Mengamankan nama file cache dari karakter berbahaya
     */
    private function getFilename(string $type, string $id): string
    {
        // Gunakan hash agar panjang file seragam dan aman
        $uniqueCode = substr(md5($id . '_v4'), 0, 8); 
        $safeId = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $id);
        
        return $this->cacheDir . '/' . $type . '_' . $safeId . '_' . $uniqueCode . '.json.gz';
    }
}