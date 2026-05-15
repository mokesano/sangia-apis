# Wizdam Ecosystem — Unified Database Guide

Panduan ini berlaku untuk semua repository Wizdam yang menggunakan database terpusat `wizdam_ecosystem`.

| Repository | Domain | Fungsi |
|---|---|---|
| `sdgs-mapper` | Research archetype | SDG classification, researcher mapping, analytics backend |
| `SDGs-analytics` | sangia.org | Analytics, trends, dashboard UI |
| `wizdam-apis` | API layer | Multi-source citation API, impact scoring, trend analysis, policy recommendations |
| `wizdam-sikola` | stipwunaraha.ac.id | Core academic platform (OJS-based), researcher profile hub |

**Semua aplikasi menggunakan satu database terpusat**: `wizdam_ecosystem` di server yang sama.

---

## Arsitektur Data Flow

```
External APIs (ORCID, Scopus, Crossref, OpenAlex, SemanticScholar, PubMed)
        ↓
wizdam-apis (stateless analysis engine)
        ↓ returns raw_data + analysis results
wizdam-sikola (owns all persistence)
        ↓ stores results
wizdam_ecosystem DB (shared, central authority)
        ↓
sdgs-mapper (knowledge base updates)
SDGs-analytics (reads for dashboard)
```

**Prinsip penting**: 
- **wizdam-apis TIDAK menyimpan apapun** — semua persistensi dimiliki wizdam-sikola
- **wizdam-sikola ADALAH DB owner** — ia memutuskan apa yang disimpan, kemana, dan kapan
- **sdgs-mapper & analytics ADALAH read-heavy** — mereka aggregator dan presenter data

---

## Setup Database (Jalankan Sekali di Server)

### 1. Buat database & user

```bash
mysql -u root -p <<'SQL'
CREATE DATABASE IF NOT EXISTS wizdam_ecosystem
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Main application user (full access)
CREATE USER IF NOT EXISTS 'wizdam_app'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_INI';
GRANT ALL PRIVILEGES ON wizdam_ecosystem.* TO 'wizdam_app'@'localhost';

-- Optional: per-repo users untuk least-privilege (lihat bagian "Hak Akses")
-- CREATE USER 'wizdam_apis'@'localhost' IDENTIFIED BY 'pass_apis';
-- CREATE USER 'wizdam_sikola'@'localhost' IDENTIFIED BY 'pass_sikola';
-- dst...

FLUSH PRIVILEGES;
SQL
```

### 2. Jalankan schema

```bash
# Dari sdgs-mapper (canonical schema owner):
mysql -u root -p wizdam_ecosystem < db/schema.sql

# Dari wizdam-apis (optional additions):
mysql -u root -p wizdam_ecosystem < db/schema.sql
```

> **Catatan**: wizdam-apis tidak memiliki schema sendiri — hanya menggunakan tabel terpusat. File `db/schema.sql` di wizdam-apis hanya berisi dokumentasi referensi.

---

## Konfigurasi `.env` (Sama untuk Semua Repo)

Setiap repository memiliki `.env` dengan kredensial DB yang sama:

```env
# Database
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=wizdam_ecosystem
DB_USERNAME=wizdam_app
DB_PASSWORD=GANTI_PASSWORD_INI
DB_CHARSET=utf8mb4

# Aplikasi spesifik (wizdam-apis saja)
WIZDAM_SHARED_SECRET=<generate-with-openssl-rand-hex-32>
SEMANTIC_SCHOLAR_API_KEY=<dari-semanticscholar.org/product/api>
PUBMED_API_KEY=<dari-ncbi.nlm.nih.gov/account>
OPENALEX_MAILTO=api@sangia.org
```

---

## Struktur Tabel (10 Entitas Unified)

### Layer 1 — Identity (Institusi & Pengguna)

| Tabel | Ditulis oleh | Dibaca oleh | Keterangan |
|---|---|---|---|
| `institutions` | sikola | semua | Data institusi/universitas (universitas, politeknik, dll) |
| `researchers` | mapper, sikola | semua | Profil peneliti dari ORCID (unified across all sources) |

**Catatan**: tabel `users`, `user_sessions`, `user_2fa` dikelola oleh OJS/wizdam-sikola sendiri (tidak dalam unified schema).

### Layer 2 — Knowledge (Publikasi & Jurnal)

| Tabel | Ditulis oleh | Dibaca oleh | Keterangan |
|---|---|---|---|
| `journals` | mapper, sikola | semua | Metadata jurnal (ISSN, SJR, SINTA rank, dll) |
| `publications` | mapper, sikola | semua | Artikel, paper, karya ilmiah (unified: DOI + put_code + title + abstract + metadata) |
| `publication_authors` | mapper, sikola | semua | Relasi publikasi ↔ peneliti (many-to-many dengan author order) |

### Layer 3 — Intelligence (SDG Mapping & Impact)

| Tabel | Ditulis oleh | Dibaca oleh | Keterangan |
|---|---|---|---|
| `work_sdgs` | mapper | analytics, sikola | SDG mapping per publikasi (granular: SDG code + confidence + target) |
| `ecosystem_cache` | semua | semua | Central cache layer (key-value dengan TTL) untuk API responses, ORCID data, Scopus profiles |
| `analytics_snapshots` | analytics | analytics, sikola | Snapshot statistik: tren SDG, platform metrics, impact scores per tahun |

### Layer 4 — Platform (API & Jobs)

| Tabel | Ditulis oleh | Dibaca oleh | Keterangan |
|---|---|---|---|
| `api_keys` | sikola, apis | apis | API key hashes (sha256), revocation status (`is_active`), permissions |
| `api_rate_limits` | apis | apis | Rate limit counters (fixed-window per user_id) — **opsional** jika file-based cukup |
| `jobs` | semua | semua | Background job queue (untuk async processing di masa depan) |

**Catatan**: wizdam-apis juga menggunakan file-based fallback (`writable/revoked_keys.txt`, `writable/ratelimit/`) untuk offline resilience.

---

## API Keys — Lifecycle

### Generasi (oleh wizdam-sikola)

```php
// Di wizdam-sikola saat user request API key
$secret = env('WIZDAM_SHARED_SECRET'); // shared dengan wizdam-apis
$key = ApiKeyMiddleware::generateKey($userId, $secret);
// Format: wz_{user_id}_{timestamp}_{hmac16}

// Simpan hash ke shared DB
DB::table('api_keys')->insert([
    'key_hash'   => hash('sha256', $key),
    'user_id'    => $userId,
    'is_active'  => 1,
    'created_at' => now(),
]);

// Kirim plaintext key ke user (display sekali saja)
```

### Validasi (oleh wizdam-apis, stateless)

```php
// ApiKeyMiddleware::validate() — HMAC-based, tidak butuh DB
$expected_hmac = substr(
    hash_hmac('sha256', "$userId:$timestamp", $secret),
    0, 16
);
if ($expected_hmac !== $presented_hmac) {
    reject(401); // Invalid key
}

// Kemudian cek revokasi
$revoked = DB::table('api_keys')
    ->where('key_hash', hash('sha256', $key))
    ->where('is_active', 0)
    ->exists();
if ($revoked) {
    reject(401); // Key revoked
}
```

### Revokasi (oleh wizdam-sikola atau user)

```php
// POST /api/v1/admin/keys/revoke (dipanggil oleh wizdam-sikola)
// AdminController::revokeKey()

// Update di DB (primary)
UPDATE api_keys SET is_active = 0 WHERE key_hash = ?

// Fallback file (jika key belum ada di DB)
// File: writable/revoked_keys.txt (satu sha256 hash per baris)
```

---

## Rate Limiting

### Database-backed (jika `api_rate_limits` ada)

```
Fixed-window per user_id:
  1. Calculate window_start = floor(now / window_size) * window_size
  2. UPSERT api_rate_limits (user_id, window_start, hit_count++)
  3. Check if hit_count > max_requests → reject 429
  4. Prune old windows
```

### File-backed (fallback, default)

```
Sliding window per user MD5(user_id):
  File: writable/ratelimit/{md5}.json
  Content: { "hits": [timestamp1, timestamp2, ...] }
```

**Default limits** (dari `.env.example`):
```
RATE_LIMIT_REQUESTS=60    # requests per window
RATE_LIMIT_WINDOW=60      # window size in seconds (1 req/sec)
```

---

## wizdam-apis — Data Sources & Clients

### Multi-source Citation API (`GET /api/v1/citation/doi`)

Mencari artikel yang **mengutip** DOI yang diberikan dari 4 sumber berbeda:

| Client | Coverage | API Key | Response |
|---|---|---|---|
| `OpenCitationsClient` | DOI-indexed journals | Tidak | citing_doi, timespan |
| `SemanticScholarClient` | 200M+ papers, AI-enriched | `SEMANTIC_SCHOLAR_API_KEY` | citing_doi, title, year, citations_count, authors |
| `OpenAlexClient` | 250M+ works global graph | `OPENALEX_MAILTO` (polite pool) | citing_doi, title, year, authors |
| `PubMedClient` | Biomedical/life sciences | `PUBMED_API_KEY` (optional) | citing_doi, title, year, authors |

**Consolidation**: Hasil dari 4 sumber **di-deduplikasi** berdasarkan DOI. Setiap entry dalam hasil final memiliki field `sources: ['openalex', 'semantic_scholar']` menunjukkan sumber mana yang mengkonfirmasi.

### ORCID Profile API (`GET /api/v1/orcid/profile`)

```
Input:  ORCID iD (e.g., 0000-0002-1234-5678)
Output: person summary + works list

Supplied data (dari wizdam-sikola):
  - supplied_works: skip ORCID fetch, langsung proses
  - supplied_person: skip person fetch

Extracted fields untuk downstream:
  - scopus_author_id: dari ORCID external-identifiers
  - researcher_id (RID/Web of Science)
  - keywords, affiliations, emails
```

### Impact Score API (`POST /api/v1/impact/calculate`)

4 pilar (batched karena dapat 100+ publikasi):

```
Academic (40%)    = h-index + citations + publication count (dari ORCID/Scopus)
Social (25%)      = media mentions + policy citations + social shares (dari wizdam-sikola inputs)
Economic (20%)    = industry adoption + patents + tech transfer (dari wizdam-sikola inputs)
SDG (15%)         = coverage + confidence dari work_sdgs
```

Hasil: `composite = Σ(pillar × weight)` per peneliti.

### Trend Analysis API (`POST /api/v1/trend/analyze`)

Analisis tren peneliti dalam 4 mode:

| Mode | Input | Output |
|---|---|---|
| `impact_trajectory` | ORCID + supplied_works | yearly_metrics: pub count, citations, trend slope |
| `sdg_evolution` | ORCID + titles + years | SDG shift per tahun, emerging SDGs |
| `collaboration_network` | authors_string per work | co-author network, repeat collaboration %, top collaborators |
| `citation_growth` | Scopus ID | citation accumulation curve, h-index |

### Policy Recommendation API (`POST /api/v1/recommendation/policy`)

Generate rekomendasi berbasis data untuk 5 stakeholder type:

| Stakeholder | Rekomendasi | Contoh |
|---|---|---|
| `government` | GOV-01 to GOV-04 | Modernisasi lab, program beasiswa, TTO, SDG focus |
| `institution` | INST-01 to INST-03 | Partnership identification, funding strategy, capacity building |
| `industry` | IND-01 to IND-02 | R&D partnership, sustainability initiatives |
| `researcher` | RES-01 to RES-02 | Profile optimization, collaboration strategy |
| `community` | COM-01 | Open access initiatives |

**Output**: language-agnostic (hanya `id`, `activity_keys`, `time_horizon_key` — Wizdam Sikola handle i18n).

---

## Cara Menghubungkan Repo ke Database Ini

### PHP (PDO)

```php
// Option 1: Manual PDO
$pdo = new PDO(
    "mysql:host=localhost;dbname=wizdam_ecosystem;charset=utf8mb4",
    'wizdam_app',
    'GANTI_PASSWORD_INI',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Option 2: Gunakan class Connection (setiap repo punya sendiri)
// wizdam-apis: Sangia\Database\Connection::get()
$pdo = Connection::get(); // returns null jika DB tidak terkonfigurasi
if ($pdo === null) {
    // Fallback ke file-based operation
}
```

### OJS/wizdam-sikola

Di `config.inc.php`:

```ini
[database]
driver   = mysqli
host     = localhost
username = wizdam_app
password = GANTI_PASSWORD_INI
name     = wizdam_ecosystem
```

### SDGs-analytics (Python)

```python
import mysql.connector

conn = mysql.connector.connect(
    host     = "localhost",
    database = "wizdam_ecosystem",
    user     = "wizdam_app",
    password = "GANTI_PASSWORD_INI",
    charset  = "utf8mb4"
)
```

---

## Hak Akses per Repo (Least Privilege — Opsional)

Untuk keamanan berlapis, buat user terpisah per aplikasi:

```sql
-- wizdam-apis: key management + rate limits (read-heavy on api_keys)
CREATE USER 'wizdam_apis'@'localhost' IDENTIFIED BY 'pass_apis';
GRANT SELECT, INSERT, UPDATE ON wizdam_ecosystem.api_keys       TO 'wizdam_apis'@'localhost';
GRANT SELECT, INSERT, UPDATE ON wizdam_ecosystem.api_rate_limits TO 'wizdam_apis'@'localhost';

-- wizdam-sikola: semua identity layer + publikasi
CREATE USER 'wizdam_sikola'@'localhost' IDENTIFIED BY 'pass_sikola';
GRANT SELECT, INSERT, UPDATE, DELETE ON wizdam_ecosystem.institutions      TO 'wizdam_sikola'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON wizdam_ecosystem.researchers       TO 'wizdam_sikola'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON wizdam_ecosystem.publications      TO 'wizdam_sikola'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON wizdam_ecosystem.publication_authors TO 'wizdam_sikola'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON wizdam_ecosystem.analytics_snapshots TO 'wizdam_sikola'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON wizdam_ecosystem.ecosystem_cache    TO 'wizdam_sikola'@'localhost';

-- sdgs-mapper: knowledge base updates (mapper/writer)
CREATE USER 'wizdam_mapper'@'localhost' IDENTIFIED BY 'pass_mapper';
GRANT SELECT, INSERT, UPDATE ON wizdam_ecosystem.researchers            TO 'wizdam_mapper'@'localhost';
GRANT SELECT, INSERT, UPDATE ON wizdam_ecosystem.publications           TO 'wizdam_mapper'@'localhost';
GRANT SELECT, INSERT, UPDATE ON wizdam_ecosystem.publication_authors    TO 'wizdam_mapper'@'localhost';
GRANT SELECT, INSERT, UPDATE ON wizdam_ecosystem.work_sdgs              TO 'wizdam_mapper'@'localhost';
GRANT SELECT, INSERT, UPDATE ON wizdam_ecosystem.journals               TO 'wizdam_mapper'@'localhost';
GRANT SELECT, INSERT, UPDATE ON wizdam_ecosystem.ecosystem_cache        TO 'wizdam_mapper'@'localhost';

-- SDGs-analytics: read-only
CREATE USER 'wizdam_analytics'@'localhost' IDENTIFIED BY 'pass_analytics';
GRANT SELECT ON wizdam_ecosystem.researchers       TO 'wizdam_analytics'@'localhost';
GRANT SELECT ON wizdam_ecosystem.publications      TO 'wizdam_analytics'@'localhost';
GRANT SELECT ON wizdam_ecosystem.work_sdgs         TO 'wizdam_analytics'@'localhost';
GRANT SELECT ON wizdam_ecosystem.analytics_snapshots TO 'wizdam_analytics'@'localhost';

FLUSH PRIVILEGES;
```

---

## Index dan Performa

Tabel-tabel utama sudah memiliki index optimal:

- **`publications`**: FULLTEXT(title, abstract), INDEX(doi), INDEX(publication_year)
- **`work_sdgs`**: UNIQUE(publication_id, sdg_code), INDEX(confidence_score)
- **`api_keys`**: UNIQUE(key_hash), INDEX(is_active)
- **`researchers`**: INDEX(orcid), INDEX(impact_score), INDEX(affiliation_id)

Untuk query berat (analytics), gunakan `analytics_snapshots` sebagai pre-computed aggregates.

---

## Catatan Teknis

### PostgreSQL Support

Semua kode PHP mendukung:
```php
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'pgsql') {
    // PostgreSQL-specific SQL (ON CONFLICT, SERIAL, dsb.)
} else {
    // MySQL/MariaDB (ON DUPLICATE KEY UPDATE, AUTO_INCREMENT)
}
```

Untuk PostgreSQL, sesuaikan:
- `AUTO_INCREMENT` → `SERIAL / BIGSERIAL`
- `ENUM` → `VARCHAR CHECK` atau custom type
- `JSON` → native JSON/JSONB type
- `FULLTEXT` → GIN index on tsvector

### Offline Resilience

wizdam-apis dirancang untuk bekerja tanpa DB:
- API key validation: stateless HMAC (tidak butuh DB)
- Rate limiting: fallback ke file `writable/ratelimit/`
- Revocation: fallback ke file `writable/revoked_keys.txt`

Jika DB down, endpoint lain (impact, trend, recommendation) tetap berjalan asalkan data dikompute dari supplied parameters.

---

**Last updated**: 2026-05-15  
**Schema version**: v1.0-unified  
**Canonical authority**: sdgs-mapper/db/schema.sql
