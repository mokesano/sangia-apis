# Sangia API Engine — Dokumentasi API

**Base URL:** `https://api.sangia.org`  
**Versi API:** v1  
**Autentikasi:** `X-API-Key: wz_{user_id}_{timestamp}_{hmac16}`

> API key dihasilkan oleh **Wizdam Sikola** dan divalidasi secara stateless menggunakan HMAC-SHA256.  
> Semua endpoint wajib menyertakan API key kecuali yang ditandai _(publik)_.

---

## Daftar Endpoint

| Method | Endpoint | Auth | Keterangan |
|--------|----------|------|------------|
| GET | `/health` | Publik | Status layanan |
| GET | `/api/v1` | Publik | Katalog endpoint |
| GET | `/api/v1/sdg/versions` | Publik | Daftar versi SDG + bobot default |
| POST | `/api/v1/sdg/{version}/classify` | API Key | Klasifikasi SDG |
| POST | `/api/v1/sdg/classify` | API Key | Alias v5 |
| GET | `/api/v1/scopus/author` | API Key | Profil author Scopus |
| GET | `/api/v1/orcid/profile` | API Key | Profil peneliti ORCID |
| GET | `/api/v1/citation/doi` | API Key | Sitasi multi-sumber |
| GET | `/api/v1/journal/metrics` | API Key | Metrik jurnal Scopus |
| GET | `/api/v1/sinta/score` | API Key | Skor jurnal SINTA |
| POST | `/api/v1/impact/calculate` | API Key | Wizdam Impact Score |
| POST | `/api/v1/admin/keys/revoke` | API Key | Cabut API key |

---

## Autentikasi

Kirim API key melalui salah satu cara:
```
X-API-Key: wz_42_1719000000_a3f8e2c1d5b7
Authorization: Bearer wz_42_1719000000_a3f8e2c1d5b7
?api_key=wz_42_1719000000_a3f8e2c1d5b7
```

**Format key:** `wz_{user_id}_{unix_timestamp}_{hmac16}`  
- `hmac16` = 16 karakter pertama dari `HMAC-SHA256(user_id:timestamp, WIZDAM_SHARED_SECRET)`  
- TTL: 1 tahun sejak `timestamp`

**Response 401 jika key tidak valid:**
```json
{ "status": "error", "code": 401, "message": "Invalid or expired API key." }
```

**Rate Limit:** 60 request/60 detik per API key (default). Dapat dikonfigurasi via env.  
Header response: `X-RateLimit-Limit`, `X-RateLimit-Remaining`

---

## Override Bobot Analisis

Semua endpoint SDG classify dan impact calculate menerima objek `weights` dalam request body.  
Bobot dari Wizdam Sikola admin panel **selalu prioritas**; nilai default dalam kode hanya fallback.

### SDG Classify — override bobot + threshold:
```json
{
  "title": "...",
  "weights": {
    "keyword": 0.25,
    "similarity": 0.30,
    "substantive": 0.25,
    "causal": 0.20,
    "max_sdgs": 5,
    "thresholds": {
      "min": 0.18,
      "confidence": 0.28,
      "high": 0.55
    }
  }
}
```

### Impact Calculate — override bobot komposit:
```json
{
  "orcid": "0000-0002-1234-5678",
  "weights": {
    "academic": 0.45,
    "social": 0.20,
    "economic": 0.20,
    "sdg": 0.15
  }
}
```

---

## Pola Batch Anti-Timeout

Endpoint yang memproses profil ORCID (banyak karya) menggunakan pola batch untuk menghindari PHP timeout.  
Client memanggil endpoint berulang kali dengan `next_offset` sampai mendapat `status: "success"`.

**Parameter:**
- `offset` (int, default `0`) — posisi mulai batch
- `batch_size` (int, default `20`, max `50`) — jumlah karya per request

**Contoh alur (JavaScript/Wizdam Sikola):**
```javascript
async function classifyWithBatch(orcid, endpoint) {
  let offset = 0;
  while (true) {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'X-API-Key': apiKey, 'Content-Type': 'application/json' },
      body: JSON.stringify({ orcid, offset, batch_size: 20 })
    });
    const data = await res.json();
    if (data.status === 'success') return data;
    if (data.status !== 'processing') throw new Error(data.message);
    updateProgressBar(data.progress.percent);
    offset = data.next_offset;
    await delay(300); // jeda 300ms antar batch
  }
}
```

**Response saat processing:**
```json
{
  "status": "processing",
  "orcid": "0000-0002-1234-5678",
  "progress": { "processed": 20, "total_works": 50, "percent": 40 },
  "next_offset": 20
}
```

---

## Endpoint Detail

---

### GET `/health`
Status layanan. Tidak memerlukan API key.

**Response:**
```json
{ "status": "up", "service": "Sangia API Engine", "time": "2025-01-01T00:00:00+00:00" }
```

---

### GET `/api/v1/sdg/versions`
Daftar versi SDG analyzer + bobot dan threshold default.

**Response:**
```json
{
  "status": "success",
  "data": {
    "v5": {
      "label": "Causal-boosted stable (v5.1.8)",
      "weights": { "keyword": 0.30, "similarity": 0.30, "substantive": 0.20, "causal": 0.20 },
      "thresholds": { "min": 0.20, "confidence": 0.30, "high": 0.60 }
    }
  }
}
```

---

### POST `/api/v1/sdg/{version}/classify`
Klasifikasi SDG dari teks, DOI, atau ORCID.  
`{version}` = `v0` | `v1` | `v2` | `v3` | `v4` | `v5` | `v5e`

**Request body:**
```json
{
  "title": "Renewable Energy Adoption in Rural Indonesia",
  "abstract": "This study examines...",
  "orcid": "0000-0002-1234-5678",
  "doi": "10.1234/example",
  "refresh": false,
  "offset": 0,
  "batch_size": 20,
  "weights": { "keyword": 0.30, "similarity": 0.30, "substantive": 0.20, "causal": 0.20 }
}
```
Gunakan **salah satu**: `title+abstract`, `doi`, atau `orcid`. Jika `orcid`, gunakan pola batch.

**Response (title+abstract):**
```json
{
  "status": "success",
  "version": "v5",
  "weights_applied": { "keyword": 0.30, ... },
  "sdg_analysis": {
    "sdgs": ["SDG7", "SDG13"],
    "sdg_confidence": { "SDG7": 0.724, "SDG13": 0.611 },
    "contributor_types": { "SDG7": "Active Contributor" },
    "contribution_pathways": { "SDG7": "clean_energy_access" },
    "detailed_analysis": {
      "SDG7": {
        "score": 0.724,
        "confidence_level": "High",
        "contributor_type": { "type": "Active Contributor", "level": "High" },
        "components": {
          "keyword_score": 0.65,
          "similarity_score": 0.71,
          "substantive_score": 0.80,
          "causal_score": 0.75
        },
        "evidence": { "causal_relationship": ["contributes to clean energy"] }
      }
    }
  }
}
```

---

### GET `/api/v1/scopus/author`
Profil author dan daftar publikasi dari Scopus API. Fallback ke OpenAlex.

**Query params:** `authorid` (required), `count` (1–25, default 10), `refresh` (bool)

**Response:**
```json
{
  "status": "success",
  "author_id": "57200000000",
  "author": {
    "full_name": "Budi Santoso",
    "affiliation": "Universitas Indonesia",
    "h_index": 18,
    "document_count": 145,
    "citation_count": 3200,
    "cited_by_count": 2980,
    "orcid": "0000-0002-1234-5678",
    "data_source": "scopus"
  },
  "publications": [
    {
      "eid": "2-s2.0-85000000000",
      "doi": "10.1234/example",
      "title": "...",
      "journal": "Renewable Energy",
      "year": 2023,
      "cited_by_count": 42,
      "open_access": true
    }
  ],
  "cache_info": { "from_cache": false }
}
```

---

### GET `/api/v1/orcid/profile`
Profil peneliti lengkap dari ORCID public API.

**Query params:** `orcid` (required, format `0000-0000-0000-0000`), `refresh` (bool), `limit` (default 50, max 200)

**Response:**
```json
{
  "status": "success",
  "orcid": "0000-0002-1234-5678",
  "person_summary": {
    "name": "Budi Santoso",
    "given_names": "Budi",
    "family_name": "Santoso",
    "bio": "Researcher in renewable energy...",
    "emails": ["budi@ui.ac.id"],
    "keywords": ["renewable energy", "SDG"],
    "country": "ID"
  },
  "works": [
    {
      "title": "Solar Panel Adoption in Rural Java",
      "doi": "10.1234/example",
      "publication_year": 2023,
      "type": "journal-article",
      "journal_title": "Renewable Energy"
    }
  ],
  "works_count": 87,
  "cache_info": { "from_cache": false }
}
```

---

### GET `/api/v1/citation/doi`
Data sitasi multi-sumber untuk sebuah DOI.

**Query params:** `doi` (required), `limit` (1–50, default 15), `refresh` (bool)

**Sumber:** OpenCitations → Crossref → OpenAlex → Semantic Scholar

**Response:**
```json
{
  "status": "success",
  "doi": "10.1234/example",
  "article_metadata": {
    "title": "Solar Panel Adoption in Rural Java",
    "authors": ["Budi Santoso", "Ani Wijaya"],
    "publication_year": 2023,
    "journal": "Renewable Energy",
    "is_referenced_by": 42
  },
  "citations": {
    "opencitations": [ { "citing_doi": "10.5678/...", "source": "opencitations" } ],
    "crossref": [],
    "openalex": [],
    "semantic_scholar": []
  },
  "citation_count": { "opencitations": 12, "crossref": 0, "openalex": 8, "semantic_scholar": 6 },
  "total_unique": 18,
  "cache_info": { "from_cache": false }
}
```

---

### GET `/api/v1/journal/metrics`
Metrik jurnal dari Scopus Serial Title API.

**Query params:** `issn` (required, format `XXXX-XXXX`), `refresh` (bool)

**Response:**
```json
{
  "status": "success",
  "journal": {
    "title": "Renewable Energy",
    "issn_print": "0960-1481",
    "issn_electronic": "1879-0682",
    "publisher": "Elsevier",
    "open_access": false,
    "active": true
  },
  "metrics": {
    "citescore": 13.2,
    "sjr": 1.845,
    "sjr_year": "2023",
    "snip": 2.11,
    "snip_year": "2023",
    "quartile": "Q1",
    "subject_areas": [
      { "name": "Renewable Energy, Sustainability and the Environment", "code": "2105", "quartile": "Q1" }
    ]
  },
  "cache_info": { "from_cache": false }
}
```

---

### GET `/api/v1/sinta/score`
Skor dan grade jurnal dari SINTA (Kemenristekdikti).

**Query params:** `issn` (required), `refresh` (bool)

**Response:**
```json
{
  "status": "success",
  "issn": "2549-1385",
  "title": "Jurnal Energi Terbarukan Indonesia",
  "impact": "2.45",
  "grade": "S2",
  "sinta_id": "123456",
  "sinta_url": "https://sinta.kemdiktisaintek.go.id/journals/profile/123456",
  "cache_info": { "from_cache": false }
}
```

---

### POST `/api/v1/impact/calculate`
Hitung Wizdam Impact Score (komposit 4 pilar). Mendukung pola batch.

**Request body:**
```json
{
  "orcid": "0000-0002-1234-5678",
  "scopus_id": "57200000000",
  "social": {
    "media_mentions": 75,
    "policy_citations": 60,
    "social_shares": 80,
    "news_coverage": 50
  },
  "economic": {
    "industry_adoption": 40,
    "patents": 20,
    "tech_transfer": 35,
    "startup_spinoffs": 10
  },
  "weights": {
    "academic": 0.40,
    "social": 0.25,
    "economic": 0.20,
    "sdg": 0.15
  },
  "refresh": false,
  "offset": 0,
  "batch_size": 20
}
```

**Response (final):**
```json
{
  "status": "success",
  "orcid": "0000-0002-1234-5678",
  "name": "Budi Santoso",
  "composite": 68.45,
  "pillars": {
    "academic": 82.10,
    "social": 66.25,
    "economic": 26.25,
    "sdg": 54.80
  },
  "weights": { "academic": 0.40, "social": 0.25, "economic": 0.20, "sdg": 0.15 },
  "sdg_tags": [
    { "sdg": 7, "code": "SDG7", "score": 0.724, "count": 12, "label": "Energi Bersih" }
  ],
  "sdg_by_work": [
    { "title": "Solar Panel Adoption...", "sdgs": [ { "sdg": "SDG7", "score": 0.724 } ] }
  ],
  "academic_metrics": {
    "publication_count": 87,
    "h_index": 18,
    "citation_count": 3200,
    "data_sources": ["orcid", "scopus"]
  },
  "social_inputs": { "media_mentions": 75, "..." : "..." },
  "economic_inputs": { "industry_adoption": 40, "...": "..." },
  "api_version": "v1.1-batch",
  "calculated_at": "2025-01-01T00:00:00+00:00",
  "cache_info": { "from_cache": false }
}
```

**Formula:**
```
Composite = Academic×w_academic + Social×w_social + Economic×w_economic + SDG×w_sdg

Academic  = (min(100, h_index×3.5) × 0.45) + (log10(citations+1)×25 × 0.35) + (min(100, pub_count×1.2) × 0.20)
Social    = avg(media_mentions, policy_citations, social_shares, news_coverage)  [0–100 each]
Economic  = avg(industry_adoption, patents, tech_transfer, startup_spinoffs)     [0–100 each]
SDG       = (coverage_ratio×0.4 + avg_confidence×0.6) × 100
            coverage_ratio = min(1.0, distinct_sdg_count / 5)
```

---

### POST `/api/v1/admin/keys/revoke`
Cabut API key (hanya untuk panggilan dari backend Wizdam Sikola).

**Request body:**
```json
{ "key": "wz_42_1719000000_a3f8e2c1d5b7" }
```

**Response:**
```json
{ "status": "success", "message": "Key revoked" }
```

---

## Error Responses

| HTTP Code | Keterangan |
|-----------|------------|
| 400 | Parameter tidak valid / kurang |
| 401 | API key tidak ada atau tidak valid |
| 404 | Data tidak ditemukan |
| 410 | Batch session expired — restart dari offset=0 |
| 429 | Rate limit exceeded |
| 500 | Internal server error |
| 502 | External API error (ORCID/Scopus/Crossref tidak merespons) |

**Format error:**
```json
{ "status": "error", "code": 400, "message": "orcid is required" }
```

---

## Cache

Semua modul menggunakan filesystem cache di `writable/cache/{module}/` (gzip, TTL 7 hari).  
Tambahkan `"refresh": true` atau `?refresh=true` untuk memaksa fetch ulang dari sumber eksternal.

| Module | Lokasi Cache | TTL |
|--------|-------------|-----|
| WizdamScore | `writable/cache/wizdamscore/` | 7 hari |
| SDG | `writable/cache/sdg/` | 7 hari |
| ORCID | `writable/cache/orchid/` | 24 jam |
| Scopus | `writable/cache/scopus/` | 30 hari |
| Journal | `writable/cache/journal/` | 7 hari |
| SINTA | `writable/cache/sintajournal/` | 7 hari |
| Citation | `writable/cache/citation/` | 7 hari |
| Batch partial | `writable/cache/wizdamscore/partial_*` | 7 hari |
