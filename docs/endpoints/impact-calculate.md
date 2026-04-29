# Impact Calculate

Compute the Wizdam Impact Score — a composite score across four research impact pillars for a researcher.

**Method:** `POST`  
**Path:** `/api/v1/impact/calculate`  
**Auth:** `X-API-Key` required  
**Timeout:** 60 seconds per batch

---

## The Four Pillars

| Pillar | Weight (default) | Data Source |
|--------|-----------------|-------------|
| **Academic** | 40% | ORCID works + Scopus author metrics |
| **Social** | 25% | Wizdam Sikola inputs (media, policy, shares, news) |
| **Economic** | 20% | Wizdam Sikola inputs (patents, industry, tech transfer) |
| **SDG** | 15% | Computed by SDG classifier on all works |

---

## Composite Formula

```
Composite = Academic×w_academic + Social×w_social + Economic×w_economic + SDG×w_sdg

Academic  = (min(100, h_index×3.5) × 0.45)
          + (log10(citations+1)×25  × 0.35)
          + (min(100, pub_count×1.2) × 0.20)

Social    = avg(media_mentions, policy_citations, social_shares, news_coverage)  [0–100 each]

Economic  = avg(industry_adoption, patents, tech_transfer, startup_spinoffs)     [0–100 each]

SDG       = (coverage_ratio × 0.40 + avg_confidence × 0.60) × 100
            coverage_ratio = min(1.0, distinct_sdg_count / 5)
```

---

## Request Body

```json
{
  "orcid":     "0000-0002-1234-5678",
  "scopus_id": "57200000000",
  "social": {
    "media_mentions":   75,
    "policy_citations": 60,
    "social_shares":    80,
    "news_coverage":    50
  },
  "economic": {
    "industry_adoption": 40,
    "patents":           20,
    "tech_transfer":     35,
    "startup_spinoffs":  10
  },
  "weights": {
    "academic":  0.40,
    "social":    0.25,
    "economic":  0.20,
    "sdg":       0.15
  },
  "refresh":    false,
  "offset":     0,
  "batch_size": 20,
  "supplied_works":  [],
  "supplied_person": null,
  "supplied_scopus": null
}
```

### Field Reference

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `orcid` | string | Yes | — | Researcher ORCID iD |
| `scopus_id` | string | No | `null` | Scopus Author ID — enables Scopus data in Academic pillar |
| `social` | object | No | all zeros | Social impact metrics (0–100 each) |
| `social.media_mentions` | int | No | `0` | Number of media/news mentions (normalized 0–100) |
| `social.policy_citations` | int | No | `0` | Policy document citations (normalized 0–100) |
| `social.social_shares` | int | No | `0` | Social media engagement (normalized 0–100) |
| `social.news_coverage` | int | No | `0` | News coverage score (normalized 0–100) |
| `economic` | object | No | all zeros | Economic impact metrics (0–100 each) |
| `economic.industry_adoption` | int | No | `0` | Technology adoption by industry (normalized 0–100) |
| `economic.patents` | int | No | `0` | Patent applications score (normalized 0–100) |
| `economic.tech_transfer` | int | No | `0` | Technology transfer activity (normalized 0–100) |
| `economic.startup_spinoffs` | int | No | `0` | Startup/spinoff activity (normalized 0–100) |
| `weights` | object | No | defaults | Override pillar weights (must sum to 1.0) |
| `refresh` | bool | No | `false` | Force re-fetch from ORCID/Scopus |
| `offset` | int | No | `0` | Batch start position |
| `batch_size` | int | No | `20` | Works per batch (max `50`) |
| `supplied_works` | array | No | `[]` | Pre-fetched works from Wizdam Sikola DB |
| `supplied_person` | object\|null | No | `null` | Pre-fetched ORCID person from Wizdam Sikola DB |
| `supplied_scopus` | object\|null | No | `null` | Pre-fetched Scopus data from Wizdam Sikola DB |

---

## Responses

### Processing (batch not complete)

```json
{
  "status": "processing",
  "orcid":  "0000-0002-1234-5678",
  "progress": {
    "processed":   20,
    "total_works": 87,
    "percent":     23
  },
  "next_offset": 20
}
```

### Success (all batches complete)

```json
{
  "status":    "success",
  "orcid":     "0000-0002-1234-5678",
  "name":      "Budi Santoso",
  "composite": 68.45,
  "pillars": {
    "academic":  82.10,
    "social":    66.25,
    "economic":  26.25,
    "sdg":       54.80
  },
  "weights": {
    "academic": 0.40, "social": 0.25, "economic": 0.20, "sdg": 0.15
  },
  "sdg_tags": [
    { "sdg": 7,  "code": "SDG7",  "score": 0.724, "count": 42, "label": "Affordable and Clean Energy" },
    { "sdg": 13, "code": "SDG13", "score": 0.611, "count": 28, "label": "Climate Action" }
  ],
  "sdg_by_work": [
    {
      "title":      "Solar Panel Adoption in Rural Java",
      "doi":        "10.1234/example",
      "year":       2023,
      "sdgs": [
        { "sdg": "SDG7", "score": 0.724, "confidence": "High" }
      ]
    }
  ],
  "academic_metrics": {
    "publication_count": 87,
    "h_index":           18,
    "citation_count":    3200,
    "data_sources":      ["orcid", "scopus"]
  },
  "social_inputs":   { "media_mentions": 75, "policy_citations": 60, "social_shares": 80, "news_coverage": 50 },
  "economic_inputs": { "industry_adoption": 40, "patents": 20, "tech_transfer": 35, "startup_spinoffs": 10 },
  "data_sources": {
    "orcid":  "orcid_api",
    "scopus": "scopus_api"
  },
  "raw_data": {
    "orcid_person": { "...": "ORCID person data" },
    "orcid_works":  [ { "...": "..." } ],
    "scopus":       { "...": "Scopus author + publications" },
    "fetched_at":   "2025-01-01T00:00:00+00:00"
  },
  "api_version":   "v1.1-batch",
  "calculated_at": "2025-01-01T00:00:00+00:00",
  "cache_info":    { "from_cache": false }
}
```

---

## Error Responses

| Code | Message | Cause |
|------|---------|-------|
| 400 | `orcid is required` | Missing `orcid` field |
| 410 | `Batch session expired` | Gap between batch calls exceeded TTL — restart from `offset: 0` |
| 502 | `ORCID fetch failed` | ORCID API unreachable |

---

## Usage in Wizdam Sikola

### 1. Researcher Impact Dashboard

The primary use case — calculate and display a researcher's full impact score:

```php
public function calculateImpact(string $orcid, string $scopusId = null): array
{
    $weights  = WeightConfigService::getForImpact();
    $cache    = AuthorProfileCache::find($orcid);
    $inputs   = ResearcherImpactInputs::getByOrcid($orcid);

    $payload = [
        'orcid'     => $orcid,
        'scopus_id' => $scopusId,
        'social'    => $inputs->social,
        'economic'  => $inputs->economic,
        'weights'   => $weights,
    ];

    // Supply cached data to avoid redundant external calls
    if ($cache) {
        $payload['supplied_works']  = json_decode($cache->works_data, true);
        $payload['supplied_person'] = json_decode($cache->person_data, true);
        $payload['supplied_scopus'] = $cache->scopus_data
            ? json_decode($cache->scopus_data, true) : null;
    }

    $result = $this->batchPost('/api/v1/impact/calculate', $payload, function ($progress) use ($orcid) {
        event(new ImpactCalculationProgress($orcid, $progress['percent']));
    });

    if ($result['status'] === 'success') {
        // Persist results
        AnalysisHistory::create([
            'orcid'         => $orcid,
            'analysis_type' => 'impact',
            'result'        => json_encode($result),
            'calculated_at' => $result['calculated_at'],
        ]);

        // Cache any freshly fetched raw data
        if (isset($result['raw_data'])) {
            AuthorProfileCache::updateOrCreate(['orcid' => $orcid], [
                'person_data' => json_encode($result['raw_data']['orcid_person']),
                'works_data'  => json_encode($result['raw_data']['orcid_works']),
                'scopus_data' => $result['raw_data']['scopus']
                    ? json_encode($result['raw_data']['scopus']) : null,
                'fetched_at'  => $result['raw_data']['fetched_at'],
            ]);
        }
    }

    return $result;
}
```

### 2. Social and Economic Data Collection

These inputs are collected from multiple sources within Wizdam Sikola:

```sql
CREATE TABLE researcher_impact_inputs (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  orcid      VARCHAR(19) NOT NULL,
  input_type ENUM('social', 'economic') NOT NULL,
  field_key  VARCHAR(50) NOT NULL,
  value      DECIMAL(5,2) NOT NULL,   -- 0.00 to 100.00
  source     ENUM('user_input', 'crawler', 'admin', 'api') DEFAULT 'user_input',
  verified   BOOLEAN DEFAULT FALSE,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_orcid_type_field (orcid, input_type, field_key)
);
```

Collection strategy per field:

| Field | Primary Source | Secondary Source |
|-------|---------------|-----------------|
| `media_mentions` | Altmetric API, Google News | Manual admin input |
| `policy_citations` | OpenAlex policy data | Manual admin input |
| `social_shares` | Altmetric, Twitter/X API | Manual input |
| `news_coverage` | News API | Manual input |
| `industry_adoption` | Manual researcher form | Admin verification |
| `patents` | SIPO / Google Patents crawler | Manual input |
| `tech_transfer` | Manual researcher form | — |
| `startup_spinoffs` | DIKTI data | Manual input |

### 3. Weight Override from Admin Panel

Load weights from DB on every request:

```php
class WeightConfigService
{
    public static function getForImpact(): array
    {
        $config = AnalysisWeightConfig::where('config_key', 'impact_composite')->first();
        return $config
            ? json_decode($config->weights, true)
            : ['academic' => 0.40, 'social' => 0.25, 'economic' => 0.20, 'sdg' => 0.15];
    }
}
```

### 4. Leaderboard Query

After calculating impact for multiple researchers, query `analysis_history` for the leaderboard:

```sql
SELECT r.name, r.institution, a.result->>'$.composite' AS score
FROM analysis_history a
JOIN researcher_profiles r ON r.orcid = a.orcid
WHERE a.analysis_type = 'impact'
  AND a.calculated_at = (
    SELECT MAX(calculated_at) FROM analysis_history
    WHERE orcid = a.orcid AND analysis_type = 'impact'
  )
ORDER BY score DESC
LIMIT 20;
```

### 5. Batch Loop (JavaScript)

```javascript
async function calculateImpact(orcid, apiKey, onProgress) {
  const endpoint = 'https://api.sangia.org/api/v1/impact/calculate';
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
