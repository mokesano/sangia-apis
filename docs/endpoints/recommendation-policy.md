# Policy Recommendation

Generate evidence-based policy recommendations for five stakeholder types, based on aggregated research landscape data.

**Method:** `POST`  
**Path:** `/api/v1/recommendation/policy`  
**Auth:** `X-API-Key` required  
**Timeout:** 30 seconds

---

## Overview

This endpoint returns **language-agnostic structured data** — all recommendation content is represented as stable keys (`id`, `activity_keys`, `category`). Sangia Scieco is responsible for translating these keys into user-facing text via its own i18n system.

Each recommendation item uses its `id` (e.g. `GOV-01`) as the primary translation key. Activity keys (e.g. `modernize_research_labs`) map to localized text in Sangia Scieco's translation files.

---

## Stakeholder Types

| `stakeholder_type` | Recommendations Produced |
|-------------------|--------------------------|
| `government` | GOV-01, GOV-02, GOV-03, GOV-04 (if `sdg_achievement` domain) |
| `institution` | INST-01, INST-02, INST-03 |
| `industry` | IND-01, IND-02 |
| `researcher` | RES-01, RES-02 |
| `community` | COM-01 |

---

## Request Body

```json
{
  "stakeholder_type": "government",
  "domain":           "sdg_achievement",
  "time_horizon":     "medium",
  "region":           "Southeast Asia",
  "research_landscape": {
    "total_researchers":         1250,
    "total_institutions":          45,
    "total_publications":        8900,
    "avg_h_index":               12.5,
    "dominant_sdgs":             ["SDG4", "SDG7", "SDG13"],
    "weak_sdgs":                 ["SDG14", "SDG15"],
    "strong_sdgs":               ["SDG4", "SDG7"],
    "top_fields":                ["Energy", "Education", "Environment"],
    "collaboration_rate":         0.68,
    "international_collab_rate":  0.23
  }
}
```

### Field Reference

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `stakeholder_type` | string | No | `government` | Target audience (see table above) |
| `domain` | string | No | `general` | Policy focus area — `general`, `sdg_achievement`, `research_funding`, `innovation` |
| `time_horizon` | string | No | `medium` | Planning horizon — `short`, `medium`, `long` |
| `region` | string | No | `""` | Geographic context (returned as-is, no effect on logic) |
| `research_landscape` | object | No | `{}` | Aggregated stats from Sangia Scieco DB — enables data-driven recommendations |
| `research_landscape.weak_sdgs` | array | No | `["SDG14","SDG15"]` | SDGs with lowest research coverage — used in GOV-04 |
| `research_landscape.strong_sdgs` | array | No | `["SDG3","SDG4","SDG9"]` | SDGs with strongest research coverage |
| `research_landscape.total_researchers` | int | No | `0` | Total researcher count in the dataset |
| `research_landscape.total_publications` | int | No | `0` | Total publication count |
| `research_landscape.avg_h_index` | float | No | `0` | Average h-index across researchers |

---

## Response

```json
{
  "status":           "success",
  "stakeholder_type": "government",
  "domain":           "sdg_achievement",
  "region":           "Southeast Asia",
  "time_horizon_key": "medium_term",
  "context_summary": {
    "data_driven":  true,
    "researchers":  1250,
    "institutions":   45,
    "strong_sdgs":  ["SDG4", "SDG7"],
    "weak_sdgs":    ["SDG14", "SDG15"],
    "region":       "Southeast Asia"
  },
  "recommendations": [
    {
      "id":          "GOV-01",
      "priority":    "high",
      "category":    "infrastructure",
      "target_sdgs": ["SDG4", "SDG9", "SDG17"],
      "activity_keys": [
        "modernize_research_labs",
        "expand_digital_library",
        "build_hpc_center",
        "cross_institution_collab_platform",
        "develop_international_partnerships"
      ],
      "expected_impact": {
        "research_capacity_increase":        "40%",
        "international_collaboration_growth": "60%",
        "publication_quality_improvement":   "35%"
      },
      "time_horizon_key": "medium_term",
      "implementation": {
        "horizon_key": "medium_term",
        "steps": [
          { "phase": "phase_1", "activity_key": "modernize_research_labs" },
          { "phase": "phase_1", "activity_key": "expand_digital_library" },
          { "phase": "phase_2", "activity_key": "build_hpc_center" },
          { "phase": "phase_2", "activity_key": "cross_institution_collab_platform" },
          { "phase": "phase_3", "activity_key": "develop_international_partnerships" }
        ]
      },
      "success_metrics": {
        "tracking_period":  "annual",
        "review_mechanism": "periodic_committee_review",
        "targets": {
          "research_capacity_increase": "40%"
        }
      }
    },
    {
      "id": "GOV-04",
      "priority":    "high",
      "category":    "sdg_focus",
      "target_sdgs": ["SDG14", "SDG15"],
      "activity_keys": [
        "dedicated_sdg_research_funding",
        "international_sdg_collaboration",
        "sdg_thematic_study_programs"
      ],
      "expected_impact": {
        "sdg_coverage_increase": "30%",
        "targeted_publications": "200+"
      }
    }
  ],
  "priority_matrix": {
    "high_priority":   ["GOV-01", "GOV-02", "GOV-04"],
    "medium_priority": ["GOV-03"],
    "low_priority":    [],
    "total":            4
  },
  "data_driven": true,
  "api_version": "v1.0-recommendation"
}
```

### Recommendation ID Reference

| ID | Stakeholder | Category |
|----|-------------|----------|
| GOV-01 | government | infrastructure |
| GOV-02 | government | human_resources |
| GOV-03 | government | innovation |
| GOV-04 | government | sdg_focus (only for `sdg_achievement`/`general` domain) |
| INST-01 | institution | collaboration |
| INST-02 | institution | funding |
| INST-03 | institution | capacity |
| IND-01 | industry | partnership |
| IND-02 | industry | sustainability |
| RES-01 | researcher | profile |
| RES-02 | researcher | collaboration |
| COM-01 | community | access |

### Activity Key Reference

A subset of activity keys and their intended meaning (for building i18n translation files):

| Key | Context |
|-----|---------|
| `modernize_research_labs` | GOV-01 — Upgrade lab equipment |
| `expand_digital_library` | GOV-01 — National digital library |
| `build_hpc_center` | GOV-01 — HPC infrastructure |
| `expand_research_scholarships` | GOV-02 — Scholarship programs |
| `postdoctoral_fellowship_program` | GOV-02 — Post-doc support |
| `strengthen_technology_transfer_office` | GOV-03 — TTO improvement |
| `dedicated_sdg_research_funding` | GOV-04 — SDG-targeted grants |
| `identify_sdg_aligned_partners` | INST-01 — Partner identification |
| `integrate_sangia_scieco_tracking` | INST-03 — Use Sangia Scieco |
| `sync_orcid_scopus_sinta` | RES-01 — Profile synchronization |
| `sangia_scieco_impact_monitoring` | RES-01 — Use impact dashboard |
| `national_open_access_repository` | COM-01 — Open access |

---

## Error Responses

| Code | Message | Cause |
|------|---------|-------|
| 400 | `Invalid stakeholder_type. Supported: ...` | Unknown stakeholder type |

---

## Usage in Sangia Scieco

### 1. Building the i18n Translation Map

Since the API returns keys, Sangia Scieco must maintain a translation file. Example structure:

```php
// resources/lang/id/recommendations.php
return [
    'GOV-01' => [
        'title'       => 'Peningkatan Infrastruktur Riset Nasional',
        'description' => 'Program komprehensif modernisasi fasilitas riset...',
    ],
    'GOV-02' => [
        'title'       => 'Program Talenta Riset Nasional',
        'description' => 'Inisiatif strategis untuk mengembangkan peneliti...',
    ],
    // ... etc
    'activities' => [
        'modernize_research_labs'   => 'Modernisasi laboratorium dan peralatan riset',
        'expand_digital_library'    => 'Perluasan perpustakaan digital nasional',
        'build_hpc_center'          => 'Pembangunan pusat komputasi berperforma tinggi',
        'expand_research_scholarships' => 'Perluasan beasiswa riset nasional',
        // ... all activity keys
    ],
    'phases' => [
        'phase_1' => 'Fase 1 — Persiapan',
        'phase_2' => 'Fase 2 — Implementasi',
        'phase_3' => 'Fase 3 — Evaluasi',
    ],
    'horizons' => [
        'short_term'  => '1–2 tahun',
        'medium_term' => '3–5 tahun',
        'long_term'   => '5–10 tahun',
    ],
    'tracking' => [
        'annual'                    => 'Tahunan',
        'periodic_committee_review' => 'Evaluasi berkala oleh komite teknis',
    ],
];
```

### 2. Rendering Recommendations in UI

```php
class RecommendationRenderer
{
    public function render(array $recommendation, string $locale = 'id'): array
    {
        $t = trans('recommendations', [], $locale);

        return [
            'id'       => $recommendation['id'],
            'title'    => $t[$recommendation['id']]['title'] ?? $recommendation['id'],
            'description' => $t[$recommendation['id']]['description'] ?? '',
            'priority' => $recommendation['priority'],
            'category' => $recommendation['category'],
            'target_sdgs'      => $recommendation['target_sdgs'] ?? [],
            'expected_impact'  => $recommendation['expected_impact'] ?? [],
            'time_horizon'     => $t['horizons'][$recommendation['time_horizon_key']] ?? $recommendation['time_horizon_key'],
            'activities'       => array_map(
                fn($key) => $t['activities'][$key] ?? $key,
                $recommendation['activity_keys'] ?? []
            ),
            'implementation'   => $this->renderImplementation($recommendation['implementation'] ?? [], $t),
        ];
    }
}
```

### 3. Build Research Landscape from DB

Aggregate the `research_landscape` from Sangia Scieco's analytics tables before calling the API:

```php
public function buildResearchLandscape(): array
{
    return [
        'total_researchers'         => ResearcherProfile::count(),
        'total_institutions'        => Institution::count(),
        'total_publications'        => Work::count(),
        'avg_h_index'               => ResearcherMetrics::avg('h_index'),
        'dominant_sdgs'             => $this->getSdgRanking('dominant', 5),
        'weak_sdgs'                 => $this->getSdgRanking('weak', 3),
        'strong_sdgs'               => $this->getSdgRanking('strong', 3),
        'collaboration_rate'        => $this->getCollaborationRate(),
        'international_collab_rate' => $this->getInternationalCollabRate(),
        'top_fields'                => $this->getTopResearchFields(5),
    ];
}
```

### 4. Stakeholder Context

Each stakeholder type maps to a different Sangia Scieco UI section:

| Stakeholder | Where it appears |
|-------------|-----------------|
| `government` | Ministry / policy-maker dashboard |
| `institution` | University admin / rector dashboard |
| `industry` | Industry partner portal |
| `researcher` | Individual researcher profile → recommendations tab |
| `community` | Public-facing research impact page |
