# Citation by DOI

Retrieve citation data for a single article from four independent sources simultaneously.

**Method:** `GET`  
**Path:** `/api/v1/citation/doi`  
**Auth:** `X-API-Key` required  
**Timeout:** 120 seconds (5 sequential external API calls)

---

## Data Sources

| Source | What it provides |
|--------|-----------------|
| **OpenCitations** | Open citation graph — citing DOIs |
| **Crossref** | Reference list of the article itself |
| **OpenAlex** | Citing works with titles and years |
| **Semantic Scholar** | Citing papers with DOI, title, year |

---

## Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `doi` | string | Yes | — | DOI of the article (e.g. `10.1234/example`) |
| `limit` | int | No | `15` | Max citations per source (1–50) |
| `refresh` | bool | No | `false` | Reserved for future use |

---

## Response

```json
{
  "status": "success",
  "doi":    "10.1234/example",
  "article_metadata": {
    "title":            "Solar Panel Adoption in Rural Java",
    "authors":          ["Budi Santoso", "Ani Wijaya"],
    "publication_year": 2023,
    "journal":          "Renewable Energy",
    "volume":           "210",
    "issue":            "3",
    "pages":            "120-135",
    "abstract":         "This study examines...",
    "publisher":        "Elsevier",
    "type":             "journal-article",
    "is_referenced_by": 42
  },
  "citations": {
    "opencitations": [
      {
        "citing_doi": "10.5678/citing-paper-1",
        "title":      null,
        "year":       null,
        "source":     "opencitations"
      }
    ],
    "crossref": [
      {
        "citing_doi": "10.9999/reference-1",
        "title":      "An energy transition study",
        "year":       2022,
        "source":     "crossref"
      }
    ],
    "openalex": [
      {
        "citing_doi": "10.1111/openalex-1",
        "title":      "Rural electrification analysis",
        "year":       2023,
        "source":     "openalex"
      }
    ],
    "semantic_scholar": [
      {
        "citing_doi": "10.2222/semantic-1",
        "title":      "SDG7 progress report",
        "year":       2024,
        "source":     "semantic_scholar"
      }
    ]
  },
  "citation_count": {
    "opencitations":   12,
    "crossref":         8,
    "openalex":        15,
    "semantic_scholar": 9
  },
  "total_unique":  28,
  "api_version":   "v1.2-modular",
  "data_source":   "external_apis",
  "cache_info":    { "from_cache": false },
  "raw_data": {
    "doi":        "10.1234/example",
    "metadata":   { "...": "article_metadata object" },
    "counts":     { "opencitations": 12, "crossref": 8, "openalex": 15, "semantic_scholar": 9 },
    "fetched_at": "2025-01-01T00:00:00+00:00"
  }
}
```

### Citation Item Fields

| Field | Type | Description |
|-------|------|-------------|
| `citing_doi` | string\|null | DOI of the citing article (may be null if unavailable) |
| `title` | string\|null | Title of the citing article (not all sources provide this) |
| `year` | int\|null | Publication year of the citing article |
| `source` | string | Which API provided this citation |

---

## Error Responses

| Code | Message | Cause |
|------|---------|-------|
| 400 | `doi is required` | Missing `doi` parameter |
| 404 | `Article not found` | DOI not resolvable in any source |

---

## Usage in Wizdam Sikola

### 1. Article Detail Page

Show citation panel when a user views a specific publication:

```php
public function getArticleCitations(string $doi): array
{
    // Check if already cached in DB
    $cached = CitationsCache::find($doi);
    if ($cached && $cached->fetched_at > now()->subDays(7)) {
        return json_decode($cached->citations, true);
    }

    // Fetch fresh from API
    $result = $this->get("/api/v1/citation/doi?doi=" . urlencode($doi) . "&limit=50");

    if ($result['status'] === 'success') {
        // Persist for future requests (cache 7 days)
        CitationsCache::updateOrCreate(['doi' => $doi], [
            'metadata'   => json_encode($result['article_metadata']),
            'citations'  => json_encode($result['citations']),
            'counts'     => json_encode($result['citation_count']),
            'fetched_at' => $result['raw_data']['fetched_at'],
        ]);
    }

    return $result;
}
```

### 2. Recommended DB Schema

```sql
CREATE TABLE citations_cache (
  doi          VARCHAR(255) PRIMARY KEY,
  metadata     JSON,
  citations    JSON,
  counts       JSON,
  total_unique INT UNSIGNED DEFAULT 0,
  fetched_at   DATETIME,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 3. Display Strategy in UI

The four sources may overlap — use `total_unique` as the headline citation number. Show per-source breakdown in a collapsible detail panel:

```
Total citations (unique): 28

  OpenCitations    ████████████ 12
  OpenAlex         ███████████████ 15
  Crossref         ████████ 8
  Semantic Scholar █████████ 9
```

Highlight citations where `citing_doi` is not null — those are clickable links to the citing papers.

### 4. Crawling Strategy

For large datasets, prioritize DOIs that have `is_referenced_by > 10` (from Crossref metadata). This prevents quota burn on low-citation articles.
