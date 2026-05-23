# SINTA Score

Retrieve a journal's accreditation grade and impact score from SINTA (Science and Technology Index), the Indonesian Ministry's journal quality database.

**Method:** `GET`  
**Path:** `/api/v1/sinta/score`  
**Auth:** `X-API-Key` required  
**Timeout:** 45 seconds

---

## Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `issn` | string | Yes | — | ISSN in format `XXXX-XXXX` |
| `refresh` | bool | No | `false` | Force re-scrape from SINTA website |

---

## Response

```json
{
  "status":      "success",
  "api_version": "v2.1-modular",
  "issn":        "2549-1385",
  "title":       "Jurnal Energi Terbarukan Indonesia",
  "impact":      "2.45",
  "grade":       "S2",
  "sinta_id":    "123456",
  "sinta_url":   "https://sinta.kemdiktisaintek.go.id/journals/profile/123456",
  "meta": {
    "last_update": "2025-01-01 00:00:00"
  },
  "cache_info": { "from_cache": false },
  "raw_data": {
    "issn":       "2549-1385",
    "sinta":      { "...": "scraped journal data" },
    "fetched_at": "2025-01-01T00:00:00+00:00"
  }
}
```

### Grade Reference

| Grade | Description |
|-------|-------------|
| `S1` | Highest accreditation — equivalent to Q1/Q2 international |
| `S2` | High quality — equivalent to Q2/Q3 international |
| `S3` | Accredited — recognized national journal |
| `S4` | Accredited — recognized national journal |
| `S5` | Basic accreditation |
| `S6` | Lowest accreditation level |

---

## Error Responses

| Code | Message | Cause |
|------|---------|-------|
| 400 | `issn is required` | Missing `issn` parameter |
| 400 | `Invalid ISSN format. Expected XXXX-XXXX.` | Malformed ISSN |
| 404 | `Journal with ISSN XXXX-XXXX not found in SINTA` | Journal not in SINTA database |

---

## Notes on Data Source

This endpoint scrapes the SINTA website (HTML parsing). Results depend on the SINTA website structure remaining stable. If SINTA updates their HTML, the scraper may return empty results until updated.

For Indonesian institutions, SINTA grade is a mandatory compliance metric for researchers (required for promotion points). Combine with Scopus metrics from `/api/v1/journal/metrics` for a complete picture.

---

## Usage in Sangia Sikola

### 1. Journal Profile — Combined Metrics

Use alongside `journal-metrics` for a comprehensive journal quality view:

```php
public function getFullJournalProfile(string $issn): array
{
    // Fetch both Scopus and SINTA in parallel if possible
    $cache = JournalProfilesCache::find($issn);

    $scopus = null;
    $sinta  = null;

    // Use DB cache if available
    if ($cache?->scopus_data) {
        $scopus = json_decode($cache->scopus_data, true);
    } else {
        $r = $this->get("/api/v1/journal/metrics?issn=$issn");
        if ($r['status'] === 'success') {
            $scopus = $r;
            if (isset($r['raw_data'])) {
                JournalProfilesCache::updateOrCreate(['issn' => $issn], [
                    'scopus_data' => json_encode($r['raw_data']['metrics']),
                ]);
            }
        }
    }

    if ($cache?->sinta_data) {
        $sinta = json_decode($cache->sinta_data, true);
    } else {
        $r = $this->get("/api/v1/sinta/score?issn=$issn");
        if ($r['status'] === 'success') {
            $sinta = $r;
            if (isset($r['raw_data'])) {
                JournalProfilesCache::updateOrCreate(['issn' => $issn], [
                    'sinta_data' => json_encode($r['raw_data']['sinta']),
                    'fetched_at' => $r['raw_data']['fetched_at'],
                ]);
            }
        }
    }

    return [
        'scopus' => $scopus,
        'sinta'  => $sinta,
    ];
}
```

### 2. Publication Compliance View

Show SINTA grade prominently for Indonesian researchers (required for university promotion):

```
Journal: Jurnal Energi Terbarukan Indonesia
ISSN: 2549-1385

  SINTA Grade:  S2  ✓ Accredited
  Impact Score: 2.45
  SINTA URL:    sinta.kemdiktisaintek.go.id/...
```

### 3. Recommended DB Schema

```sql
-- Add to journal_profiles_cache
ALTER TABLE journal_profiles_cache
  ADD COLUMN sinta_grade  VARCHAR(5) NULL,
  ADD COLUMN sinta_id     VARCHAR(20) NULL,
  ADD COLUMN sinta_data   JSON NULL;
```

### 4. Validation Use Case

Before submitting a publication to an administrative form, validate that the target journal is SINTA-accredited:

```php
public function validateJournalAccreditation(string $issn): bool
{
    $result = $this->getFullJournalProfile($issn);
    $grade  = $result['sinta']['grade'] ?? null;
    return $grade !== null && in_array($grade, ['S1','S2','S3','S4','S5','S6']);
}
```
