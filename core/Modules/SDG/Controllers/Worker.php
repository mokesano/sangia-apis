<?php
declare(strict_types=1);

namespace Sangia\Core\Modules\SDG\Controllers;

use Sangia\Core\Shared\Models\TaskModel;
use Sangia\Core\Shared\ApiClients\CrossrefClient;
use Sangia\Core\Modules\SDG\Services\SdgAnalyzer;
use Sangia\Core\Modules\SDG\Services\SdgClassifier;
use Sangia\Core\Modules\SDG\Services\Evaluator\LevelV4Evaluator;
use Sangia\Core\Modules\SDG\Config\SdgDictionary;

class Worker
{
    private TaskModel $taskModel;
    private CrossrefClient $crossrefClient;
    private SdgAnalyzer $analyzer;

    public function __construct()
    {
        $this->taskModel = new TaskModel();
        $this->crossrefClient = new CrossrefClient();
        
        // Inisialisasi Otak Analitik
        $dictionary = new SdgDictionary();
        $classifier = new SdgClassifier($dictionary);
        $v4Evaluator = new LevelV4Evaluator();
        
        $this->analyzer = new SdgAnalyzer($classifier, $v4Evaluator, $dictionary);
    }

    /**
     * Endpoint untuk memproses antrean berdasarkan Task ID (Dipanggil via AJAX Polling)
     */
    public function processChunk(string $taskId): array
    {
        // 1. Ambil State/Status Task saat ini
        $taskId = preg_replace('/[^a-zA-Z0-9_-]/', '', $taskId); // Sanitasi
        $task = $this->taskModel->getTask($taskId);

        if (!$task) {
            return $this->jsonResponse(404, ['error' => 'Task ID tidak ditemukan atau sudah kadaluarsa.']);
        }

        // 2. Jika sudah selesai, langsung kembalikan hasil akhirnya
        if ($task['status'] === 'completed') {
            return $this->jsonResponse(200, [
                'status' => 'completed',
                'progress' => 100,
                'meta' => $task['meta'],
                'total_processed' => $task['progress']['total'],
                'results' => $task['results'] // Data final untuk dirender grafik di Frontend
            ]);
        }

        // 3. Ambil sebagian kecil data untuk diproses di siklus ini (Maks 3 item)
        $batchSize = 3;
        // array_splice akan mengambil 3 item pertama dan MENGHAPUSNYA dari $task['pending_items']
        $itemsToProcessNow = array_splice($task['pending_items'], 0, $batchSize);

        // 4. Proses masing-masing karya
        foreach ($itemsToProcessNow as $workSummary) {
            $title = $workSummary['title']['title']['value'] ?? '';
            $doi = $this->extractDoi($workSummary);
            $abstract = '';

            // Jika ada DOI, ambil abstrak dari Crossref/Semantic Scholar
            if (!empty($doi)) {
                $crossrefData = $this->crossrefClient->getWorkData($doi);
                $abstract = $crossrefData['message']['abstract'] ?? '';
                
                // Coba ambil dari alternatif jika Crossref tidak punya abstrak
                if (empty($abstract)) {
                    $abstract = $this->crossrefClient->getAlternativeAbstract($doi);
                }
            }

            // Eksekusi Mesin AI
            $analysisResult = $this->analyzer->analyzeWork($title, $abstract);

            // Simpan hasil ke dalam tumpukan Task
            $task['results'][] = [
                'title' => $title,
                'doi' => $doi,
                'sdg_analysis' => $analysisResult
            ];

            $task['progress']['current']++;
        }

        // 5. Perbarui Status Antrean
        if (empty($task['pending_items'])) {
            $task['status'] = 'completed';
            // --> TAMBAHKAN INI <--
            // Lakukan rekapitulasi akhir saat semua karya selesai
            $summarizer = new \Sangia\Core\Modules\SDG\Services\SdgSummarizer();
            $finalSummary = $summarizer->generateProfileSummary($task['results']);
            
            $task['meta']['profile_summary'] = $finalSummary;
            // ---------------------
        } else {
            $task['status'] = 'processing';
        }

        // 6. Simpan kembali State ke file JSON
        $this->taskModel->saveTask($taskId, $task);

        // 7. Kembalikan respons Progress ke Frontend
        return $this->jsonResponse(200, [
            'status' => $task['status'],
            'task_id' => $taskId,
            'progress' => $task['progress'] // Akan berisi: current, total, percentage
        ]);
    }

    /**
     * Helper privat untuk ekstrak DOI dari array ORCID Work Summary
     */
    private function extractDoi(array $summary): ?string
    {
        if (!isset($summary['external-ids']['external-id']) || !is_array($summary['external-ids']['external-id'])) {
            return null;
        }

        foreach ($summary['external-ids']['external-id'] as $id) {
            if (isset($id['external-id-type']) && strtolower($id['external-id-type']) === 'doi') {
                return $id['external-id-value'] ?? null;
            }
        }
        return null;
    }

    /**
     * Format standard respons JSON
     */
    private function jsonResponse(int $code, array $data): array
    {
        http_response_code($code);
        return $data;
    }
}