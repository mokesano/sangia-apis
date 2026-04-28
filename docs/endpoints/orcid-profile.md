# ORCID Profile

Fetch a researcher's full profile and works list from the ORCID Public API.

**Method:** `GET`  
**Path:** `/api/v1/orcid/profile`  
**Auth:** `X-API-Key` required  
**Timeout:** 60 seconds

---

## Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `orcid` | string | Yes | — | ORCID iD in format `0000-0000-0000-0000` |
| `refresh` | bool | No | `false` | Force re-fetch from ORCID API even if `supplied_*` present |
| `limit` | int | No | `50` | Maximum number of works to return (max `200`) |

---

## Request Body (optional — supplied data)

Send pre-fetched data from Wizdam Sikola DB to skip the ORCID cURL call.

```json
{
  "supplied_works": [
    {
      "title":            "Solar Panel Adoption in Rural Java",
      "doi":              "10.1234/example",
      "publication_year": 2023,
      "type":             "journal-article",
      "journal_title":    "Renewable Energy",
      "authors_string":   "Budi Santoso, Ani Wijaya"
    }
  ],
  "supplied_person": {
    "name":        "Budi Santoso",
    "given_names": "Budi",
    "family_name": "Santoso",
    "bio":         "Researcher in renewable energy...",
    "emails":      ["budi@ui.ac.id"],
    "keywords":    ["renewable energy", "SDG"],
    "country":     "ID"
  }
}
```

When supplied data is provided and `refresh` is `false`, the API returns immediately without any external network call. Response will include `"data_source": "wizdam_sikola_db"`.

---

## Response

### Fresh fetch from ORCID API

```json
{
  "status":       "success",
  "orcid":        "0000-0002-1234-5678",
  "works_count":  87,
  "data_source":  "orcid_api",
  "person_summary": {
    "name":         "Budi Santoso",
    "given_names":  "Budi",
    "family_name":  "Santoso",
    "bio":          "Researcher in renewable energy at Universitas Indonesia",
    "emails":       ["budi@ui.ac.id"],
    "keywords":     ["renewable energy", "solar energy", "SDG7"],
    "external_ids": [
      { "type": "Scopus Author ID", "value": "57200000000" }
    ],
    "urls":   [],
    "country": "ID"
  },
  "works": [
    {
      "title":            "Solar Panel Adoption in Rural Java",
      "doi":              "10.1234/example",
      "publication_year": 2023,
      "type":             "journal-article",
      "journal_title":    "Renewable Energy",
      "scopus_id":        null
    }
  ],
  "cache_info": { "from_cache": false },
  "raw_data": {
    "person":     { "...": "full ORCID person object" },
    "works":      [ { "...": "full works array" } ],
    "fetched_at": "2025-01-01T00:00:00+00:00"
  }
}
```

### From Wizdam Sikola DB (supplied data)

```json
{
  "status":         "success",
  "orcid":          "0000-0002-1234-5678",
  "works_count":    87,
  "data_source":    "wizdam_sikola_db",
  "person_summary": { "...": "from supplied_person" },
  "works":          [ { "...": "from supplied_works" } ],
  "cache_info":     { "from_cache": false }
}
```

Note: `raw_data` is absent when data comes from Wizdam Sikola DB (no new data to persist).

---

## Error Responses

| Code | Message | Cause |
|------|---------|-------|
| 400 | `orcid is required` | Missing `orcid` parameter |
| 400 | `Invalid ORCID format` | ORCID does not match `\d{4}-\d{4}-\d{4}-\d{3}[\dX]` |
| 502 | `ORCID API error` | ORCID API unreachable or returned non-200 |

---

## Usage in Wizdam Sikola

### 1. Initial Researcher Sync (Registration / Profile Setup)

When a researcher registers and connects their ORCID:

```php
public function syncResearcherProfile(string $orcid): void
{
    // First call — no cache, fetch fresh
    $result = $this->sangiaClient->getOrcidProfile($orcid, refresh: true);

    if ($result['status'] !== 'success') {
        throw new ApiException($result['message']);
    }

    // Persist raw data for future supplied_data calls
    if (isset($result['raw_data'])) {
        AuthorProfileCache::updateOrCreate(['orcid' => $orcid], [
            'person_data' => json_encode($result['raw_data']['person']),
            'works_data'  => json_encode($result['raw_data']['works']),
            'fetched_at'  => $result['raw_data']['fetched_at'],
        ]);
    }

    // Populate researcher profile in Wizdam Sikola DB
    ResearcherProfile::updateOrCreate(['orcid' => $orcid], [
        'name'       => $result['person_summary']['name'],
        'email'      => $result['person_summary']['emails'][0] ?? null,
        'country'    => $result['person_summary']['country'],
        'works_count'=> $result['works_count'],
    ]);
}
```

### 2. Subsequent Calls — Use Cached Data

After initial sync, always supply data from DB to avoid redundant ORCID calls:

```php
public function getOrcidProfile(string $orcid, bool $refresh = false): array
{
    $cache = AuthorProfileCache::find($orcid);

    $body = [];
    if ($cache && !$refresh) {
        $body['supplied_works']  = json_decode($cache->works_data, true);
        $body['supplied_person'] = json_decode($cache->person_data, true);
    }

    $result = $this->post("/api/v1/orcid/profile?orcid=$orcid" . ($refresh ? '&refresh=true' : ''), $body);

    // Save any newly fetched data
    if (isset($result['raw_data'])) {
        AuthorProfileCache::updateOrCreate(['orcid' => $orcid], [
            'person_data' => json_encode($result['raw_data']['person']),
            'works_data'  => json_encode($result['raw_data']['works']),
            'fetched_at'  => $result['raw_data']['fetched_at'],
        ]);
    }

    return $result;
}
```

### 3. Recommended DB Schema

```sql
CREATE TABLE author_profiles_cache (
  orcid        VARCHAR(19) PRIMARY KEY,
  person_data  JSON,         -- raw ORCID person object
  works_data   JSON,         -- raw ORCID works array
  scopus_data  JSON NULL,    -- from Scopus (if fetched)
  fetched_at   DATETIME,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 4. Refresh Strategy

- **Automatic:** Run a nightly crawler that calls with `refresh=true` for active researchers
- **Manual:** Researcher clicks "Sync Profile" button in Wizdam Sikola UI
- **Trigger-based:** Refresh when a new publication is detected via DOI lookup
