# Catatan Pengembangan untuk Wizdam Sikola

Dokumen ini berisi poin-poin penting yang harus diperhatikan saat membangun interface Wizdam Sikola sebagai frontend dari Sangia API Engine (wizdam-apis).

---

## 1. Autentikasi API Key

Wizdam Sikola adalah **satu-satunya** yang men-generate API key untuk user.

### Generate key (PHP — gunakan di backend Wizdam Sikola):
```php
use Sangia\Gateway\ApiKeyMiddleware;

$secret = env('WIZDAM_SHARED_SECRET'); // harus identik di kedua sistem
$key    = ApiKeyMiddleware::generateKey($userId, $secret);
// Simpan $key ke tabel users (kolom api_key) di wizdam_sikola DB
// Kirim $key ke user melalui UI
```

### Cabut key:
```php
// Panggil endpoint admin wizdam-apis
POST /api/v1/admin/keys/revoke
X-API-Key: {service_key_wizdam_sikola}
{ "key": "wz_42_1719000000_a3f8e2c1d5b7" }
```

**Penting:** Simpan `WIZDAM_SHARED_SECRET` yang **identik** di `.env` kedua sistem.

---

## 2. Pengelolaan Bobot Analisis (Admin Panel)

Wizdam Sikola mengontrol penuh semua bobot analisis melalui admin panel.  
Bobot dikirimkan ke wizdam-apis dalam setiap request — nilai dalam kode hanya fallback.

### Bobot yang bisa dikonfigurasi:

#### a) Bobot SDG Scoring (per versi)
```json
{
  "weights": {
    "keyword": 0.30,
    "similarity": 0.30,
    "substantive": 0.20,
    "causal": 0.20,
    "max_sdgs": 7,
    "thresholds": {
      "min": 0.20,
      "confidence": 0.30,
      "high": 0.60
    }
  }
}
```
Kirim di body request `POST /api/v1/sdg/{version}/classify`.

#### b) Bobot Komposit Wizdam Impact Score
```json
{
  "weights": {
    "academic": 0.40,
    "social": 0.25,
    "economic": 0.20,
    "sdg": 0.15
  }
}
```
Kirim di body request `POST /api/v1/impact/calculate`.

### Rekomendasi tabel di DB Wizdam Sikola:
```sql
CREATE TABLE analysis_weight_configs (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  config_key  VARCHAR(50) UNIQUE NOT NULL,  -- e.g. 'sdg_v5', 'impact_composite'
  weights     JSON NOT NULL,
  updated_by  INT,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

Saat memanggil API, load config dari DB dan sertakan dalam request body.

---

## 3. Pola Batch Anti-Timeout (ORCID)

Untuk endpoint yang memproses banyak karya (SDG classify dengan ORCID, Impact Score):

```javascript
// Contoh implementasi di Wizdam Sikola (Vue/React/Vanilla JS)
async function runBatchAnalysis(endpoint, payload, onProgress) {
  let offset = 0;
  const batchSize = 20;

  while (true) {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'X-API-Key': userApiKey,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ ...payload, offset, batch_size: batchSize })
    });

    const data = await response.json();

    if (data.status === 'error') throw new Error(data.message);

    if (data.status === 'processing') {
      onProgress?.(data.progress); // { processed, total_works, percent }
      offset = data.next_offset;
      await new Promise(r => setTimeout(r, 300)); // 300ms delay
      continue;
    }

    if (data.status === 'success') return data;
  }
}

// Penggunaan:
const result = await runBatchAnalysis(
  '/api/v1/impact/calculate',
  { orcid: '0000-0002-1234-5678', scopus_id: '57200000000', social: {...}, economic: {...} },
  (progress) => updateProgressBar(progress.percent)
);
```

**Penting:** Jika server mengembalikan `status: "error", code: 410`, artinya session batch expired — restart dari `offset: 0`.

---

## 4. Suplai Data untuk WizdamScoreEngine

Wizdam Impact Score menjadi **powerful** jika pilar Social dan Economic diisi dengan data nyata.

### Data Social Pillar (0–100 per metrik):
| Field | Cara Mendapatkan |
|-------|-----------------|
| `media_mentions` | Crawler berita/media (Google News API, MediaStack) |
| `policy_citations` | Input manual admin / crawler kebijakan |
| `social_shares` | Altmetric API, Twitter/X API |
| `news_coverage` | Crawler berita Indonesia (Kompas, Tempo, dll) |

### Data Economic Pillar (0–100 per metrik):
| Field | Cara Mendapatkan |
|-------|-----------------|
| `industry_adoption` | Input manual user/admin |
| `patents` | Crawler SIPO / Google Patents |
| `tech_transfer` | Input manual via form peneliti |
| `startup_spinoffs` | Input manual / data DIKTI |

### Rekomendasi flow data:
1. User mengisi data social/economic di profil Wizdam Sikola
2. Admin dapat memverifikasi dan menambahkan data dari crawler
3. Data disimpan di tabel `researcher_impact_inputs` di DB Wizdam Sikola
4. Saat memanggil `/api/v1/impact/calculate`, load dari DB dan kirim ke API

```sql
CREATE TABLE researcher_impact_inputs (
  id              INT PRIMARY KEY AUTO_INCREMENT,
  orcid           VARCHAR(19) NOT NULL,
  input_type      ENUM('social', 'economic') NOT NULL,
  field_key       VARCHAR(50) NOT NULL,
  value           DECIMAL(5,2) NOT NULL,   -- 0.00 – 100.00
  source          ENUM('user_input', 'crawler', 'admin', 'api') DEFAULT 'user_input',
  verified        BOOLEAN DEFAULT FALSE,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY      uq_orcid_type_field (orcid, input_type, field_key)
);
```

---

## 5. Strategi Caching

Wizdam-apis meng-cache semua hasil di server-side (filesystem, TTL 7 hari).  
Wizdam Sikola **tidak perlu** re-cache hasil API — cukup gunakan `refresh: false` (default).

Gunakan `refresh: true` hanya ketika:
- User klik tombol "Refresh" secara eksplisit
- Admin memicu update massal dari admin panel
- Setelah data baru ditambahkan (social/economic inputs)

---

## 6. Arsitektur yang Disarankan di Wizdam Sikola

```
Wizdam Sikola (Frontend + Backend PHP/Laravel)
│
├── Admin Panel
│   ├── User Management (generate/revoke API keys)
│   ├── Weight Configuration (simpan ke DB → kirim ke API)
│   ├── Crawler Management (trigger crawl social/economic data)
│   └── System Monitor (health check API, cache stats)
│
├── Researcher Dashboard
│   ├── Input Social/Economic Data
│   ├── Trigger Impact Score Calculation (with progress bar)
│   └── View SDG Analysis Results
│
└── API Integration Layer
    ├── SangiaApiClient.php (wrapper untuk semua call ke wizdam-apis)
    ├── WeightConfigService.php (load config dari DB, sertakan di request)
    └── BatchProcessor.php (handle pola loop batch)
```

### SangiaApiClient.php (contoh skeleton):
```php
class SangiaApiClient {
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $apiKey) {
        $this->baseUrl = config('services.sangia.url');
        $this->apiKey  = $apiKey;
    }

    public function classifySdg(string $version, array $params): array {
        $weights = WeightConfigService::getForSdg($version); // dari DB admin config
        return $this->post("/api/v1/sdg/$version/classify", array_merge($params, ['weights' => $weights]));
    }

    public function calculateImpact(array $params): array {
        $weights = WeightConfigService::getForImpact(); // dari DB admin config
        return $this->batchPost('/api/v1/impact/calculate', array_merge($params, ['weights' => $weights]));
    }

    private function batchPost(string $endpoint, array $payload): array {
        $offset = 0;
        do {
            $result = $this->post($endpoint, array_merge($payload, ['offset' => $offset]));
            if ($result['status'] === 'processing') {
                $offset = $result['next_offset'];
                usleep(300000); // 300ms
            }
        } while (($result['status'] ?? '') === 'processing');
        return $result;
    }

    private function post(string $path, array $body): array { /* ... */ }
}
```

---

## 7. CORS

Tambahkan domain Wizdam Sikola ke `CORS_ALLOWED_ORIGINS` di `.env` wizdam-apis:
```
CORS_ALLOWED_ORIGINS=https://app.wizdam.id,https://admin.wizdam.id,http://localhost:3000
```

---

## 8. Monitoring & Logging

Wizdam Sikola sebaiknya menyimpan log setiap call ke wizdam-apis di DB:
```sql
CREATE TABLE api_call_logs (
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id       INT,
  endpoint      VARCHAR(100),
  params        JSON,
  status        VARCHAR(20),
  duration_ms   INT,
  from_cache    BOOLEAN,
  called_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

Ini berguna untuk:
- Audit penggunaan API per user
- Deteksi bottleneck
- Billing / quota management

---

## 9. Versi SDG yang Direkomendasikan

| Versi | Rekomendasi Penggunaan |
|-------|----------------------|
| `v5` | **Default** — gunakan untuk semua analisis produksi |
| `v5e` | Eksperimental — untuk testing weight baru |
| `v4` | Jika ingin bobot substantive lebih tinggi |
| `v0` | Hanya untuk komparasi / benchmark keyword-only |

Sediakan dropdown di UI untuk memilih versi, dengan default `v5`.

---

## 10. Struktur Response Standard

Semua response API mengikuti pola:
```json
{
  "status": "success" | "error" | "processing",
  "data": {},
  "cache_info": { "from_cache": true | false },
  "api_version": "v1.1-batch"
}
```

Selalu periksa `status` sebelum memproses data. Jika `"processing"`, lakukan loop batch.
