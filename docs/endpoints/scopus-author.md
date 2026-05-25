# Scopus Author

Fetch a researcher's author profile and recent publications from the Scopus API (Elsevier).

**Method:** `GET`  
**Path:** `/api/v1/scopus/author`  
**Auth:** `X-API-Key` required  
**Timeout:** 45 seconds  
**Requires:** `SCOPUS_API_KEY` environment variable on the sangia-apis server

---

## Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `authorid` | string | Yes | — | Scopus Author ID (numeric, e.g. `57200000000`) |
| `count` | int | No | `10` | Number of recent publications to return (max `25`) |
| `refresh` | bool | No | `false` | Force re-fetch from Scopus API |

---

## Request Body (optional — supplied data)

Send pre-fetched Scopus data from Sangia Sikola DB to skip the Scopus cURL call:

```json
{
  "supplied_scopus": {
    "author": {
      "full_name":      "Budi Santoso",
      "affiliation":    "Universitas Indonesia",
      "h_index":        18,
      "document_count": 145,
      "citation_count": 3200,
      "cited_by_count": 2980,
      "orcid":          "0000-0002-1234-5678",
      "data_source":    "scopus"
    },
    "publications": [
      {
        "eid":            "2-s2.0-85000000000",
        "doi":            "10.1234/example",
        "title":          "Solar Panel Adoption in Rural Java",
        "journal":        "Renewable Energy",
        "year":           2023,
        "cited_by_count": 42,
        "open_access":    true
      }
    ]
  }
}
```

---

## Response

### Fresh fetch from Scopus API

```json
{
  "status":    "success",
  "author_id": "57200000000",
  "author": {
    "full_name":      "Budi Santoso",
    "first_name":     "Budi",
    "last_name":      "Santoso",
    "affiliation":    "Universitas Indonesia",
    "h_index":        18,
    "document_count": 145,
    "citation_count": 3200,
    "cited_by_count": 2980,
    "orcid":          "0000-0002-1234-5678",
    "data_source":    "scopus"
  },
  "publications": [
    {
      "eid":            "2-s2.0-85000000000",
      "doi":            "10.1234/example",
      "title":          "Solar Panel Adoption in Rural Java",
      "journal":        "Renewable Energy",
      "year":           2023,
      "cited_by_count": 42,
      "open_access":    true
    }
  ],
  "data_source": "scopus_api",
  "raw_data": {
    "author":       { "...": "full Scopus author object" },
    "publications": [ { "...": "..." } ],
    "fetched_at":   "2025-01-01T00:00:00+00:00"
  },
  "cache_info": { "from_cache": false }
}
```

### From Sangia Sikola DB

```json
{
  "status":      "success",
  "author_id":   "57200000000",
  "author":      { "...": "from supplied_scopus.author" },
  "publications":[ { "...": "from supplied_scopus.publications" } ],
  "data_source": "sangia_sikola_db",
  "cache_info":  { "from_cache": false }
}
```

---

## Error Responses

| Code | Message | Cause |
|------|---------|-------|
| 400 | `authorid is required` | Missing `authorid` parameter |
| 404 | `Author not found in Scopus` | Scopus ID does not exist |
| 503 | `Scopus API key not configured` | `SCOPUS_API_KEY` missing on server |
| 502 | `Scopus API error` | Scopus API unreachable |

---

## Usage in Sangia Sikola

### 1. Enrich Researcher Profile with Scopus Data

Scopus provides h-index, citation count, and verified publication list — critical for the Academic pillar of Sangia Impact Score.

```php
public function enrichWithScopus(string $orcid, string $scopusId): array
{
    // Check DB first
    $cache = AuthorProfileCache::find($orcid);

    $body = [];
    if ($cache?->scopus_data) {
        $body['supplied_scopus'] = json_decode($cache->scopus_data, true);
    }

    $result = $this->get("/api/v1/scopus/author?authorid=$scopusId&count=25", $body);

    if ($result['status'] === 'success' && isset($result['raw_data'])) {
        AuthorProfileCache::updateOrCreate(['orcid' => $orcid], [
            'scopus_data' => json_encode([
                'author'       => $result['author'],
                'publications' => $result['publications'],
            ]),
        ]);
    }

    return $result;
}
```

### 2. Link Scopus ID to ORCID

ORCID works often include the Scopus Author ID in `external_ids`. Sangia Sikola should extract and store it:

```php
$orcidProfile = $this->getOrcidProfile($orcid);
foreach ($orcidProfile['person_summary']['external_ids'] as $id) {
    if ($id['type'] === 'Scopus Author ID') {
        ResearcherProfile::where('orcid', $orcid)
            ->update(['scopus_id' => $id['value']]);
        break;
    }
}
```

### 3. Recommended DB Schema

```sql
-- Add Scopus data to author_profiles_cache
ALTER TABLE author_profiles_cache
  ADD COLUMN scopus_id   VARCHAR(20)  NULL,
  ADD COLUMN scopus_data JSON         NULL;

-- Denormalized metrics for fast queries (leaderboard, filtering)
CREATE TABLE researcher_metrics (
  orcid           VARCHAR(19) PRIMARY KEY,
  scopus_id       VARCHAR(20),
  h_index         SMALLINT UNSIGNED DEFAULT 0,
  citation_count  INT UNSIGNED DEFAULT 0,
  document_count  INT UNSIGNED DEFAULT 0,
  last_synced_at  DATETIME,
  FOREIGN KEY (orcid) REFERENCES author_profiles_cache(orcid)
);
```

### 4. Use in Sangia Sikola Dashboard

The Scopus data powers several UI components:
- **Researcher card:** h-index badge, citation count, document count
- **Impact Score page:** Academic pillar uses `h_index`, `citation_count`, `document_count`
- **Publication list:** sorted by `cited_by_count` (most-cited first)
- **Open access indicator:** `open_access` flag on each publication
