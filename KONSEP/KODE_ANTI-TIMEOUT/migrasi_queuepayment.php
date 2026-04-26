<?php
// ==========================================
// WIZDAM INVOICES MIGRATOR: WEB BATCH MODE
// ==========================================
define('BATCH_SIZE', 50);

require('tools/bootstrap.inc.php');

import('classes.security.Validation');
if (!Validation::isLoggedIn() || !Validation::isSiteAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    die("Akses Ditolak. Harap login sebagai Site Administrator Frontedge Scholar Wizdam.");
}

// ==========================================
// BACKEND PROCESS (AJAX WORKER)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'run') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    try {
        import('lib.wizdam.classes.checkout.InvoiceDAO');
        $invoiceDao = DAORegistry::getDAO('InvoiceDAO');
        $targetOffset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $task = isset($_GET['task']) ? $_GET['task'] : 'queued';

        if ($task === 'queued') {
            $result = $invoiceDao->migrateLegacyQueuedPayments(BATCH_SIZE, $targetOffset);
        } else {
            $result = $invoiceDao->migrateLegacyCompletedPayments(BATCH_SIZE, $targetOffset);
        }

        // Format logs sesuai tipe (Mengambil data dari DAO)
        $htmlLogs = [];
        if (isset($result['logs']) && is_array($result['logs'])) {
            foreach ($result['logs'] as $log) {
                if ($log['type'] == 'success') {
                    $htmlLogs[] = "<span class='success'>{$log['msg']}</span>";
                } elseif ($log['type'] == 'skip') {
                    $htmlLogs[] = "<span class='skip'>{$log['msg']}</span>";
                } else {
                    $htmlLogs[] = "<span style='color:red'>{$log['msg']}</span>";
                }
            }
        }

        echo json_encode([
            'status' => $result['is_done'] ? 'done' : 'continue',
            'next_offset' => $targetOffset + BATCH_SIZE,
            'task' => $task,
            'logs' => $htmlLogs
        ]);

    } catch (\Throwable $e) { // Kompatibilitas PHP 8.x mutlak
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Wizdam Invoice Migrator (Web-Batch)</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #ddd; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #2d2d2d; padding: 20px; border: 1px solid #444; border-radius: 8px;}
        button { background: #ff4757; color: white; border: none; padding: 12px 24px; cursor: pointer; font-size: 16px; font-weight: bold; border-radius: 4px; }
        button:hover { background: #ff6b81; }
        .log-box { height: 400px; overflow-y: auto; background: #000; border: 1px solid #555; margin-top: 15px; padding: 10px; font-size: 13px; line-height: 1.5; border-radius: 4px;}
        .success { color: #2ed573; display: block; margin-bottom: 4px; border-bottom: 1px dashed #333; padding-bottom: 2px;}
        .skip { color: #747d8c; display: block; margin-bottom: 4px; border-bottom: 1px dashed #333; padding-bottom: 2px;}
        .info { color: #1e90ff; font-weight: bold; margin-top: 10px; margin-bottom: 5px; display: block; border-bottom: 2px solid #1e90ff; padding-bottom: 4px;}
    </style>
</head>
<body>

<div class="container">
    <h2>💸 Wizdam Invoices Migrator (Dual-Stage Batch)</h2>
    <p>Skrip ini akan mengekstrak tabel <code>queued_payments</code> (Tertunda) dan <code>completed_payments</code> (Lunas) lalu memigrasikannya ke <code>checkout_invoices</code>.</p>
    
    <div id="controls">
        <button onclick="start()" id="btnStart">MULAI EKSTRAKSI DUAL-STAGE</button>
    </div>
    
    <div style="margin-top:15px; font-size: 1.2em; font-weight: bold;" id="status">Status: Menunggu instruksi...</div>
    <div class="log-box" id="logs"></div>
</div>

<script>
    let currentTask = 'queued'; 

    function start() {
        document.getElementById('btnStart').style.display = 'none';
        log("<span class='info'>🔄 MEMULAI TAHAP 1: Migrasi Queued Payments...</span>");
        runBatch(0, currentTask);
    }

    function runBatch(offset, task) {
        let maxLimit = offset + <?php echo BATCH_SIZE; ?>;
        let taskLabel = task === 'queued' ? 'Tagihan Tertunda (Queued)' : 'Tagihan Lunas (Completed)';
        
        document.getElementById('status').innerText = "Mengekstrak [" + taskLabel + "] ke-" + offset + " s/d " + maxLimit + " ...";
        document.getElementById('status').style.color = "#1e90ff";
        
        fetch('?action=run&offset=' + offset + '&task=' + task)
            .then(res => res.text()) 
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error("Respon Server Gagal: \n" + text.substring(0, 300));
                }
            })
            .then(data => {
                if (data.status === 'error') {
                    log("FATAL: " + data.message, 'error');
                    return;
                }

                if (data.logs && data.logs.length > 0) {
                    data.logs.forEach(l => log(l));
                }

                if (data.status === 'continue') {
                    setTimeout(() => {
                        runBatch(data.next_offset, task);
                    }, 500); 
                } else {
                    if (task === 'queued') {
                        log("<span class='info'>✅ TAHAP 1 SELESAI. <br>🔄 MEMULAI TAHAP 2: Migrasi Completed Payments (PAID)...</span>");
                        currentTask = 'completed';
                        setTimeout(() => {
                            runBatch(0, currentTask); 
                        }, 1500);
                    } else {
                        document.getElementById('status').innerText = "🔥 OPERASI SELESAI TOTAL.";
                        document.getElementById('status').style.color = "#2ed573";
                        log("<strong style='color:#fff'>✅ Seluruh data finansial berhasil disatukan di dalam arsitektur Wizdam Frontedge.</strong>", 'success');
                    }
                }
            })
            .catch(err => {
                log("Connection Error: " + err.message, 'error');
            });
    }

    function log(html, type) {
        let div = document.createElement('div');
        div.innerHTML = html;
        if (type === 'error') div.style.color = 'red';
        let box = document.getElementById('logs');
        box.appendChild(div);
        box.scrollTop = box.scrollHeight; 
    }
</script>
</body>
</html>