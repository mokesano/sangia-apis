# 🔌 Sangia APIs — *Sangia API Engine*

**Pure analysis engine untuk ekosistem Sangia Publishing. Menyediakan REST API stateless untuk klasifikasi SDG, metrik Scopus/ORCID/SINTA, analisis tren, dan rekomendasi kebijakan — tanpa menyimpan data apa pun.**

---

<p align="center">
  <a href="https://github.com/mokesano/sangia-apis">
    <img src="https://img.shields.io/badge/PHP-^8.1-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP Version">
  </a>
  <a href="https://github.com/mokesano/sangia-apis/blob/master/LICENSE">
    <img src="https://img.shields.io/badge/license-MIT-blue?style=for-the-badge" alt="License">
  </a>
  <a href="https://github.com/mokesano/sangia-apis/actions">
    <img src="https://img.shields.io/badge/build-passing-brightgreen?style=for-the-badge&logo=github-actions&logoColor=white" alt="Build Status">
  </a>
  <a href="https://github.com/mokesano/sangia-apis/releases">
    <img src="https://img.shields.io/badge/release-v1.0.0--alpha-lightgrey?style=for-the-badge" alt="Release">
  </a>
  <a href="https://github.com/mokesano/sangia-apis/security/advisories">
    <img src="https://img.shields.io/badge/security-policy-important?style=for-the-badge&logo=github" alt="Security Policy">
  </a>
</p>

<br>

<p align="center">
  <em>🧬 SDG Classification · 📊 Journal Metrics · 👤 ORCID Profile · 📈 Trend Analysis · 🎯 Policy Recommendations</em>
</p>

---

## 📖 Tentang

**Sangia APIs** adalah *pure analysis engine* yang menjadi jantung komputasi ekosistem **Sangia Publishing**. Berbeda dari aplikasi monolitik, engine ini **tidak menyimpan data apapun** — semua analisis dilakukan secara *on‑the‑fly* berdasarkan data yang dikirim oleh **Sangia Sikola** (frontend). Hasilnya: arsitektur yang ringan, mudah di-scale, dan bebas *caching artifact*.

> **Base URL**: `https://api.sangia.org`  
> **Versi API**: `v1`  
> **Dokumentasi Lengkap**: [docs/API.md](https://github.com/mokesano/sangia-apis/blob/master/docs/API.md)

---

## 🧠 Mengapa Sangia APIs?

| Masalah | Solusi |
| :--- | :--- |
| Anda butuh klasifikasi SDG untuk ribuan artikel ilmiah. | POST `/api/v1/sdg/v5/classify` — klasifikasi multilevel dengan bobot dinamis. |
| Anda ingin menghitung *impact score* jurnal secara otomatis. | POST `/api/v1/impact/calculate` — Sangia Impact Score siap pakai. |
| Anda perlu rekomendasi kebijakan berbasis data riset. | POST `/api/v1/recommendation/policy` — analisis *evidence‑based*. |
| Data peneliti dan publikasi sudah ada di database Anda. | Kirim sebagai `supplied_data` — engine tidak perlu cURL ke API eksternal. |
| Keamanan API tanpa session server. | HMAC‑SHA256 stateless authentication dengan TTL 1 tahun. |

---

## 🚀 Quick Start

### Prasyarat

| Perangkat Lunak | Versi |
| :--- | :--- |
| **PHP** | ≥ 8.1 |
| **Composer** | 2.x |
| **Web Server** | Apache / Nginx |

### Instalasi

```bash
git clone https://github.com/mokesano/sangia-apis.git
cd sangia-apis
cp .env.example .env
# ✏️ Edit .env — set SANGIA_SHARED_SECRET, dsb.
composer install
php -S localhost:8000 -t public/
```

### Uji Coba Endpoint

```bash
# Health check (publik)
curl https://api.sangia.org/health

# Katalog endpoint (publik)
curl https://api.sangia.org/api/v1

# Klasifikasi SDG (butuh API key)
curl -X POST https://api.sangia.org/api/v1/sdg/v5/classify \
  -H "X-API-Key: wz_42_1719000000_a3f8e2c1d5b7" \
  -H "Content-Type: application/json" \
  -d '{"title": "Solar Panel Adoption in Rural Java", "abstract": "..."}'
```

---

## 📡 Daftar Endpoint

| Method | Endpoint | Auth | Deskripsi |
| :--- | :--- | :--- | :--- |
| `GET` | `/health` | 🔓 Publik | Status layanan |
| `GET` | `/api/v1` | 🔓 Publik | Katalog endpoint |
| `GET` | `/api/v1/sdg/versions` | 🔓 Publik | Versi SDG + bobot default |
| `POST` | `/api/v1/sdg/{version}/classify` | 🔒 API Key | Klasifikasi SDG |
| `GET` | `/api/v1/scopus/author` | 🔒 API Key | Profil author Scopus |
| `GET` | `/api/v1/orcid/profile` | 🔒 API Key | Profil peneliti ORCID |
| `GET` | `/api/v1/citation/doi` | 🔒 API Key | Sitasi multi‑sumber |
| `GET` | `/api/v1/journal/metrics` | 🔒 API Key | Metrik jurnal Scopus |
| `GET` | `/api/v1/sinta/score` | 🔒 API Key | Skor jurnal SINTA |
| `POST` | `/api/v1/impact/calculate` | 🔒 API Key | Sangia Impact Score |
| `POST` | `/api/v1/trend/analyze` | 🔒 API Key | Trend analysis |
| `POST` | `/api/v1/recommendation/policy` | 🔒 API Key | Rekomendasi kebijakan |
| `POST` | `/api/v1/admin/keys/revoke` | 🔒 API Key | Cabut API key |

> 📘 Dokumentasi lengkap dengan contoh request/response: [docs/API.md](https://github.com/mokesano/sangia-apis/blob/master/docs/API.md)

---

## 🔐 Autentikasi

Gunakan **HMAC‑SHA256 stateless** — tidak ada session server.

```
Format: wz_{user_id}_{unix_timestamp}_{hmac16}
Contoh: wz_42_1719000000_a3f8e2c1d5b7
```

| Parameter | Deskripsi |
| :--- | :--- |
| `user_id` | ID pengguna dari Sangia Sikola |
| `unix_timestamp` | Timestamp saat key dibuat |
| `hmac16` | 16 karakter pertama dari `HMAC-SHA256(user_id:timestamp, SANGIA_SHARED_SECRET)` |
| **TTL** | 1 tahun sejak `timestamp` |

**Kirim key melalui:**
- Header: `X-API-Key: wz_...`
- Header: `Authorization: Bearer wz_...`
- Query: `?api_key=wz_...`

> ⚠️ **Rate Limit**: 60 request/60 detik per API key (dapat dikonfigurasi via `.env`).

---

## 🧩 Modul Analisis

| Modul | Direktori | Fungsi |
| :--- | :--- | :--- |
| 🧬 **SDG** | `core/Modules/SDG` | Klasifikasi SDG multiversi (v1–v5) dengan bobot dinamis |
| 📊 **Scopus** | `core/Modules/Scopus` | Profil author & metrik jurnal Scopus |
| 👤 **ORCID** | `core/Modules/ORCID` | Profil peneliti ORCID |
| 📄 **Citation** | `core/Modules/Citation` | Sitasi multi‑sumber (DOI‑based) |
| 🏫 **Sinta** | `core/Modules/Sinta` | Skor jurnal SINTA Kemdikbud |
| ⭐ **SangiaScore** | `core/Modules/SangiaScore` | Kalkulasi Sangia Impact Score |
| 📈 **Trend** | `core/Modules/Trend` | Analisis tren riset |
| 🎯 **Recommendation** | `core/Modules/Recommendation` | Rekomendasi kebijakan berbasis bukti |
| 📰 **Journal** | `core/Modules/Journal` | Metrik jurnal |

---

## 🔄 Pola `supplied_data`

Jika **Sangia Sikola** sudah memiliki data di database, kirimkan dalam request body. Engine akan menggunakan data tersebut **tanpa melakukan HTTP request ke API eksternal**, sehingga lebih cepat dan hemat *rate limit*.

```json
{
  "title": "Solar Panel Adoption in Rural Java",
  "abstract": "...",
  "supplied_works": [
    {
      "title": "Solar Panel Adoption in Rural Java",
      "doi": "10.1234/example",
      "publication_year": 2023,
      "type": "journal-article"
    }
  ],
  "supplied_scopus": {
    "h_index": 18,
    "total_documents": 120,
    "total_citations": 3400
  }
}
```

---

## ⚙️ Konfigurasi `.env`

```env
SANGIA_SHARED_SECRET=your-secret-key-here
DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=sangia_apis
DB_USER=root
DB_PASS=
RATE_LIMIT_MAX=60
RATE_LIMIT_WINDOW=60
SANGIA_ADMIN_USER_IDS=service-api,superadmin
```

---

## 🧪 Testing

```bash
# Unit tests
vendor/bin/phpunit

# Code style (PSR-12)
vendor/bin/phpcs --standard=PSR12 core/ src/

# Static analysis
vendor/bin/phpstan analyse --level max core/ src/

# Branding guard (legacy brand -> Sangia)
composer run check-branding
```

---

## 🤝 Kontribusi

Kami menyambut kontribusi! Lihat [CONTRIBUTING.md](https://github.com/mokesano/sangia-apis/blob/master/CONTRIBUTING.md) untuk panduan lengkap.

1. Fork repositori ini
2. Buat branch baru (`git checkout -b fitur-baru`)
3. Commit perubahan (`git commit -m 'Menambahkan fitur baru'`)
4. Push ke branch (`git push origin fitur-baru`)
5. Buat Pull Request

Proyek ini mengikuti [Contributor Covenant Code of Conduct](https://github.com/mokesano/sangia-apis/blob/master/CODE_OF_CONDUCT.md).

---

## 🔒 Keamanan

**Jangan umbar kerentanan secara publik.**

- **Pelaporan**: [security@sangia.org](mailto:security@sangia.org)
- **Respons**: Dalam 48 jam
- **Advisori**: [GitHub Security Advisories](https://github.com/mokesano/sangia-apis/security/advisories)

Detail lengkap: [SECURITY.md](https://github.com/mokesano/sangia-apis/blob/master/SECURITY.md)

---

## 📄 Lisensi

**MIT License** © 2025–2026 Rochmady. Lihat [LICENSE](https://github.com/mokesano/sangia-apis/blob/master/LICENSE) untuk teks lengkap.

| Izin | Ketentuan |
| :--- | :--- |
| ✅ Bebas digunakan (komersial & non‑komersial) | ⚠️ Tidak ada garansi |
| ✅ Bebas dimodifikasi & didistribusikan | ⚠️ Harus menyertakan hak cipta dan lisensi asli |

---

## 🙏 Kredit

| 🏷️ Atribusi | 🔗 Referensi |
| :--- | :--- |
| **Lead Developer** | [Rochmady (mokesano)](https://github.com/mokesano) |
| **Ekosistem** | [Sangia Publishing](https://github.com/sangiaorg) |
| **Dokumentasi API** | [docs/API.md](https://github.com/mokesano/sangia-apis/blob/master/docs/API.md) |

---

<p align="center">
  <br>
  <creator>Dibangun dengan ❤️ sebagai bagian dari ekosistem <strong>Sangia Publishing</strong></creator>
  <br><br>
  <a href="https://github.com/mokesano/sangia-apis/stargazers">
    <img src="https://img.shields.io/github/stars/mokesano/sangia-apis?style=social" alt="GitHub Stars">
  </a>
  <a href="https://github.com/mokesano/sangia-apis/network/members">
    <img src="https://img.shields.io/github/forks/mokesano/sangia-apis?style=social" alt="GitHub Forks">
  </a>
  <br><br>
  <credit>© 2025–2026 Rochmady. Dilisensikan di bawah MIT License.</credit>
</p>
