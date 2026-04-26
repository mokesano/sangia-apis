# 🚀 Panduan Lengkap Integrasi API - SDG Classification System

## 📊 **Overview API Architecture**

### **Status API Integration (6 dari 8 API)**

| No | API Name | Status | Input Method | Credentials | Cache Type |
|## 🕷️ **Web Crawling Strategy (Hybrid API + Crawling Approach)**

### **Berdasarkan SINTA Pattern yang Sudah Berhasil:**

SINTA API (paste-4.txt) sudah menunjukkan crawling yang efektif:
- ✅ **Respectful crawling** dengan delay dan retry
- ✅ **Smart cache** untuk menghindari re-crawling  
- ✅ **Pattern extraction** yang robust
- ✅ **Error handling** yang comprehensive
- ✅ **User agent rotation** untuk menghindari blocking

### **Crawling Use Cases untuk SDG Classification:**

#### **1. Scholar Profile Enhancement**
```php
// Target: Google Scholar, ResearchGate, Academia.edu
Purpose: Melengkapi data ORCID yang mungkin tidak lengkap
Input: Nama peneliti, institution
Output: Publication list, h-index, citation metrics, collaboration network
Cache Strategy: Weekly update dengan smart detection
```

#### **2. Journal Impact Crawling**  
```php
// Target: Journal websites, impact factor databases
Purpose: Update data journal yang berubah annually
Input: ISSN, journal name
Output: Current impact factor, scope, editorial board info
Cache Strategy: Monthly update atau berdasarkan access pattern
```

#### **3. Institution Research Mapping**
```php
// Target: University websites, research directories
Purpose: Mapping research landscape dan collaboration
Input: Institution name, department
Output: Active researchers, ongoing projects, research focus
Cache Strategy: Quarterly update dengan change detection
```

#### **4. Citation Network Analysis**
```php
// Target: Citation databases, related papers platforms
Purpose: Build citation network untuk trend analysis
Input: DOI artikel
Output: Citation network, related research, co-citation analysis
Cache Strategy: Monthly update dengan incremental updates
```

### **Smart Crawling Implementation:**

#### **Pattern 1: Respectful Crawling (Berdasarkan SINTA)**
```php
class SmartCrawler {
    private $delays = [
        'google_scholar' => 3,     // 3 seconds delay
        'researchgate' => 2,       // 2 seconds delay  
        'journal_sites' => 1,      // 1 second delay
        'institutional' => 2       // 2 seconds delay
    ];
    
    public function crawlWithRespect($url, $source) {
        // Check robots.txt
        if (!$this->isAllowedByRobots($url)) {
            return false;
        }
        
        // Apply appropriate delay
        sleep($this->delays[$source]);
        
        // Use SINTA-style fetchWithRetry
        return $this->fetchWithRetry($url, 3, 30);
    }
}
```

#### **Pattern 2: Queue-based Crawling**
```php
class CrawlQueue {
    public function addToCrawlQueue($url, $type, $priority = 1) {
        // Add ke database queue
        $sql = "INSERT INTO crawl_queue (url, type, priority, created_at) 
                VALUES (?, ?, ?, NOW())";
    }
    
    public function processCrawlQueue($batchSize = 10) {
        // Process via background job/cron
        // Rate limiting per source
        // Smart retry logic
    }
}
```

#### **Pattern 3: Cache Integration**
```php
// Extend CacheManager untuk crawling results
public function saveCrawlResult($url, $data, $ttl = null) {
    $key = 'crawl_' . md5($url);
    
    // Use SINTA-style compression
    $compressed = gzcompress(json_encode($data), 9);
    
    // Smart TTL based on content type
    if ($ttl === null) {
        $ttl = $this->getSmartTTL($url);
    }
    
    return $this->saveCompressed($key, $data, $ttl);
}

private function getSmartTTL($url) {
    if (strpos($url, 'google.com/scholar') !== false) {
        return 86400 * 7; // Weekly for scholar profiles
    } elseif (strpos($url, 'journal') !== false) {
        return 86400 * 30; // Monthly for journal data
    }
    return 86400 * 3; // Default 3 days
}
```

----|----------|--------|--------------|-------------|------------|
| 1 | **SDG Classification** | ✅ Selesai | ORCID/DOI (manual) | Internal API | gzip + hash |
| 2 | **Scopus Journal Profile** | ✅ Selesai | ISSN (manual) | Scopus API Key | Smart cache |
| 3 | **SINTA Journal Profile** | ✅ Selesai | ISSN (manual) | cURL scraping | Weekly cache |
| 4 | **SDG Interface Frontend** | ✅ Selesai | Web UI | None | Browser cache |
| 5 | **Citation Analysis (CrossRef)** | 🔄 Dalam Proses | DOI (manual) | CrossRef credential | TBD |
| 6 | **ORCID Researcher Profile** | 🔄 Dalam Proses | ORCID (manual) | ORCID Public API | TBD |
| 7 | **Trend Analysis Engine** | ❌ Belum | Aggregate data | Internal processing | Memory cache |
| 8 | **Image Resize Service** | ❌ Belum | File upload | None | File system |
| 9 | **Web Crawling Engine** | ❌ Belum | Various targets | Smart crawling | Rotating cache |

---

## 🏗️ **API Integration Patterns (Berdasarkan Kode Existing)**

### **Pattern 1: SDG Classification API**
```php
// CHARACTERISTICS:
- Function-based approach
- Extensive caching dengan gzip compression
- Memory cache + file cache (dual layer)
- Complex scoring algorithm (4 komponen)
- Error handling dengan try-catch
- Rate limiting awareness
- API version tracking (v5.1.8)

// CACHE PATTERN:
$cacheKey = md5($text . '_' . $sdg . '_contribution_v4');
$cacheFile = getCacheFilename('orcid', $orcid);
saveToCache($cacheFile, $result);

// ERROR HANDLING:
try {
    $result = main();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
```

### **Pattern 2: Scopus API**
```php
// CHARACTERISTICS:
- Class-based approach (ScopusAPI class)
- Multiple endpoint attempts
- Comprehensive data extraction
- Debug mode dengan logging
- Smart quartile detection
- Enhanced subject areas analysis

// CLASS STRUCTURE:
class ScopusAPI {
    private $apiKey;
    private $baseUrl = 'https://api.elsevier.com/content/serial/title';
    
    public function searchByISSN($issn) { }
    private function makeHttpRequest($url, $headers) { }
    private function parseJournalData($apiData, $originalISSN) { }
}

// ERROR PATTERN:
if ($httpCode === 404) return ['success' => false, 'error' => 'Journal not found'];
if ($httpCode === 401) return ['success' => false, 'error' => 'Invalid API Key'];
```

### **Pattern 4: Web Crawling (Berdasarkan SINTA Pattern)**
```php
// CHARACTERISTICS (dari paste-4.txt):
- cURL-based dengan retry mechanism
- Smart user agent rotation
- Respectful crawling dengan delays
- Pattern-based data extraction
- Robust error handling
- Cache untuk menghindari re-crawling

// CRAWLING PATTERN (SINTA style):
function fetchWithRetry($url, $maxAttempts = 3, $timeout = 30) {
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)...',
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        if ($response && $statusCode >= 200 && $statusCode < 300) {
            return $response;
        }
        
        if ($attempt < $maxAttempts) {
            sleep($attempt * 2); // Progressive delay
        }
    }
}

// DATA EXTRACTION PATTERN:
if (preg_match('/<div[^>]*class=["\']profile-name["\'][^>]*>([^<]+)<\/div>/is', 
               $html, $matches)) {
    $title = trim($matches[1]);
}
```
```php
// CHARACTERISTICS:
- Efficient caching dengan compression
- Smart access pattern tracking
- Weekly cache dengan auto-expiry
- Batch update capabilities
- Hash-based change detection
- Status endpoint untuk monitoring

// SMART CACHE PATTERN:
$cacheFile = CACHE_DIRECTORY . '/sinta_' . md5($normalizedIssn) . '.json.gz';
$cachedData = loadFromCache($cacheFile);
if (isCacheValid($cacheFile, $cachedData, $normalizedIssn)) {
    updateAccessCount($normalizedIssn, $cachedData);
}

// COMPRESSION PATTERN:
$content = json_encode($data, JSON_UNESCAPED_UNICODE);
$compressedContent = gzcompress($content, 9);
file_put_contents($cacheFile, $compressedContent);
```

---

## 🔧 **Helper Classes Integration Strategy**

### **1. InputValidator.php (Sudah Dibuat)**
**Fungsi**: Validasi semua format input API
```php
// VALIDATIONS YANG SUDAH ADA:
- ORCID: 0000-0002-5152-9727 format dengan checksum
- DOI: 10.1234/example format
- Email, URL, numeric validations
- File upload validations

// YANG PERLU DITAMBAHKAN:
- ISSN validation untuk Scopus/SINTA
- Scopus Author ID validation  
- CrossRef DOI enhanced validation
- API key format validations
```

### **2. CacheManager.php**
**Fungsi**: Unified cache management untuk semua API
```php
// STRATEGY BERDASARKAN PATTERN EXISTING:
class CacheManager {
    // Compression cache (SINTA pattern)
    public function saveCompressed($key, $data, $ttl = 604800) { }
    
    // Memory + file cache (SDG pattern)  
    public function saveLayered($key, $data, $memoryTtl, $fileTtl) { }
    
    // Smart cache dengan access tracking (SINTA pattern)
    public function saveWithTracking($key, $data, $accessPattern) { }
    
    // Hash-based change detection
    public function hasChanged($key, $newData) { }
}
```

### **4. WebCrawler.php (Smart Crawling Engine)**
**Fungsi**: Intelligent web crawling untuk data yang tidak tersedia via API
```php
class WebCrawler {
    // Scholar Profile Crawling
    public function crawlGoogleScholar($authorName) { }
    public function crawlResearchGate($authorProfile) { }
    
    // Journal Data Enhancement  
    public function crawlJournalWebsite($issn, $journalName) { }
    public function crawlImpactFactorDatabases($issn) { }
    
    // Institution Research Mapping
    public function crawlInstitutionProfile($institutionName) { }
    public function crawlResearchDirectories($institution) { }
    
    // Citation Network Analysis
    public function crawlCitationNetworks($doi) { }
    public function crawlRelatedPapers($keywords) { }
    
    // Respectful crawling dengan rate limiting
    private function respectfulRequest($url, $delay = 2) { }
    private function rotateUserAgent() { }
    private function detectCaptcha($html) { }
}
```
**Fungsi**: Central coordinator untuk semua API calls
```php
class APIHandler {
    // SDG Analysis
    public function analyzeSDG($orcid = null, $doi = null) { }
    
    // Journal Profiles
    public function getJournalProfile($issn, $source = 'both') { } // Scopus + SINTA
    
    // Citation Analysis (API #5)
    public function getCitationData($doi) { }
    
    // Researcher Profile (API #6)  
    public function getResearcherProfile($orcid) { }
    
    // Trend Analysis (API #7)
    public function analyzeTrends($data) { }
}
```

---

## 📈 **API Integration Roadmap**

### **Phase 1: Completion (APIs #5-6)**
```
Citation Analysis API:
- Input: DOI (manual trigger)
- Output: Citation count, citing papers, impact metrics
- Integration: Extend DOI validation dalam InputValidator
- Cache: Implementasikan pattern kompresi seperti SINTA

ORCID Researcher Profile API:
- Input: ORCID ID (manual trigger)
- Output: Full profile, publikasi list, affiliations
- Integration: Enhance ORCID validation untuk profile endpoint
- Cache: Memory + file cache seperti SDG API
```

### **Phase 2: Advanced Features (APIs #7-9)**
```
Trend Analysis Engine:
- Aggregate data dari semua API + crawling results
- Time-series analysis dengan historical data
- Research mapping dengan institution crawling
- Network analysis dengan citation crawling

Image Resize Service:
- Multiple format support
- Compression optimization  
- CDN integration ready

Web Crawling Engine:
- Scholar profile enhancement (Google Scholar, ResearchGate)
- Journal data enrichment (publisher websites)
- Institution research mapping (university directories)
- Citation network analysis (related papers crawling)
```

---

## 🔐 **Security & Credentials Management**

### **Berdasarkan Pattern yang Ada:**
```php
// Environment variables untuk API keys
$SCOPUS_API_KEY = $_ENV['SCOPUS_API_KEY'] ?? 'default_key';

// Database storage untuk credentials (config.php pattern)
define('SCOPUS_API_KEY', $_ENV['SCOPUS_API_KEY'] ?? '');
define('CROSSREF_API_KEY', $_ENV['CROSSREF_API_KEY'] ?? '');

// Rate limiting per API
$RATE_LIMITS = [
    'scopus' => ['requests_per_hour' => 100],
    'crossref' => ['requests_per_hour' => 200],
    'sinta' => ['requests_per_hour' => 50] // cURL-based
];
```

---

## 🚀 **Quick Start untuk Development Selanjutnya**

### **1. Struktur File yang Harus Dibuat:**
```
helpers/
├── CacheManager.php       (PRIORITAS TINGGI)
├── APIHandler.php         (PRIORITAS TINGGI)  
├── WebCrawler.php         (PRIORITAS TINGGI)
├── Analytics.php          (PRIORITAS SEDANG)
├── UserAnalytics.php      (PRIORITAS SEDANG)
├── CrawlQueue.php         (PRIORITAS SEDANG)
├── EmailHandler.php       (PRIORITAS RENDAH)

api/
├── citation-analysis.php     (API #5)
├── researcher-profile.php    (API #6)
├── trend-analysis.php        (API #7)
├── image-resize.php          (API #8)
└── crawl-manager.php         (API #9)

crawling/
├── scholar-crawler.php       (Google Scholar profile)
├── journal-crawler.php       (Journal website data)
├── institution-crawler.php   (University research mapping)
└── citation-crawler.php      (Citation network analysis)
```

### **2. Integration Checklist:**
```
✅ Patterns & Standards:
- [ ] Error handling konsisten di semua API
- [ ] Cache strategy unified dengan CacheManager
- [ ] Rate limiting implementation
- [ ] Response format standardization
- [ ] Debug mode untuk development

✅ Validation & Security:
- [ ] Input validation untuk semua API formats
- [ ] Credential management system
- [ ] API key rotation capability
- [ ] Request logging untuk monitoring

✅ Performance Optimization:
- [ ] Smart caching berdasarkan access patterns
- [ ] Compression untuk large responses
- [ ] Memory optimization untuk analytics
- [ ] Background processing untuk batch operations
```

### **3. Development Commands:**
```bash
# Testing individual APIs
php api/analyze.php?orcid=0000-0002-5152-9727
php api/journal-profile.php?issn=2076-3417&source=scopus
php api/sinta-profile.php?issn=2076-3417

# Testing crawling features
php crawling/scholar-crawler.php?author=john+doe&institution=university
php crawling/journal-crawler.php?issn=2076-3417&enhance=true
php api/crawl-manager.php?action=status

# Cache management
php api/cache-status.php
php api/cache-clear.php?api=all
php api/batch-update.php

# Crawl queue management
php api/crawl-queue.php?action=process&batch=10
php api/crawl-queue.php?action=add&url=scholar_profile&priority=high

# Monitoring
php api/api-status.php
php api/performance-stats.php
php api/crawl-stats.php
```

---

## 📋 **Template untuk Conversation Selanjutnya**

```
KONTEKS CEPAT:
1. Project: SDG Classification System dengan 9 API integrations (8 API + 1 Crawling Engine)
2. 4 API sudah selesai: SDG Analysis, Scopus Journal, SINTA Journal (dengan crawling), Frontend
3. 2 API dalam proses: Citation Analysis (CrossRef), ORCID Researcher Profile  
4. 3 API belum mulai: Trend Analysis Engine, Image Resize Service, Web Crawling Engine

PATTERN YANG HARUS DIIKUTI:
- Cache: gzip compression + smart detection + access tracking
- Error: try-catch dengan HTTP codes + detailed messages
- Class: PSR-4 autoloading, dependency injection ready
- Security: environment variables, rate limiting, input validation
- Crawling: respectful crawling seperti SINTA pattern dengan delay & retry

PRIORITAS DEVELOPMENT:
1. CacheManager.php (unified cache system untuk API + crawling)
2. WebCrawler.php (smart crawling engine berdasarkan SINTA pattern)
3. APIHandler.php (central orchestrator API + crawling)
4. Citation Analysis API (CrossRef integration)
5. ORCID Researcher Profile API
6. Analytics.php (trend analysis engine dengan crawling data)

STATUS HELPER CLASSES:
✅ InputValidator.php (comprehensive validation)
🔄 Selanjutnya: CacheManager.php, WebCrawler.php, atau APIHandler.php

CRAWLING STRATEGY:
- Hybrid API + Crawling approach (API primary, crawling untuk enhancement)
- Scholar profiles: Google Scholar, ResearchGate crawling
- Journal data: Publisher website crawling
- Institution mapping: University directory crawling  
- Citation network: Related papers crawling
- Queue-based processing dengan respectful rate limiting
```

---