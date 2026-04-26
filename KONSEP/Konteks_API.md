ARSITEKTUR SISTEM WIZDAM SIKOLA

                    +----------------------------------+
                    |  WIZDOM INDONESIA PLATFORM      |
                    +----------------------------------+
                              |
             +----------------+----------------+
             |                |                |
+------------v-----+ +--------v---------+ +----v---------------+
| PENGUMPULAN DATA | | ANALISIS DAMPAK  | | VISUALISASI DATA   |
+------------------+ +------------------+ +--------------------+
| - Crawler SINTA  | | - Metrik Artikel | | - Peta Distribusi  |
| - Data Akademik  | | - Dampak Peneliti| | - Analitik Visual  |
| - Media Sosial   | | - Tren Penelitian| | - Dasbor Interaktif|
| - Sumber Publik  | | - Kolaborasi     | | - Perbandingan     |
+------------------+ +------------------+ +--------------------+
             |                |                |
             +----------------+----------------+
                              |
                    +---------v----------+
                    |  LAPISAN API       |
                    +--------------------+
                    | - REST API         |
                    | - GraphQL Endpoint |
                    | - Webhook          |
                    +--------------------+
                              |
              +---------------+---------------+
              |                               |
    +---------v---------+           +---------v---------+
    | ANTARMUKA WEB     |           | APLIKASI MOBILE   |
    +-------------------+           +-------------------+
    | - Dasbor Analitik |           | - Penelusuran     |
    | - Peta Interaktif |           | - Profil Peneliti |
    | - Laporan Dampak  |           | - Notifikasi      |
    +-------------------+           +-------------------+
"""

# KOMPONEN SISTEM WIZDOM INDONESIA

"""
1. MODUL PENGUMPULAN DATA
   - Crawler SINTA: Mengumpulkan data dari portal SINTA (Science and Technology Index Indonesia)
   - Pengumpulan Data Akademik: Mendapatkan informasi sitasi, publikasi, dan kolaborasi
   - Monitor Media Sosial: Mengukur dampak dan penyebaran penelitian di media sosial
   - Agregator Penggunaan Publik: Memantau implementasi penelitian dalam kebijakan dan masyarakat

2. MODUL ANALISIS DAMPAK
   - Metrik Dampak Artikel: Penghitungan metrik gabungan dari berbagai sumber
   - Profil Dampak Peneliti: Analisis komprehensif kinerja dan pengaruh peneliti
   - Analisis Tren Penelitian: Identifikasi area penelitian yang sedang berkembang
   - Jaringan Kolaborasi: Pemetaan hubungan antar peneliti dan institusi

3. MODUL VISUALISASI
   - Peta Distribusi Geografis: Visualisasi sebaran peneliti di Indonesia
   - Dasbor Analitik: Tampilan metrik utama dan indikator kinerja
   - Visualisasi Interaktif: Grafik dan chart yang dapat disesuaikan pengguna
   - Generator Laporan: Pembuatan laporan dampak yang komprehensif

4. LAYANAN API
   - REST API: Untuk integrasi dengan sistem eksternal
   - GraphQL Endpoint: Untuk query data yang lebih fleksibel
   - Webhook: Untuk notifikasi dan pembaruan otomatis

5. ANTARMUKA PENGGUNA
   - Aplikasi Web: Dasbor analitik lengkap dengan fitur penjelajahan data
   - Aplikasi Mobile: Versi mobile untuk akses di mana saja
   - Portal Publik: Bagian yang dapat diakses masyarakat umum
"""

# IMPLEMENTASI PENGUKURAN DAMPAK ARTIKEL

"""
Metrik DAMPAK ARTIKEL menggabungkan tiga kategori utama:

1. DAMPAK AKADEMIK (45%)
   - Jumlah sitasi dari berbagai sumber (Google Scholar, SINTA, dll)
   - Normalisasi berdasarkan bidang studi dan usia artikel
   - Kualitas jurnal tempat publikasi (faktor Sinta)
   - Indeks h-relatif dalam bidang studi

2. DAMPAK MEDIA SOSIAL (25%)
   - Mentions di Twitter/X
   - Shares di platform sosial media
   - Pembahasan di blog akademik dan umum
   - Cakupan media berita

3. DAMPAK PENGGUNAAN PRAKTIS (30%)
   - Penerapan dalam kebijakan publik
   - Penggunaan dalam produk/layanan
   - Implementasi dalam praktik industri
   - Unduhan dan pembacaan artikel

Formula Dasar Dampak Artikel:
ImpactScore = (AcademicImpact * 0.45) + (SocialImpact * 0.25) + (PracticalImpact * 0.30)

Dengan normalisasi tambahan berdasarkan:
- Bidang studi (faktor normalisasi berbeda per disiplin)
- Usia artikel (mempertimbangkan waktu untuk mengakumulasi dampak)
- Akreditasi jurnal (bobot tambahan untuk jurnal terakreditasi)
"""

# IMPLEMENTASI PENGUKURAN DAMPAK PENELITI

"""
Metrik DAMPAK PENELITI menggabungkan:

1. METRIK AKADEMIK TRADISIONAL (50%)
   - H-index dan turunannya
   - Jumlah total sitasi
   - Jumlah publikasi berpengaruh (highly cited)
   - Peringkat di SINTA

2. DAMPAK ARTIKEL GABUNGAN (30%)
   - Rata-rata dampak artikel (seperti dihitung di atas)
   - Artikel dengan dampak tertinggi
   - Konsistensi dampak penelitian

3. JANGKAUAN & KOLABORASI (20%)
   - Jaringan kolaborasi (lokal dan internasional)
   - Interdisiplinaritas penelitian
   - Pembimbingan peneliti muda
   - Keterlibatan dengan masyarakat/industri

Formula Dampak Peneliti:
ResearcherImpact = (AcademicMetrics * 0.50) + (ArticleImpact * 0.30) + (Collaboration * 0.20)
"""

# IMPLEMENTASI PEMETAAN DISTRIBUSI PENELITI

"""
Sistem pemetaan distribusi peneliti terdiri dari beberapa lapisan:

1. PETA DASAR INDONESIA
   - Peta administrasi provinsi
   - Data demografis dasar
   - Infrastruktur pendidikan tinggi

2. VISUALISASI PENELITI
   - Titik/marker untuk setiap institusi penelitian
   - Kluster berdasarkan kedekatan geografis
   - Warna untuk menunjukkan peringkat/dampak
   - Ukuran untuk menunjukkan jumlah peneliti

3. LAPISAN DATA OVERLAY
   - Heatmap kepadatan peneliti
   - Visualisasi jaringan kolaborasi antar daerah
   - Pemetaan berdasarkan bidang penelitian
   - Indikator pertumbuhan (perubahan dari waktu ke waktu)

4. FILTER INTERAKTIF
   - Berdasarkan bidang studi
   - Berdasarkan peringkat dampak
   - Berdasarkan periode waktu
   - Berdasarkan jenis institusi

5. DETAIL ON-DEMAND
   - Informasi institusi saat hover/klik
   - Daftar peneliti terkemuka di lokasi
   - Statistik ringkas untuk area yang dipilih
   - Tautan ke profil lengkap
"""

# SKEMA DATABASE

"""
CREATE TABLE researchers (
    id SERIAL PRIMARY KEY,
    sinta_id VARCHAR(50) UNIQUE,
    name VARCHAR(255) NOT NULL,
    title VARCHAR(100),
    affiliation VARCHAR(255),
    department VARCHAR(255),
    email VARCHAR(255),
    location VARCHAR(255),
    province VARCHAR(100),
    field_of_study VARCHAR(255),
    h_index INTEGER DEFAULT 0,
    i10_index INTEGER DEFAULT 0,
    citations INTEGER DEFAULT 0,
    sinta_score NUMERIC(10,2) DEFAULT 0,
    impact_score NUMERIC(10,2) DEFAULT 0,
    rank INTEGER,
    lat NUMERIC(10,6),
    lng NUMERIC(10,6),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE institutions (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100),
    location VARCHAR(255),
    province VARCHAR(100),
    website VARCHAR(255),
    established_year INTEGER,
    researcher_count INTEGER DEFAULT 0,
    publication_count INTEGER DEFAULT 0,
    citation_count INTEGER DEFAULT 0,
    impact_score NUMERIC(10,2) DEFAULT 0,
    lat NUMERIC(10,6),
    lng NUMERIC(10,6),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE articles (
    id SERIAL PRIMARY KEY,
    title TEXT NOT NULL,
    authors TEXT[],
    journal VARCHAR(255),
    journal_id VARCHAR(50),
    publication_year INTEGER,
    doi VARCHAR(255),
    url TEXT,
    abstract TEXT,
    keywords TEXT[],
    field VARCHAR(255),
    sinta_accreditation VARCHAR(50),
    citations_count INTEGER DEFAULT 0,
    social_mentions_count INTEGER DEFAULT 0,
    downloads_count INTEGER DEFAULT 0,
    academic_impact_score NUMERIC(10,2) DEFAULT 0,
    social_impact_score NUMERIC(10,2) DEFAULT 0,
    practical_impact_score NUMERIC(10,2) DEFAULT 0,
    total_impact_score NUMERIC(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE researcher_articles (
    id SERIAL PRIMARY KEY,
    researcher_id INTEGER REFERENCES researchers(id),
    article_id INTEGER REFERENCES articles(id),
    is_corresponding BOOLEAN DEFAULT FALSE,
    author_position INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(researcher_id, article_id)
);

CREATE TABLE collaborations (
    id SERIAL PRIMARY KEY,
    researcher1_id INTEGER REFERENCES researchers(id),
    researcher2_id INTEGER REFERENCES researchers(id),
    strength INTEGER DEFAULT 1,
    articles_count INTEGER DEFAULT 0,
    first_collaboration_year INTEGER,
    latest_collaboration_year INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(researcher1_id, researcher2_id)
);

CREATE TABLE province_statistics (
    id SERIAL PRIMARY KEY,
    province_name VARCHAR(100) NOT NULL,
    researcher_count INTEGER DEFAULT 0,
    institution_count INTEGER DEFAULT 0,
    publication_count INTEGER DEFAULT 0,
    citation_count INTEGER DEFAULT 0,
    avg_impact_score NUMERIC(10,2) DEFAULT 0,
    top_field VARCHAR(255),
    lat NUMERIC(10,6),
    lng NUMERIC(10,6),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(province_name)
);
"""

# LAYANAN API UNTUK VISUALISASI PETA

"""
# Endpoint API untuk Peta Distribusi Peneliti

## 1. Mendapatkan Data Provinsi
GET /api/map/provinces
Response:
{
    "provinces": [
        {
            "id": "ID-JK",
            "name": "DKI Jakarta",
            "researcher_count": 2541,
            "institution_count": 58,
            "avg_impact": 75.4,
            "top_field": "Teknologi Informasi",
            "coordinates": {"lat": -6.2088, "lng": 106.8456}
        },
        // Data provinsi lainnya
    ]
}

## 2. Mendapatkan Data Institusi
GET /api/map/institutions?province=ID-JK
Response:
{
    "institutions": [
        {
            "id": 123,
            "name": "Universitas Indonesia",
            "type": "Perguruan Tinggi Negeri",
            "researcher_count": 587,
            "avg_impact": 82.3,
            "top_researchers": [
                {"id": 456, "name": "Dr. Budi Santoso", "impact_score": 95.2},
                // Peneliti lainnya
            ],
            "coordinates": {"lat": -6.3656, "lng": 106.8267}
        },
        // Institusi lainnya
    ]
}

## 3. Mendapatkan Data Peneliti Terkemuka
GET /api/map/top-researchers?province=ID-JK&limit=10
Response:
{
    "researchers": [
        {
            "id": 456,
            "name": "Dr. Budi Santoso",
            "affiliation": "Universitas Indonesia",
            "field": "Kecerdasan Buatan",
            "h_index": 28,
            "impact_score": 95.2,
            "rank": 1,
            "top_articles": [
                {"id": 789, "title": "Deep Learning untuk Pengenalan Pola Batik", "impact": 92.7},
                // Artikel lainnya
            ]
        },
        // Peneliti lainnya
    ]
}

## 4. Mendapatkan Statistik Agregat
GET /api/map/statistics
Response:
{
    "total_researchers": 24587,
    "total_institutions": 578,
    "avg_impact_national": 68.4,
    "top_provinces": [
        {"name": "DKI Jakarta", "researcher_count": 2541, "avg_impact": 75.4},
        // Provinsi lainnya
    ],
    "top_fields": [
        {"name": "Teknologi Informasi", "researcher_count": 3125, "avg_impact": 72.6},
        // Bidang lainnya
    ]
}

## 5. Mendapatkan Data Jaringan Kolaborasi
GET /api/map/collaborations?province=ID-JK
Response:
{
    "nodes": [
        {"id": "UI", "name": "Universitas Indonesia", "size": 587, "group": 1},
        // Node lainnya
    ],
    "links": [
        {"source": "UI", "target": "ITB", "value": 243},
        // Link lainnya
    ]
}
