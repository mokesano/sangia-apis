# SDG Classify

Classify one or more research works against the 17 Sustainable Development Goals.

**Method:** `POST`  
**Path:** `/api/v1/sdg/{version}/classify`  
**Auth:** `X-API-Key` required  
**Alias:** `POST /api/v1/sdg/classify` → redirects to `v5`  
**Timeout:** 60 seconds per batch

---

## Versions

| Version | Description |
|---------|-------------|
| `v0` | Keyword-only scoring |
| `v1` | Keyword + vector similarity |
| `v2` | Bilingual dictionary (EN + ID) |
| `v3` | Contributor type classification |
| `v4` | Substantive + causal analysis |
| `v5` | Causal-boosted — **recommended for production** |
| `v5e` | Metadata-enhanced — experimental |

Use `GET /api/v1/sdg/versions` to retrieve current weights and thresholds for each version.

---

## Input Modes

The endpoint accepts three mutually exclusive input modes:

| Mode | Required Fields | Use Case |
|------|----------------|----------|
| Text | `title` and/or `abstract` | Single article, synchronous |
| DOI | `doi` | Fetch metadata from Crossref then classify |
| ORCID | `orcid` | Classify all works of a researcher, batch |

---

## Request Body

```json
{
  "title":       "string — article title",
  "abstract":    "string — article abstract",
  "doi":         "string — e.g. 10.1234/example",
  "orcid":       "string — e.g. 0000-0002-1234-5678",
  "refresh":     false,
  "offset":      0,
  "batch_size":  20,
  "weights": {
    "keyword":     0.30,
    "similarity":  0.30,
    "substantive": 0.20,
    "causal":      0.20,
    "max_sdgs":    7,
    "thresholds": {
      "min":        0.20,
      "confidence": 0.30,
      "high":       0.60
    }
  },
  "supplied_works": []
}
```

### Field Reference

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `title` | string | Mode: text | — | Article title |
| `abstract` | string | Mode: text | — | Article abstract |
| `doi` | string | Mode: doi | — | DOI of the article |
| `orcid` | string | Mode: orcid | — | Researcher ORCID iD |
| `refresh` | bool | No | `false` | Force re-fetch from ORCID/Crossref |
| `offset` | int | No | `0` | Batch start position (ORCID mode) |
| `batch_size` | int | No | `20` | Works per batch, max `50` (ORCID mode) |
| `weights` | object | No | Version defaults | Override scoring weights |
| `weights.keyword` | float | No | v5: 0.30 | Weight for keyword score component |
| `weights.similarity` | float | No | v5: 0.30 | Weight for vector similarity component |
| `weights.substantive` | float | No | v5: 0.20 | Weight for substantive analysis |
| `weights.causal` | float | No | v5: 0.20 | Weight for causal relationship detection |
| `weights.max_sdgs` | int | No | 7 | Maximum SDGs to return per work |
| `weights.thresholds.min` | float | No | 0.20 | Minimum score to include an SDG |
| `weights.thresholds.confidence` | float | No | 0.30 | Score for "confident" label |
| `weights.thresholds.high` | float | No | 0.60 | Score for "high confidence" label |
| `supplied_works` | array | No | `[]` | Pre-fetched works from Sangia Sikola DB — skips ORCID cURL |

---

## Responses

### Text / DOI mode — synchronous

```json
{
  "status": "success",
  "version": "v5",
  "weights_applied": {
    "keyword": 0.30, "similarity": 0.30, "substantive": 0.20, "causal": 0.20
  },
  "sdg_analysis": {
    "sdgs": ["SDG7", "SDG13"],
    "sdg_confidence": {
      "SDG7":  0.724,
      "SDG13": 0.611
    },
    "contributor_types": {
      "SDG7": "Active Contributor"
    },
    "contribution_pathways": {
      "SDG7": "clean_energy_access"
    },
    "detailed_analysis": {
      "SDG7": {
        "score": 0.724,
        "confidence_level": "High",
        "contributor_type": {
          "type": "Active Contributor",
          "level": "High"
        },
        "components": {
          "keyword_score":      0.65,
          "similarity_score":   0.71,
          "substantive_score":  0.80,
          "causal_score":       0.75
        },
        "evidence": {
          "matched_keywords":   ["solar energy", "rural electrification"],
          "causal_relationship": ["contributes to clean energy access"]
        }
      }
    }
  }
}
```

### ORCID mode — processing (not yet complete)

```json
{
  "status": "processing",
  "orcid": "0000-0002-1234-5678",
  "progress": {
    "processed":   20,
    "total_works": 87,
    "percent":     23
  },
  "next_offset": 20
}
```

### ORCID mode — success (all batches complete)

```json
{
  "status": "success",
  "orcid": "0000-0002-1234-5678",
  "total_works": 87,
  "data_source": "orcid_api",
  "data": {
    "sdg_summary": {
      "SDG7":  { "average_confidence": 0.724, "work_count": 42 },
      "SDG13": { "average_confidence": 0.611, "work_count": 28 }
    },
    "works": [
      {
        "title": "Solar Panel Adoption in Rural Java",
        "doi":   "10.1234/example",
        "sdgs":  [
          { "sdg": "SDG7", "score": 0.724, "confidence": "High" }
        ]
      }
    ]
  },
  "raw_data": {
    "works":      [],
    "fetched_at": "2025-01-01T00:00:00+00:00"
  }
}
```

---

## Error Responses

| Code | Message | Cause |
|------|---------|-------|
| 400 | `Provide orcid, doi, title+abstract, or orcid+supplied_works` | No valid input mode |
| 400 | `doi is required` | DOI mode but empty DOI |
| 404 | `DOI not found in Crossref` | DOI does not exist in Crossref |
| 410 | `Batch session expired` | Gap between batch calls exceeded TTL — restart from `offset: 0` |
| 502 | `ORCID fetch failed` | ORCID API unreachable |

---

## Usage in Sangia Sikola

### 1. Single Article Classification (text mode)

Use when a researcher submits a new article or when Sangia Sikola needs to classify a known title+abstract from its DB.

```php
// SangiaApiClient.php
public function classifySingleWork(string $title, string $abstract, string $version = 'v5'): array
{
    $weights = WeightConfigService::getForSdg($version); // load from admin config DB
    return $this->post("/api/v1/sdg/$version/classify", [
        'title'    => $title,
        'abstract' => $abstract,
        'weights'  => $weights,
    ]);
}
```

### 2. Full Researcher Profile Classification (ORCID batch mode)

Use when a researcher registers or manually triggers re-analysis. Show a progress bar to the user.

```php
public function classifyResearcherProfile(string $orcid, string $version = 'v5'): array
{
    $weights = WeightConfigService::getForSdg($version);

    // Check if Sangia Sikola already has works cached
    $cache   = AuthorProfileCache::find($orcid);
    $payload = [
        'orcid'   => $orcid,
        'weights' => $weights,
    ];

    if ($cache && !empty($cache->works_data)) {
        $payload['supplied_works'] = json_decode($cache->works_data, true);
    }

    return $this->batchPost("/api/v1/sdg/$version/classify", $payload, function ($progress) use ($orcid) {
        // Emit SSE or WebSocket event for progress bar
        event(new SdgAnalysisProgress($orcid, $progress['percent']));
    });
}
```

### 3. Persist Results

After receiving `status: success`, save to `analysis_history`:

```php
if ($result['status'] === 'success') {
    AnalysisHistory::create([
        'orcid'         => $orcid,
        'analysis_type' => 'sdg',
        'version'       => $version,
        'result'        => json_encode($result['data']),
        'calculated_at' => now(),
    ]);

    // Also persist raw ORCID data if freshly fetched
    if (isset($result['raw_data'])) {
        AuthorProfileCache::updateOrCreate(
            ['orcid' => $orcid],
            ['works_data' => json_encode($result['raw_data']['works']), 'fetched_at' => $result['raw_data']['fetched_at']]
        );
    }
}
```

### 4. Recommended Weight Configuration (Admin Panel)

Store weights in DB and send per-request:

```sql
INSERT INTO analysis_weight_configs (config_key, weights) VALUES
('sdg_v5', '{"keyword":0.30,"similarity":0.30,"substantive":0.20,"causal":0.20,"max_sdgs":7,"thresholds":{"min":0.20,"confidence":0.30,"high":0.60}}');
```

---

## Batch Loop Pattern

```javascript
async function classifyResearcher(orcid, apiKey, onProgress) {
  const endpoint = 'https://api.sangia.org/api/v1/sdg/v5/classify';
  let offset = 0;

  while (true) {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'X-API-Key': apiKey, 'Content-Type': 'application/json' },
      body: JSON.stringify({ orcid, offset, batch_size: 20 }),
    });

    const data = await res.json();

    if (data.status === 'success')    return data;
    if (data.status === 'error')      throw new Error(data.message);
    if (data.status === 'processing') {
      onProgress(data.progress.percent);
      offset = data.next_offset;
      await new Promise(r => setTimeout(r, 300));
    }
  }
}
```
