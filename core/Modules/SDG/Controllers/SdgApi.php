<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\SDG\Controllers;

use Sangia\Core\Shared\Models\TaskModel;
use Sangia\Core\Shared\ApiClients\OrcidClient;
use Sangia\Core\Shared\ApiClients\CrossrefClient;
use Sangia\Core\Shared\Services\CacheService;
use Sangia\Core\Modules\SDG\Services\SdgAnalyzer;
// (Pastikan Anda meng-use SdgDictionary, SdgClassifier, LevelV4Evaluator juga jika instansiasi manual)

class SdgApi
{
    private TaskModel $taskModel;
    private OrcidClient $orcidClient;
    private CrossrefClient $crossrefClient;
    private CacheService $cache;
    private SdgAnalyzer $analyzer;

    public function __construct(SdgAnalyzer $analyzer)
    {
        $this->taskModel = new TaskModel();
        $this->orcidClient = new OrcidClient();
        $this->crossrefClient = new CrossrefClient();
        $this->cache = new CacheService('sdg'); // Set folder cache ke writable/cache/sdg/
        $this->analyzer = $analyzer;
    }

    /**
     * Handler untuk ORCID (Menggunakan Sistem Antrean/Worker)
     */
    public function handleOrcidRequest(string $orcid, bool $forceRefresh = false): array
    {
        $orcid = trim($orcid);
        
        // 1. Cek Cache (Jika tidak force refresh)
        if (!$forceRefresh) {
            $cachedData = $this->cache->get('orcid', $orcid);
            if ($cachedData !== false) {
                return [
                    'status' => 'success',
                    'message' => 'Data diambil dari cache.',
                    'from_cache' => true,
                    'data' => $cachedData
                ];
            }
        }

        // 2. Jika tidak ada di cache, masukkan ke antrean (Logika sama seperti sebelumnya)
        // ... (Ambil Profil dari OrcidClient) ...
        // ... (Buat $taskId via TaskModel) ...
        
        // Contoh return jika masuk antrean (dipersingkat untuk fokus)
        return [
            'status' => 'queued',
            'task_id' => 'WD-XYZ123', // Hasil dari $this->taskModel->createTask(...)
            'message' => 'Masuk antrean. Lakukan polling ke worker.'
        ];
    }

    /**
     * Handler untuk DOI Tunggal (Diproses Langsung, Bebas Timeout karena cuma 1)
     */
    public function handleDoiRequest(string $doi, bool $forceRefresh = false): array
    {
        $doi = trim($doi);
        if (empty($doi)) {
            return $this->errorResponse(400, 'DOI tidak boleh kosong');
        }

        // 1. Cek Cache
        if (!$forceRefresh) {
            $cachedData = $this->cache->get('article', $doi);
            if ($cachedData !== false) {
                $cachedData['cache_info'] = ['from_cache' => true];
                return $cachedData;
            }
        }

        try {
            // 2. Ambil Metadata dari Crossref
            $crossrefData = $this->crossrefClient->getWorkData($doi);
            if (empty($crossrefData)) {
                return $this->errorResponse(404, 'Data DOI tidak ditemukan di Crossref.');
            }

            $title = $crossrefData['message']['title'][0] ?? '';
            $abstract = $crossrefData['message']['abstract'] ?? $this->crossrefClient->getAlternativeAbstract($doi);

            // 3. Analisis menggunakan SdgAnalyzer
            $analysisResult = $this->analyzer->analyzeWork($title, $abstract);

            // 4. Susun Hasil Akhir
            $finalResult = [
                'doi' => $doi,
                'title' => $title,
                'abstract' => strip_tags($abstract),
                'sdg_analysis' => $analysisResult,
                'api_version' => 'v5.1.8-Modular',
                'status' => 'success'
            ];

            // 5. Simpan ke Cache (.json.gz)
            $this->cache->set('article', $doi, $finalResult);

            $finalResult['cache_info'] = ['from_cache' => false];
            return $finalResult;

        } catch (\Exception $e) {
            return $this->errorResponse(500, 'Kesalahan internal: ' . $e->getMessage());
        }
    }

    private function errorResponse(int $code, string $message): array
    {
        http_response_code($code);
        return ['status' => 'error', 'code' => $code, 'message' => $message];
    }
}