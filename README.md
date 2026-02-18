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
```bash
samudata/
├── api/ # Backend REST API
│ ├── services/ # Service layer
│ │ └── auth_lib.php # Authentication library
│ ├── budidaya_fetch.php # Fetch data budidaya
│ ├── budidaya_fetch_all.php # Fetch all budidaya data
│ ├── change_password.php # Change password endpoint
│ ├── check_session.php # Session validation
│ ├── dashboard_totals.php # Dashboard statistics
│ ├── db.php # Database connection (PDO)
│ ├── download_file.php # File download handler
│ ├── download_template.php # Template download
│ ├── ekspor_fetch.php # Fetch data ekspor
│ ├── ekspor_fetch_all.php # Fetch all ekspor data
│ ├── file_manager_api.php # File CRUD operations
│ ├── files.php # File listing
│ ├── get_user_profile.php # User profile endpoint
│ ├── investasi_fetch.php # Fetch data investasi
│ ├── kpp_fetch.php # Fetch data KPP garam
│ ├── landing_stats.php # Landing page statistics
│ ├── login_api.php # Login endpoint
│ ├── pengolahan_pemasaran_*.php # Fetch data pengolahan
│ ├── register.php # Registration endpoint
│ ├── save_rows.php # Save data rows
│ ├── tangkap_fetch.php # Fetch data tangkap
│ └── tangkap_fetch_all.php # Fetch all tangkap data
│
├── uploads/ # Uploaded Excel files
├── logs/ # Application logs
│ └── php_errors.log
│
├── database_schema.sql # Database migration
├── database_config.php # Database configuration
├── db.php # Database connection
│
├── index.php # Main entry point
├── login.php # Login page
├── logout.php # Logout handler
├── register.php # Registration page
│
├── dashboard.php # Dashboard page
├── file-manager.php # File manager UI
├── pengaturan-akun.php # Account settings
│
├── perikanan-tangkap.php # Statistik tangkap
├── perikanan-budidaya.php # Statistik budidaya
├── kpp.php # Statistik KPP garam
├── pengolahan-pemasaran.php # Statistik pengolahan
├── investasi.php # Statistik investasi
├── ekspor-perikanan.php # Statistik ekspor
│
├── download_template.php # Template downloader
├── files.php # File listing page
├── map.html # Interactive map
│
├── protected_template.php # Protected page template
├── default.php # Default page template
│
├── Dockerfile.txt # Docker configuration
├── server.log # Server logs
│
├── README.md # Dokumentasi utama
├── README_DATABASE.md # Database documentation
├── README_DEPLOYMENT.md # Deployment guide
├── DEPLOYMENT_GUIDE.md # Deployment instructions
├── INSTALLATION_GUIDE.md # Installation guide
├── PANDUAN_DEPLOYMENT... # Panduan deployment (ID)
│
└── samudata-project dkpjati... # Project archive
```
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
