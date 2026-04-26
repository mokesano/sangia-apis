# 📋 Konteks Lengkap Project: SDG Classification System (Wizdam AI-sikola)

## 🎯 **Overview Project**
- **Nama**: Wizdam AI-sikola v3.0
- **Tujuan**: Platform AI untuk menganalisis kontribusi penelitian terhadap Sustainable Development Goals (SDGs)
- **Developer**: Wizdam Team & PT. Sangia Research Media and Publishing
- **Teknologi**: PHP, MySQL, JavaScript, CSS, HTML5
- **Arsitektur**: MVC Pattern dengan API-driven approach

## 🏗️ **Struktur Database**
**Database**: `sdg_classification`

### **Tabel Utama:**
1. `users` - User management dengan role (user/admin/researcher)
2. `analysis_history` - Riwayat analisis user
3. `citations_cache` - Cache data kutipan artikel
4. `author_profiles_cache` - Cache profil author
5. `journal_profiles_cache` - Cache profil jurnal
6. `user_articles` - Artikel milik user
7. `image_uploads` - Upload dan resize gambar
8. `system_settings` - Konfigurasi aplikasi
9. `api_usage_logs` - Logging penggunaan API
10. `rate_limits` - Rate limiting system
11. `notifications` - Sistem notifikasi

### **Views Analytics:**
- `user_analytics` - Analitik pengguna
- `api_analytics` - Analitik penggunaan API

## 🔧 **Core Architecture**

### **Database Layer:**
- `config/database.php` - Singleton pattern connection
- `config/config.php` - Konfigurasi aplikasi lengkap
- `config/session.php` - Manajemen session

### **Core Classes:**
- `classes/User.php` - User management
- `classes/APIHandler.php` - Handler untuk 6 API eksternal

### **Security Features:**
- CSRF protection
- Rate limiting (per IP & per user)
- Session security
- Input validation & sanitization
- Password strength requirements

## 🌐 **8 API yang Sudah Terintegrasi (4 Sudah Selesai + 4 Akan Dibuat)**

### **✅ API yang Sudah Selesai:**

### **1. SDG Classification API** *(paste-3.txt)*
- **Endpoint**: `https://www.journals.sangia.org/api/sdg_v5`
- **Input**: DOI, ORCID (manual trigger)
- **Output**: Klasifikasi SDG dengan AI analysis
- **Features**: V5.1.8, 4-komponen scoring, cache system, impact orientation
- **Credentials**: Internal API (no key required)

### **2. Scopus Journal Profile API** *(paste-2.txt)*
- **Input**: ISSN jurnal (manual trigger)
- **Output**: CiteScore, Quartile, SJR, SNIP, Subject Areas
- **Features**: Enhanced quartile detection, discontinued status, coverage info
- **Credentials**: Scopus API Key required
- **Cache**: Smart caching dengan rate limiting

### **3. SINTA Journal Profile API** *(paste-4.txt)*
- **Input**: ISSN jurnal (manual trigger)
- **Output**: Impact factor, grade, profil jurnal Indonesia
- **Features**: Smart detection, weekly cache, access pattern tracking
- **Credentials**: cURL-based (no API key)
- **Method**: Web scraping dengan compression cache

### **4. SDG Analysis Interface** *(paste.txt)*
- **Frontend**: Modern interface untuk menampilkan hasil SDG
- **Integration**: Terintegrasi dengan SDG Classification API
- **Features**: Charts, visualizations, responsive design
- **Input Method**: Manual input ORCID/DOI

### **5. Citation Analysis API (CrossRef)**
- **Input**: DOI artikel (manual trigger)
- **Output**: Jumlah kutipan, impact metrics, artikel profile
- **Credentials**: CrossRef API (official)
- **Target**: Trend analysis dan citation network

### **6. Scopus Author Profile API**
- **Input**: Scopus Author ID (manual trigger)  
- **Output**: Daftar artikel, h-index, collaboration metrics
- **Credentials**: Scopus API Key
- **Target**: Author analytics dan research mapping

### **7. ORCID Researcher Profile API**
- **Input**: ORCID ID (manual trigger)
- **Output**: Complete researcher profile, affiliations
- **Credentials**: ORCID public API
- **Target**: Researcher verification dan data validation

### **8. Trend Analysis & Impact Engine**
- **Input**: Aggregate data dari API 1-7
- **Output**: Trend analysis, impact predictions, recommendations
- **Features**: Time-series analysis, research mapping, institutional impact
- **Method**: Data processing dan machine learning analysis

### **9. Image Resize Service**
- **Features**: Auto-resize gambar upload user
- **Format**: Multiple sizes (thumbnail, medium, original)
- **Integration**: User dashboard dan profile management

## ✅ **File yang Sudah Selesai**

### **Database & Config:**
- ✅ Database schema lengkap dengan sample data
- ✅ `config/database.php` - Connection dengan singleton
- ✅ `config/config.php` - Konfigurasi aplikasi
- ✅ `config/session.php` - Session management

### **Core Classes:**
- ✅ `classes/User.php` - Complete user management
- ✅ `classes/APIHandler.php` - Semua 6 API terintegrasi

### **Authentication System:**
- ✅ `auth/login.php` - Login dengan rate limiting
- ✅ `auth/register.php` - Registrasi dengan validasi

### **Dashboard Systems:**
- ✅ `admin/dashboard.php` - Admin dashboard lengkap
- ✅ `user/dashboard.php` - User dashboard dengan analytics

### **API Endpoints:**
- ✅ `api/analyze.php` - Endpoint analisis lengkap
- ✅ `api/upload-image.php` - Image resize functionality

### **CSS Styling:**
- ✅ `assets/css/main.css` - Stylesheet utama
- ✅ `assets/css/auth.css` - Styling autentikasi
- ✅ `assets/css/admin.css` - Styling admin dashboard

### **Templates:**
- ✅ `templates/auth_header.php` - Header autentikasi
- ✅ `templates/auth_footer.php` - Footer autentikasi
- ✅ `templates/admin_header.php` - Header admin
- ✅ `templates/admin_sidebar.php` - Sidebar admin

### **Helper Classes:**
- ✅ `includes/Logger.php` - Sistem logging lengkap
- ✅ `includes/RateLimiter.php` - Rate limiting system
- ✅ `includes/InputValidator.php` - **BARU SELESAI** - Validation comprehensive

## 🚧 **File yang Belum Selesai (Prioritas)**

### **Prioritas Tinggi - Helper Classes:**
11. `includes/CacheManager.php` - Cache management system
12. `includes/Analytics.php` - Analytics processing
13. `includes/UserAnalytics.php` - User-specific analytics
14. `includes/EmailHandler.php` - Email notifications
15. `includes/functions.php` - Global utility functions

### **Prioritas Tinggi - Main Files:**
16. `index.php` - Homepage dengan analysis interface
17. `auth/logout.php` - Logout handler
18. `auth/forgot-password.php` - Password reset

### **Prioritas Tinggi - Templates:**
19. `templates/header.php` - Main header
20. `templates/footer.php` - Main footer
21. `templates/navbar.php` - Navigation bar
22. `templates/sidebar.php` - Main sidebar
23. `templates/user_header.php` - User header
24. `templates/user_footer.php` - User footer

### **Prioritas Menengah - Admin Pages:**
25. `admin/users.php` - User management
26. `admin/analytics.php` - System analytics
27. `admin/settings.php` - System settings
28. `admin/api-keys.php` - API key management

### **Prioritas Menengah - User Pages:**
29. `user/profile.php` - User profile management
30. `user/history.php` - Analysis history
31. `user/analysis-result.php` - Detailed analysis results
32. `user/settings.php` - User settings

### **Prioritas Menengah - Additional APIs:**
33. `api/user-actions.php` - User-specific actions
34. `api/admin-actions.php` - Admin actions
35. `api/recent-uploads.php` - Recent uploads data
36. `api/export-report.php` - Export functionality

### **Prioritas Rendah - Static Pages:**
37. `pages/terms.php` - Terms of service
38. `pages/privacy.php` - Privacy policy
39. `pages/about.php` - About page
40. `pages/help.php` - Help documentation

### **JavaScript Files:**
41. `assets/js/main.js` - Main functionality
42. `assets/js/admin.js` - Admin interactions
43. `assets/js/auth.js` - Auth validations
44. `assets/js/charts.js` - Chart configurations
45. `assets/js/image-resize.js` - Image upload handling

## 🎨 **Design Patterns & Conventions**

### **Code Standards:**
- **PHP**: PSR-4 autoloading, camelCase methods, PascalCase classes
- **Database**: snake_case tables/columns
- **CSS**: BEM methodology
- **JavaScript**: ES6+ features, camelCase

### **Security Implementation:**
- CSRF tokens untuk semua forms
- Rate limiting (100 requests/hour per user)
- Input validation & sanitization
- SQL injection prevention (prepared statements)
- XSS protection (htmlspecialchars)

### **Cache Strategy:**
- SDG Analysis: 24 jam
- Citation Data: 12 jam  
- Author Profiles: 24 jam
- Journal Profiles: 24 jam

### **File Upload:**
- Max size: 10MB
- Allowed types: JPG, PNG, GIF, WebP
- Auto-resize: thumbnail (150x150), medium (500x500)

## 🔄 **Integration Points**

### **User Dashboard Features:**
- Personal SDG analytics dengan charts
- Upload area untuk gambar
- Analysis history dengan filtering
- Profile management terintegrasi ORCID

### **Admin Dashboard Features:**
- System health monitoring
- User analytics dan growth metrics
- API usage statistics dengan charts
- Real-time system metrics

### **API Integration Flow:**
1. User input (DOI/ORCID/Author ID/ISSN)
2. Check cache first
3. Call external APIs jika perlu
4. Process & analyze data
5. Store hasil ke database
6. Return formatted response
7. Log usage untuk analytics

## 📊 **Analytics & Monitoring**

### **User Analytics:**
- Total analyses per user
- SDG distribution charts
- Research impact metrics
- Publication timeline

### **System Analytics:**
- API response times
- Success/failure rates
- User growth metrics
- Cache hit rates
- Error tracking

## 🎯 **Next Steps**
**Sedang Dikerjakan**: Helper Classes (InputValidator ✅ selesai)
**Selanjutnya**: CacheManager.php untuk optimasi performa API calls

---