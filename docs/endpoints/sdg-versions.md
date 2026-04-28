# SDG Versions

List all available SDG classifier versions with their default weights and score thresholds.

**Method:** `GET`  
**Path:** `/api/v1/sdg/versions`  
**Auth:** Not required (public endpoint)

---

## Response

```json
{
  "status": "success",
  "data": {
    "v0": {
      "label": "Keyword-only (v0)",
      "weights": {
        "keyword":     1.00,
        "similarity":  0.00,
        "substantive": 0.00,
        "causal":      0.00
      },
      "thresholds": {
        "min":        0.15,
        "confidence": 0.25,
        "high":       0.50
      }
    },
    "v5": {
      "label": "Causal-boosted stable (v5.1.8)",
      "weights": {
        "keyword":     0.30,
        "similarity":  0.30,
        "substantive": 0.20,
        "causal":      0.20
      },
      "thresholds": {
        "min":        0.20,
        "confidence": 0.30,
        "high":       0.60
      }
    },
    "v5e": {
      "label": "Metadata-enhanced experimental (v5e)",
      "weights": { "...": "..." },
      "thresholds": { "...": "..." }
    }
  }
}
```

---

## Usage in Wizdam Sikola

### 1. Admin Panel — Version Selector

Load available versions on page load for the weight configuration panel:

```javascript
async function loadSdgVersions() {
  const res  = await fetch('https://api.sangia.org/api/v1/sdg/versions');
  const data = await res.json();

  const select = document.getElementById('sdg-version-select');
  Object.entries(data.data).forEach(([version, config]) => {
    const opt   = document.createElement('option');
    opt.value   = version;
    opt.text    = `${version.toUpperCase()} — ${config.label}`;
    select.appendChild(opt);
  });
}
```

### 2. Populate Default Weights in Admin Form

When admin selects a version, pre-fill the weight inputs with defaults:

```javascript
function onVersionChange(version, versionsData) {
  const cfg = versionsData[version];
  document.getElementById('w-keyword').value     = cfg.weights.keyword;
  document.getElementById('w-similarity').value  = cfg.weights.similarity;
  document.getElementById('w-substantive').value = cfg.weights.substantive;
  document.getElementById('w-causal').value      = cfg.weights.causal;
  document.getElementById('th-min').value        = cfg.thresholds.min;
  document.getElementById('th-confidence').value = cfg.thresholds.confidence;
  document.getElementById('th-high').value       = cfg.thresholds.high;
}
```

### 3. Cache in DB

Version configs change infrequently. Cache them in `analysis_weight_configs` and refresh weekly:

```php
public function syncSdgVersions(): void
{
    $res = Http::get('https://api.sangia.org/api/v1/sdg/versions');
    foreach ($res->json('data') as $version => $config) {
        AnalysisWeightConfig::updateOrCreate(
            ['config_key' => "sdg_$version"],
            ['weights' => $config, 'updated_at' => now()]
        );
    }
}
```
