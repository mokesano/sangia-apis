<?php
// ==========================================
// WIZDAM IDENTIFIER GENERATOR: WEB BATCH MODE
// ==========================================
define('BATCH_SIZE', 50); // Memproses 50 artikel per detik (Sangat aman)

require('tools/bootstrap.inc.php');

// --- KEAMANAN MUTLAK ---
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
        $articleDao = DAORegistry::getDAO('ArticleDAO');

        $targetOffset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $processedInBatch = 0;
        $logs = [];

        // Ambil ID saja dari database menggunakan limit
        $sql = "SELECT article_id FROM articles WHERE status = 3";
        $result = $articleDao->retrieveLimit($sql, [], BATCH_SIZE, $targetOffset);

        while (!$result->EOF) {
            $row = $result->GetRowAssoc(false);
            $articleId = (int) $row['article_id'];

            // PANGGIL FUNGSI SENTRAL WIZDAM Identifier
            $identifiers = $articleDao->getArticleIdentifiers($articleId);

            if ($identifiers) {
                $logs[] = "<span class='success'>[OK] ID {$articleId} | PII: {$identifiers['pii']} | eLocator: {$identifiers['eLocator']}</span>";
            }

            $processedInBatch++;
            $result->MoveNext();
        }
        $result->Close();

        $isDone = ($processedInBatch < BATCH_SIZE);
        $nextOffset = $targetOffset + BATCH_SIZE;

        echo json_encode([
            'status' => $isDone ? 'done' : 'continue',
            'next_offset' => $nextOffset,
            'logs' => $logs
        ]);

    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Wizdam Web Batch Generator</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #ddd; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #2d2d2d; padding: 20px; border: 1px solid #444; border-radius: 8px;}
        button { background: #ff4757; color: white; border: none; padding: 12px 24px; cursor: pointer; font-size: 16px; font-weight: bold; border-radius: 4px; }
        button:hover { background: #ff6b81; }
        .log-box { height: 400px; overflow-y: auto; background: #000; border: 1px solid #555; margin-top: 15px; padding: 10px; font-size: 13px; line-height: 1.5; border-radius: 4px;}
        .success { color: #2ed573; display: block; margin-bottom: 4px; border-bottom: 1px dashed #333; padding-bottom: 2px;}
        .skip { color: #747d8c; display: block;}
        .info { color: #1e90ff; font-weight: bold; margin-bottom: 5px; display: block;}
    </style>
</head>
<body>

<div class="container">
    <h2>🧬 Wizdam Identifier Generator (Web-Batch Mode)</h2>
    <p>Skrip ini akan menginjeksi PII dan eLocator ke artikel lawas secara bertahap (50 artikel per *request*) untuk menghindari beban <i>Server Timeout</i>.</p>
    
    <div id="controls">
        <button onclick="start()" id="btnStart">MULAI INJEKSI MASSAL</button>
    </div>
    
    <div style="margin-top:15px; font-size: 1.2em; font-weight: bold;" id="status">Status: Menunggu instruksi...</div>
    <div class="log-box" id="logs"></div>
</div>

<script>
    function start() {
        document.getElementById('btnStart').style.display = 'none';
        document.getElementById('status').innerText = "Menghubungkan ke database...";
        document.getElementById('status').style.color = "#1e90ff";
        runBatch(0);
    }

    function runBatch(offset) {
        let maxLimit = offset + <?php echo BATCH_SIZE; ?>;
        document.getElementById('status').innerText = "Menganalisis artikel ke-" + offset + " s/d " + maxLimit + " ...";
        
        fetch('?action=run&offset=' + offset)
            .then(res => res.text()) 
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error("Respon Server Gagal (Mungkin timeout atau fatal error): \n" + text.substring(0, 300));
                }
            })
            .then(data => {
                if (data.status === 'error') {
                    log("FATAL: " + data.message, 'error');
                    return;
                }

                // Cetak Log ke layar
                if (data.logs.length > 0) {
                    data.logs.forEach(l => log(l));
                }

                if (data.status === 'continue') {
                    // Beri jeda 0.5 detik antar request AJAX agar CPU hosting tidak tersedak
                    setTimeout(() => {
                        runBatch(data.next_offset);
                    }, 500); 
                } else {
                    document.getElementById('status').innerText = "🔥 OPERASI SELESAI TOTAL.";
                    document.getElementById('status').style.color = "#2ed573";
                    log("<strong style='color:#fff'>✅ Seluruh arsip artikel berhasil dimigrasikan dengan identitas Wizdam.</strong>", 'success');
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
        box.scrollTop = box.scrollHeight; // Auto-scroll ke bawah
    }
</script>
</body>
</html>