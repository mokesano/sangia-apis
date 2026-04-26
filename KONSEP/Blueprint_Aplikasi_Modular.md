# 🚀 Blueprint Aplikasi Modular: Wizdam Research Platform

## 🎯 **Visi Aplikasi**
Platform penelitian terintegrasi yang menggabungkan 9 API untuk analisis SDG, profiling peneliti, trend analysis, dan rekomendasi kebijakan dengan sistem login/dashboard yang modular.

## 📊 **Struktur Aplikasi Modular**

### 🔐 **1. Authentication & User Management System**

#### **A. Login System**
```
/auth/
├── login.php                 (Multi-role login)
├── register.php              (User registration)
├── logout.php                (Session management)
├── forgot-password.php       (Password recovery)
└── social-login/             (Google, ORCID integration)
    ├── google-auth.php
    └── orcid-auth.php
```

#### **B. User Roles & Permissions**
- **Admin** - Full access, user management, system settings
- **Researcher** - Personal dashboard, data validation, collaboration
- **Institution** - Institutional analytics, researcher management
- **Government** - Policy insights, trend analysis, reporting
- **Industry** - Innovation metrics, collaboration opportunities
- **Public** - Limited access, basic search, public data

#### **C. Database Schema Enhancement**
```sql
-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'researcher', 'institution', 'government', 'industry', 'public') DEFAULT 'public',
    orcid VARCHAR(19) UNIQUE,
    scopus_author_id VARCHAR(20),
    institution_name VARCHAR(200),
    government_agency VARCHAR(200),
    industry_sector VARCHAR(100),
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP,
    profile_data JSON  -- Store additional profile info
);

-- User validation data table
CREATE TABLE user_validations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    validation_type ENUM('orcid', 'publication', 'affiliation', 'citation'),
    original_data JSON,        -- Data from API
    validated_data JSON,       -- User-corrected data
    validation_status ENUM('pending', 'approved', 'rejected'),
    validated_by INT,          -- Admin user who validated
    validated_at TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (validated_by) REFERENCES users(id)
);

-- User sessions table
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_token VARCHAR(255) UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### 🏠 **2. Dashboard System (Role-Based)**

#### **A. Admin Dashboard** (`/admin/`)
```
admin/
├── index.php                 (Admin overview)
├── users/
│   ├── list.php             (User management)
│   ├── edit.php             (User editing)
│   └── permissions.php      (Role management)
├── system/
│   ├── api-status.php       (Monitor all 9 APIs)
│   ├── cache-management.php (Cache control)
│   ├── logs.php             (System logs)
│   └── settings.php         (System configuration)
├── data/
│   ├── validation-queue.php (Pending validations)
│   ├── bulk-updates.php     (Batch data updates)
│   └── export.php           (Data export tools)
└── analytics/
    ├── usage-stats.php      (Platform usage)
    ├── api-analytics.php    (API performance)
    └── user-engagement.php  (User behavior)
```

#### **B. Researcher Dashboard** (`/researcher/`)
```
researcher/
├── index.php                (Personal overview)
├── profile/
│   ├── view.php             (View profile from APIs)
│   ├── validate.php         (Validate/correct data)
│   └── connect-accounts.php (Link ORCID, Scopus)
├── publications/
│   ├── list.php             (My publications)
│   ├── sdg-analysis.php     (SDG contribution)
│   ├── citations.php        (Citation tracking)
│   └── impact.php           (Impact metrics)
├── collaboration/
│   ├── find-researchers.php (Find collaborators)
│   ├── my-network.php       (My research network)
│   └── opportunities.php    (Collaboration opportunities)
└── tools/
    ├── trend-analysis.php   (Personal trends)
    ├── journal-finder.php   (Journal recommendation)
    └── export-cv.php        (Export academic CV)
```

#### **C. Institution Dashboard** (`/institution/`)
```
institution/
├── index.php                (Institution overview)
├── researchers/
│   ├── list.php             (Institution researchers)
│   ├── performance.php      (Performance metrics)
│   └── recruitment.php      (Talent identification)
├── research/
│   ├── portfolio.php        (Research portfolio)
│   ├── sdg-impact.php       (SDG contribution)
│   ├── collaboration.php    (External collaboration)
│   └── trends.php           (Research trends)
├── analytics/
│   ├── rankings.php         (Institution rankings)
│   ├── benchmarking.php     (Peer comparison)
│   └── growth.php           (Growth analytics)
└── reports/
    ├── annual-report.php    (Annual research report)
    ├── funding-report.php   (Funding analysis)
    └── impact-report.php    (Impact assessment)
```

#### **D. Government Dashboard** (`/government/`)
```
government/
├── index.php                (Policy overview)
├── policy/
│   ├── recommendations.php  (AI-generated recommendations)
│   ├── impact-assessment.php (Policy impact analysis)
│   └── implementation.php   (Implementation tracking)
├── research/
│   ├── national-trends.php  (National research trends)
│   ├── sdg-progress.php     (SDG progress tracking)
│   ├── institution-map.php  (Research institution mapping)
│   └── funding-analysis.php (Research funding analysis)
├── insights/
│   ├── innovation-hubs.php  (Innovation ecosystem)
│   ├── talent-pipeline.php  (Researcher pipeline)
│   └── international.php    (International collaboration)
└── reports/
    ├── policy-brief.php     (Policy briefs)
    ├── white-papers.php     (Government white papers)
    └── parliamentary.php    (Parliamentary reports)
```

### 🔧 **3. API Integration Layer**

#### **A. API Manager Class** (`/classes/APIManager.php`)
```php
class APIManager {
    private $apis = [
        'sdg_classification' => '/api/sdg-analysis.php',
        'scopus_journal' => '/api/scopus-journal.php', 
        'sinta_journal' => '/api/sinta-journal.php',
        'scopus_author' => '/api/scopus-author.php',
        'orcid_profile' => '/api/orcid-profile.php',
        'citation_analysis' => '/api/citation-analysis.php',
        'trend_analysis' => '/api/trend-analysis.php',      // NEW
        'policy_recommendations' => '/api/policy-rec.php',  // NEW  
        'image_resize' => '/api/image-resize.php'           // NEW
    ];
    
    public function callAPI($apiName, $params) { }
    public function getAPIStatus() { }
    public function getCacheStatus($apiName) { }
    public function refreshCache($apiName, $params) { }
}
```

#### **B. Unified Data Validation System**
```php
class DataValidator {
    public function validateORCIDProfile($userData, $apiData) { }
    public function validatePublications($userData, $apiData) { }
    public function validateAffiliations($userData, $apiData) { }
    public function validateCitations($userData, $apiData) { }
    public function saveValidation($userId, $type, $data) { }
}
```

### 📈 **4. New API Development (APIs #7-9)**

#### **A. Trend Analysis Engine** (`/api/trend-analysis.php`)
```php
/**
 * Menganalisis trend dampak peneliti dan artikel
 * Input: user_id, time_range, analysis_type
 * Output: Trend metrics seperti artifact "Metrik Dampak Artikel"
 */
Features:
- Time-series analysis dari semua 6 API
- Impact trajectory prediction
- Collaboration network evolution
- SDG contribution trends
- Citation growth patterns
- Journal impact progression
```

#### **B. Policy Recommendation Engine** (`/api/policy-recommendations.php`)
```php
/**
 * Generate rekomendasi kebijakan untuk stakeholder
 * Input: stakeholder_type, domain, region
 * Output: Structured recommendations seperti "Analisis Trend Penelitian"
 */
Features:
- AI-powered policy synthesis
- Stakeholder-specific recommendations
- Evidence-based suggestions
- Implementation roadmaps
- Impact projections
- Success metrics
```

#### **C. Image Resize Service** (`/api/image-resize.php`)
```php
/**
 * Service untuk resize image OJS v2.4.8.2
 * Input: image_file, target_dimensions, format
 * Output: Resized image dengan multiple formats
 */
Features:
- Multiple format support (JPG, PNG, WebP)
- Smart compression
- Batch processing
- OJS integration ready
- CDN optimization
```

### 🔗 **5. Integration & Workflow**

#### **A. User Journey - Researcher**
1. **Registration/Login** → Connect ORCID/Scopus accounts
2. **Profile Validation** → Review and correct API data
3. **Dashboard Overview** → See personal research metrics
4. **Data Analysis** → Access all analytical tools
5. **Collaboration** → Find and connect with other researchers
6. **Export/Share** → Generate reports and CVs

#### **B. User Journey - Institution**
1. **Institution Setup** → Register institution account
2. **Researcher Import** → Import/invite institutional researchers  
3. **Analytics Dashboard** → Monitor institutional performance
4. **Benchmarking** → Compare with peer institutions
5. **Reporting** → Generate institutional reports
6. **Strategic Planning** → Use insights for decision making

#### **C. User Journey - Government**
1. **Government Access** → Specialized government dashboard
2. **National Overview** → See national research landscape
3. **Policy Analysis** → Generate policy recommendations
4. **Implementation Tracking** → Monitor policy effectiveness
5. **International Comparison** → Benchmark against other countries
6. **Strategic Reports** → Generate policy briefs and white papers

### 💾 **6. Data Management Strategy**

#### **A. Cache Enhancement**
- **User-specific cache** untuk personalized data
- **Role-based cache** untuk dashboard content
- **Validation cache** untuk approved corrections
- **Collaborative filtering** untuk recommendations

#### **B. Data Validation Workflow**
1. **API Data Import** → Fetch from 6 existing APIs
2. **User Validation** → Users correct/verify their data
3. **Admin Approval** → Admins approve critical validations
4. **Cache Update** → Update caches with validated data
5. **Quality Metrics** → Track data quality improvements

### 🚀 **7. Implementation Roadmap**

#### **Phase 1: Foundation (Weeks 1-2)**
- [ ] Authentication system with multi-role support
- [ ] Basic dashboard structures for all roles
- [ ] Database schema implementation
- [ ] API Manager integration

#### **Phase 2: Core Dashboards (Weeks 3-4)**
- [ ] Researcher dashboard with validation features
- [ ] Admin dashboard with system management
- [ ] Institution dashboard with basic analytics
- [ ] Government dashboard with policy tools

#### **Phase 3: Advanced Features (Weeks 5-6)**
- [ ] Trend Analysis Engine (API #7)
- [ ] Policy Recommendation Engine (API #8)  
- [ ] Image Resize Service (API #9)
- [ ] Advanced analytics and reporting

#### **Phase 4: Enhancement (Weeks 7-8)**
- [ ] Real-time notifications
- [ ] Advanced collaboration features
- [ ] Mobile app development
- [ ] Performance optimization

### 🎨 **8. UI/UX Enhancement Strategy**

#### **A. Design System**
- Extend existing SDGs Presentation design
- Consistent color scheme and typography
- Role-specific UI variations
- Mobile-first responsive design

#### **B. Component Library**
- Reusable dashboard widgets
- Chart/visualization components  
- Form components with validation
- Navigation components

#### **C. User Experience**
- Progressive data loading
- Smart notifications
- Contextual help system
- Keyboard shortcuts for power users

---

## 🎯 **Success Metrics**

### **User Engagement**
- Active users per role type
- Data validation completion rates
- API usage patterns
- Collaboration network growth

### **Data Quality**
- Validation accuracy rates
- User correction adoption
- Cache hit rates
- API response times

### **Business Impact**
- Research collaboration increases
- Policy implementation success
- Institution performance improvements
- Government decision-making enhancement

---