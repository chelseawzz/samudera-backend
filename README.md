# SAMUDERA  
## Sistem Analisis dan Monitoring Data Perikanan dan Kelautan Provinsi Jawa Timur  

**Slogan:** *"Lautnya Luas, Datanya Jelas – SAMUDERA, Solusi Cerdas."*

---

## 📋 Deskripsi

SAMUDERA adalah sistem berbasis web untuk analisis dan monitoring data statistik kelautan dan perikanan di Dinas Kelautan dan Perikanan (DKP) Provinsi Jawa Timur.  
Sistem mengintegrasikan data dari 5 bidang statistik:

- Perikanan Tangkap  
- Perikanan Budidaya  
- KPP Garam  
- Pengolahan & Pemasaran  
- Ekspor Perikanan  

Periode data: **2020–2024**

---

## ✨ Fitur Utama

### 🔐 Autentikasi & Keamanan
- Login admin dengan session management
- Role-based access control (Admin vs User Umum)
- Audit log aktivitas di `fm_actions`

### 📁 File Manager
- Upload file Excel dengan validasi otomatis
- Template Excel per komponen untuk konsistensi header
- Delete file dengan cascade ke database
- SHA1 hash untuk deteksi duplikasi

### 📊 Data Import & Validasi
- Import Excel otomatis menggunakan PhpSpreadsheet
- Auto-detect tipe data berdasarkan struktur header
- Transaction support untuk menjaga integritas data
- Validasi header terhadap template database

### 📡 REST API Endpoints

| Endpoint | Method | Fungsi |
|-----------|--------|--------|
| `/api/login.php` | POST | Autentikasi admin |
| `/api/logout.php` | POST | Logout session |
| `/api/file_manager_api.php` | GET/POST | CRUD file |
| `/api/upload_handler.php` | POST | Upload & import Excel |
| `/api/tangkap_fetch.php` | GET | Fetch data tangkap |
| `/api/budidaya_fetch.php` | GET | Fetch data budidaya |
| `/api/kpp_fetch.php` | GET | Fetch data KPP |
| `/api/pengolahan_pemasaran_fetch.php` | GET | Fetch data pengolahan |
| `/api/ekspor_fetch.php` | GET | Fetch data ekspor |
| `/api/landing_stats.php` | GET | Statistik landing page |

---

## 🛠️ Teknologi

| Komponen | Teknologi | Versi |
|-----------|------------|-------|
| Language | PHP | 8.2.x |
| Database | MySQL | 8.0.x |
| Web Server | Apache | 2.4.x |
| Excel Library | PhpSpreadsheet | 1.29.x |
| Authentication | PHP Sessions + PDO | - |

---

## 📁 Struktur Proyek

samudera/
├── api/
│ ├── services/
│ │ └── auth_lib.php
│ ├── budidaya_fetch.php
│ ├── change_password.php
│ ├── db.php
│ ├── file_manager_api.php
│ ├── login_api.php
│ ├── tangkap_fetch.php
│ └── ...
│
├── uploads/
├── logs/
│ └── php_errors.log
│
├── database_schema.sql
├── database_config.php
├── db.php
│
├── index.php
├── login.php
├── dashboard.php
├── file-manager.php
│
├── perikanan-tangkap.php
├── perikanan-budidaya.php
├── kpp.php
├── ekspor-perikanan.php
│
├── Dockerfile.txt
├── README.md
└── INSTALLATION_GUIDE.md



---

## ⚙️ Instalasi & Deployment

### Quick Start
1. Clone repository dari GitHub
2. Install PHP 8.1+ dan MySQL
3. Import `database_schema.sql`
4. Edit `database_config.php`
5. Set permission folder `uploads/`
6. Jalankan melalui Apache / XAMPP

### Production Deployment
- Gunakan HTTPS
- Setup backup database rutin
- Monitor penggunaan storage
- Aktifkan logging dan keamanan server

---

## 📊 Prinsip No Dummy Data
- Statistik menampilkan 0 jika belum ada data
- Visualisasi hanya dari data real database
- State kosong memberi panduan pengguna

---

## 🔒 Keamanan File
- File disimpan di direktori privat
- Akses melalui controller terproteksi
- Validasi format file ketat
- Sanitasi nama file
- Checksum untuk deteksi duplikat

---

## 💻 System Requirements
- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx)
- Minimum 512MB RAM
- Minimum 1GB disk space

### PHP Extensions
- php-mysql  
- php-curl  
- php-json  
- php-mbstring  
- php-fileinfo  

---

## 📞 Support
Untuk bantuan teknis, hubungi tim IT DKP Jawa Timur.

---

**SAMUDERA**  
*Lautnya Luas, Datanya Jelas – SAMUDERA, Solusi Cerdas.*
