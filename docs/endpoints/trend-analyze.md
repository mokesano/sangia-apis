# Trend Analyze

Analyze research trends over time for a researcher using four distinct analysis types.

**Method:** `POST`  
**Path:** `/api/v1/trend/analyze`  
**Auth:** `X-API-Key` required  
**Timeout:** 120 seconds

---

## Analysis Types

| `analysis_type` | Description | Required Extra Input |
|-----------------|-------------|---------------------|
| `impact_trajectory` | Publication and citation growth per year | `supplied_works` with `publication_year` |
| `sdg_evolution` | SDG contribution shift per year | `supplied_works` with `title` and `publication_year` |
| `collaboration_network` | Co-authorship patterns and frequency | `supplied_works` with `authors_string` or `contributors` |
| `citation_growth` | Citation accumulation by publication year | `scopus_id` required |

---

## Time Range Values

| Value | Period |
|-------|--------|
| `1y` | Last 1 year |
| `3y` | Last 3 years |
| `5y` | Last 5 years (default) |
| `10y` | Last 10 years |
| `all` | All available years (from 1900) |

---

## Request Body

```json
{
  "orcid":         "0000-0002-1234-5678",
  "analysis_type": "impact_trajectory",
  "time_range":    "5y",
  "scopus_id":     "57200000000",
  "refresh":       false,
  "supplied_works": [
    {
      "title":            "Solar Panel Adoption in Rural Java",
      "doi":              "10.1234/example",
      "publication_year": 2023,
      "type":             "journal-article",
      "authors_string":   "Budi Santoso, Ani Wijaya"
    }
  ],
  "supplied_scopus": {
    "publications": [
      { "doi": "10.1234/example", "year": 2023, "cited_by_count": 42 }
    ],
    "author": { "h_index": 18 }
  }
}
```

### Field Reference

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `orcid` | string | Yes | — | Researcher ORCID iD |
| `analysis_type` | string | No | `impact_trajectory` | Type of analysis (see table above) |
| `time_range` | string | No | `5y` | Time window (see table above) |
| `scopus_id` | string | No | `null` | Required for `citation_growth` type |
| `refresh` | bool | No | `false` | Force re-fetch from ORCID/Scopus |
| `supplied_works` | array | No | `[]` | Works from Wizdam Sikola DB — skips ORCID cURL |
| `supplied_scopus` | object\|null | No | `null` | Scopus data from Wizdam Sikola DB |

---

## Responses

### `impact_trajectory`

```json
{
  "status":        "success",
  "orcid":         "0000-0002-1234-5678",
  "analysis_type": "impact_trajectory",
  "time_range":    "5y",
  "data_source":   "wizdam_sikola_db",
  "yearly_metrics": [
    { "year": 2019, "publications": 8,  "citations": 45,  "cumulative_pubs": 8  },
    { "year": 2020, "publications": 12, "citations": 112, "cumulative_pubs": 20 },
    { "year": 2021, "publications": 15, "citations": 180, "cumulative_pubs": 35 },
    { "year": 2022, "publications": 18, "citations": 240, "cumulative_pubs": 53 },
    { "year": 2023, "publications": 22, "citations": 310, "cumulative_pubs": 75 }
  ],
  "trends": {
    "publication_trend": {
      "direction":       "increasing",
      "growth_rate_pct": 175.0,
      "slope":           3.4,
      "first_value":     8,
      "last_value":      22
    },
    "citation_trend": {
      "direction":       "increasing",
      "growth_rate_pct": 588.9,
      "slope":           66.5,
      "first_value":     45,
      "last_value":      310
    }
  },
  "summary": {
    "total_publications":   75,
    "years_active":          5,
    "most_productive_year":  2023,
    "avg_pubs_per_year":    15.0
  },
  "api_version": "v1.0-trend"
}
```

### `sdg_evolution`

```json
{
  "status":        "success",
  "analysis_type": "sdg_evolution",
  "sdg_by_year": {
    "2021": [
      { "sdg": 7,  "count": 5,  "avg_score": 0.680 },
      { "sdg": 13, "count": 3,  "avg_score": 0.540 }
    ],
    "2023": [
      { "sdg": 7,  "count": 10, "avg_score": 0.724 },
      { "sdg": 13, "count": 6,  "avg_score": 0.611 },
      { "sdg": 9,  "count": 4,  "avg_score": 0.502 }
    ]
  },
  "dominant_sdgs": [7, 13, 9, 4, 3],
  "emerging_sdgs": [9, 11],
  "summary": {
    "total_works_analyzed": 75,
    "years_covered":         5,
    "unique_sdgs_touched":  8
  }
}
```

### `collaboration_network`

```json
{
  "status":        "success",
  "analysis_type": "collaboration_network",
  "network_stats": {
    "total_collaborators":  42,
    "repeat_collab_rate":   52.4,
    "avg_authors_per_work": 3.2
  },
  "yearly_new_collaborators": {
    "2019": 12, "2020": 8, "2021": 10, "2022": 6, "2023": 6
  },
  "top_collaborators": [
    { "name": "Ani Wijaya", "collaboration_count": 18, "first_year": 2019, "last_year": 2023 },
    { "name": "Rudi Hartono", "collaboration_count": 12, "first_year": 2020, "last_year": 2023 }
  ],
  "summary": {
    "total_works":   75,
    "years_covered":  5
  }
}
```

### `citation_growth`

```json
{
  "status":        "success",
  "analysis_type": "citation_growth",
  "yearly_metrics": [
    { "year": 2019, "new_citations": 45,  "cumulative": 45,  "publications_count": 8 },
    { "year": 2020, "new_citations": 112, "cumulative": 157, "publications_count": 12 }
  ],
  "trend": {
    "direction":       "increasing",
    "growth_rate_pct": 588.9,
    "slope":           66.5,
    "first_value":     45,
    "last_value":      310
  },
  "summary": {
    "total_citations":    887,
    "peak_citation_year": 2023,
    "h_index":            18
  }
}
```

---

## Error Responses

| Code | Message | Cause |
|------|---------|-------|
| 400 | `orcid is required` | Missing `orcid` field |
| 400 | `Invalid ORCID: ...` | Malformed ORCID format |
| 400 | `scopus_id is required for citation_growth analysis` | `citation_growth` type without `scopus_id` |
| 400 | `Invalid analysis_type: ...` | Unknown analysis type |
| 502 | `ORCID fetch failed` | ORCID API unreachable and no `supplied_works` |

---

## Usage in Wizdam Sikola

### 1. Researcher Trend Dashboard

Use to power the trend charts on a researcher's profile page:

```php
public function getResearcherTrends(string $orcid, string $scopusId = null): array
{
    $cache = AuthorProfileCache::find($orcid);

    $supplied = [];
    if ($cache?->works_data) {
        $supplied['supplied_works'] = json_decode($cache->works_data, true);
    }
    if ($scopusId && $cache?->scopus_data) {
        $supplied['supplied_scopus'] = json_decode($cache->scopus_data, true);
    }

    $results = [];

    // Impact trajectory (always available)
    $results['impact'] = $this->post('/api/v1/trend/analyze', array_merge([
        'orcid'         => $orcid,
        'analysis_type' => 'impact_trajectory',
        'time_range'    => '5y',
    ], $supplied));

    // SDG evolution (needs titles for classification)
    $results['sdg'] = $this->post('/api/v1/trend/analyze', array_merge([
        'orcid'         => $orcid,
        'analysis_type' => 'sdg_evolution',
        'time_range'    => '5y',
    ], $supplied));

    // Collaboration network
    $results['collab'] = $this->post('/api/v1/trend/analyze', array_merge([
        'orcid'         => $orcid,
        'analysis_type' => 'collaboration_network',
        'time_range'    => '5y',
    ], $supplied));

    // Citation growth (only if Scopus ID available)
    if ($scopusId) {
        $results['citation'] = $this->post('/api/v1/trend/analyze', array_merge([
            'orcid'         => $orcid,
            'analysis_type' => 'citation_growth',
            'time_range'    => '5y',
            'scopus_id'     => $scopusId,
        ], $supplied));
    }

    return $results;
}
```

### 2. Chart Rendering (JavaScript)

```javascript
// impact_trajectory → line chart
function renderImpactChart(data) {
  const labels = data.yearly_metrics.map(m => m.year);
  const pubs   = data.yearly_metrics.map(m => m.publications);
  const cits   = data.yearly_metrics.map(m => m.citations);

  new Chart(document.getElementById('impact-chart'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Publications', data: pubs, borderColor: '#3B82F6' },
        { label: 'Citations',    data: cits, borderColor: '#10B981', yAxisID: 'y1' },
      ],
    },
  });
}

// sdg_evolution → stacked bar chart per year
function renderSdgEvolutionChart(data) {
  const years  = Object.keys(data.sdg_by_year);
  const sdgNos = data.dominant_sdgs.slice(0, 5);
  // ... build datasets per SDG
}
```

### 3. Persist Results

```php
AnalysisHistory::create([
    'orcid'         => $orcid,
    'analysis_type' => 'trend_' . $analysisType,
    'result'        => json_encode($result),
    'calculated_at' => now(),
]);
```

### 4. `sdg_evolution` Data Requirements

For meaningful SDG evolution results, each work in `supplied_works` must include `title` (and ideally `abstract`). The engine runs the full SDG classifier per work per year — this is CPU-intensive for large datasets, so cache results in `analysis_history`.
