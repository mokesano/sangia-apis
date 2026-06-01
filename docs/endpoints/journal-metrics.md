# Journal Metrics

Retrieve journal impact metrics from the Scopus Serial Title API (CiteScore, SJR, SNIP, quartile).

**Method:** `GET`  
**Path:** `/api/v1/journal/metrics`  
**Auth:** `X-API-Key` required  
**Timeout:** 30 seconds  
**Requires:** `SCOPUS_API_KEY` environment variable on the sangia-apis server

---

## Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `issn` | string | Yes | — | ISSN in format `XXXX-XXXX` (print or electronic) |
| `refresh` | bool | No | `false` | Force re-fetch from Scopus API |

---

## Response

```json
{
  "status":      "success",
  "api_version": "v2.1-modular",
  "journal": {
    "title":            "Renewable Energy",
    "issn_print":       "0960-1481",
    "issn_electronic":  "1879-0682",
    "publisher":        "Elsevier",
    "country":          "Journal",
    "open_access":      "0",
    "publication_type": "Journal",
    "active":           true
  },
  "metrics": {
    "citescore":     13.2,
    "sjr":           1.845,
    "sjr_year":      "2023",
    "snip":          2.11,
    "snip_year":     "2023",
    "quartile":      "Q1",
    "subject_areas": [
      {
        "name":     "Renewable Energy, Sustainability and the Environment",
        "code":     "2105",
        "quartile": "Q1"
      },
      {
        "name":     "Energy Engineering and Power Technology",
        "code":     "2102",
        "quartile": "Q1"
      }
    ]
  },
  "cache_info": { "from_cache": false },
  "raw_data": {
    "issn":       "0960-1481",
    "metrics":    { "...": "journal + metrics object" },
    "fetched_at": "2025-01-01T00:00:00+00:00"
  }
}
```

### Metrics Reference

| Field | Description |
|-------|-------------|
| `citescore` | Scopus CiteScore — citations in 4 years / publications in 4 years |
| `sjr` | SCImago Journal Rank — weighted citation prestige per article |
| `snip` | Source Normalized Impact per Paper — field-normalized citation impact |
| `quartile` | Q1–Q4 ranking within the journal's primary subject area |

---

## Error Responses

| Code | Message | Cause |
|------|---------|-------|
| 400 | `issn is required` | Missing `issn` parameter |
| 400 | `Invalid ISSN format. Expected 8 digits (e.g. 1234-5678).` | ISSN is malformed |
| 404 | `Journal with ISSN XXXX-XXXX not found in Scopus` | ISSN not in Scopus database |
| 503 | `Scopus API key not configured` | `SCOPUS_API_KEY` missing on server |

---

## Usage in Sangia Scieco

### 1. Journal Profile Page

Display journal metrics when a user views a journal or when a publication is listed:

```php
public function getJournalMetrics(string $issn): array
{
    $cache = JournalProfilesCache::find($issn);

    // Use cached data if fresh (7 days)
    if ($cache?->scopus_data && $cache->fetched_at > now()->subDays(7)) {
        return json_decode($cache->scopus_data, true);
    }

    $result = $this->get("/api/v1/journal/metrics?issn=" . urlencode($issn));

    if ($result['status'] === 'success' && isset($result['raw_data'])) {
        JournalProfilesCache::updateOrCreate(['issn' => $issn], [
            'scopus_data' => json_encode($result['raw_data']['metrics']),
            'fetched_at'  => $result['raw_data']['fetched_at'],
        ]);
    }

    return $result;
}
```

### 2. Enriching Publication Lists

When displaying a researcher's publications, show journal quality indicators:

```php
foreach ($researcher->publications as $pub) {
    if ($pub->issn) {
        $metrics = $this->getJournalMetrics($pub->issn);
        $pub->journal_quartile  = $metrics['metrics']['quartile'] ?? null;
        $pub->journal_citescore = $metrics['metrics']['citescore'] ?? null;
    }
}
```

### 3. Recommended DB Schema

```sql
CREATE TABLE journal_profiles_cache (
  issn        VARCHAR(10) PRIMARY KEY,  -- normalized XXXX-XXXX
  title       VARCHAR(255),
  scopus_data JSON,
  sinta_data  JSON NULL,
  fetched_at  DATETIME,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 4. Display in UI

Show a compact metrics badge on each publication card:

```
Renewable Energy  [Q1]  CiteScore: 13.2  SJR: 1.845
```

Color-code quartiles: Q1 = green, Q2 = blue, Q3 = yellow, Q4 = gray.
