<?php
declare(strict_types=1);

namespace Sangia\Core\Shared\Models;

use RuntimeException;

class TaskModel
{
    private string $taskDir;

    public function __construct()
    {
        // Arahkan ke folder writable/tasks di luar direktori core
        $this->taskDir = __DIR__ . '/../../../../writable/tasks';
        
        if (!is_dir($this->taskDir)) {
            if (!mkdir($this->taskDir, 0755, true) && !is_dir($this->taskDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $this->taskDir));
            }
        }
    }

    /**
     * Membuat Task baru dan mengembalikan Task ID
     */
    public function createTask(string $module, string $identifier, array $itemsToProcess, array $meta = []): string
    {
        $taskId = 'WD-' . strtoupper(bin2hex(random_bytes(6))); // Contoh: WD-A1B2C3D4E5F6
        
        $taskData = [
            'task_id' => $taskId,
            'module' => $module,             // misal: 'SDG'
            'identifier' => $identifier,     // ORCID atau DOI asal
            'status' => 'pending',           // pending -> processing -> completed
            'progress' => [
                'current' => 0,
                'total' => count($itemsToProcess),
                'percentage' => 0
            ],
            'pending_items' => $itemsToProcess, // Antrean yang belum diproses
            'results' => [],                 // Tempat menumpuk hasil analisis
            'meta' => $meta,                 // Data tambahan (seperti Nama Peneliti, dll)
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];

        $this->saveTask($taskId, $taskData);
        return $taskId;
    }

    /**
     * Mengambil data Task berdasarkan ID
     */
    public function getTask(string $taskId): ?array
    {
        $file = $this->getFilePath($taskId);
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        return $content ? json_decode($content, true) : null;
    }

    /**
     * Menyimpan/Memperbarui data Task
     */
    public function saveTask(string $taskId, array $data): void
    {
        $data['updated_at'] = date('c');
        
        // Kalkulasi ulang persentase jika ada perubahan progress
        if (isset($data['progress']['total']) && $data['progress']['total'] > 0) {
            $data['progress']['percentage'] = round(($data['progress']['current'] / $data['progress']['total']) * 100);
        }
        
        file_put_contents($this->getFilePath($taskId), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Helper untuk mendapatkan path file dengan aman
     */
    private function getFilePath(string $taskId): string
    {
        // Sanitasi nama file untuk mencegah Path Traversal attack
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $taskId);
        return $this->taskDir . '/' . $safeId . '.json';
    }
}