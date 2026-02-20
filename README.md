# SAMUDERA
## Sistem Analisis dan Monitoring Data Perikanan dan Kelautan Provinsi Jawa Timur  

**Slogan:** *"Lautnya Luas, Datanya Jelas – SAMUDERA, Solusi Cerdas."*  

---

## Deskripsi
Frontend aplikasi **SAMUDERA** dibangun menggunakan **React.js + TypeScript** dengan antarmuka modern dan interaktif untuk visualisasi data statistik kelautan dan perikanan di Dinas Kelautan dan Perikanan (DKP) Provinsi Jawa Timur.

---

## Fitur Utama

### Landing Page
- 6 kartu statistik real-time (Tangkap, Budidaya, KPP, Pengolahan, Ekspor, Investasi)  
- Navigasi langsung ke halaman detail per bidang  
- Animasi wave background dan desain modern  

### Dashboard
- Ringkasan produksi per bidang dengan filter tahun (2020–2024)  
- Grafik interaktif (Bar, Line, Pie) menggunakan Recharts  
- Tabel data per kabupaten/kota dengan sorting & pagination  
- Export data ke Excel  

### Data Statistik per Bidang
- **Perikanan Tangkap:** Nelayan, Armada, Volume, Nilai, Komoditas  
- **Perikanan Budidaya:** Volume, Nilai, Pembudidaya, Luas Area, Ikan Hias  
- **KPP Garam:** Luas Lahan, Kelompok, Petambak, Volume Produksi  
- **Pengolahan & Pemasaran:** AKI, Pemasaran, Olahan per Kab/Kota  
- **Ekspor Perikanan:** Total Ekspor, Komoditas Utama, Negara Tujuan  

### Peta Interaktif (JatimMap)
- GeoJSON batas wilayah 38 kabupaten/kota Jawa Timur  
- Color coding berdasarkan nilai produksi  
- Tooltip detail saat hover  
- Filter tahun & jenis perikanan  

### File Manager (Admin Only)
- Upload file Excel dengan validasi otomatis  
- Template Excel per komponen  
- Delete file dengan cascade ke database  
- Audit log aktivitas  

### Autentikasi & Keamanan
- Login khusus admin dengan session management  
- Role-based access control  
- Protected routes untuk halaman admin  

### Pengaturan Akun (Admin)
- Edit profil: Nama, Email, Telepon  
- Ubah password dengan validasi kompleksitas  

---

## 🛠️ Teknologi

| Komponen | Teknologi | Versi |
|-----------|------------|-------|
| Framework | React.js + TypeScript | 18.x |
| Styling | Tailwind CSS | 3.x |
| Charts | Recharts | 2.x |
| Maps | Leaflet | 1.9.x |
| Icons | Lucide React | Latest |
| Build Tool | Vite | 4.x |
| HTTP Client | Fetch API | - |
| State Management | React Hooks | - |

### Node.js Requirements
- Node.js 18+  
- npm 9+ atau yarn 1.22+  

---

## 📁 Struktur Proyek

```bash
samudera-frontend/
├── public/
│   ├── jatim_kabkota_geojson.json
│   ├── bg5.jpg, bg2.jpg
│   ├── logo.png
│   └── favicon.ico
│
├── src/
│   ├── pages/
│   │   ├── LandingPage.tsx
│   │   ├── Dashboard.tsx
│   │   ├── Login.tsx
│   │   ├── FileManager.tsx
│   │   ├── PengaturanAkun.tsx
│   │   └── DataStatistik/
│   │       ├── PerikananTangkap.tsx
│   │       ├── PerikananBudidaya.tsx
│   │       ├── KPP.tsx
│   │       ├── PengolahanPemasaran.tsx
│   │       ├── EksporPerikanan.tsx
│   │       └── types.ts
│   │
│   ├── components/
│   │   ├── Header.tsx
│   │   ├── Footer.tsx
│   │   ├── DashboardHeader.tsx
│   │   ├── StatCard.tsx
│   │   ├── StatPortraitCard.tsx
│   │   └── JatimMap.tsx
│   │
│   ├── App.tsx
│   ├── main.tsx
│   ├── index.css
│   └── vite-env.d.ts
│
├── package.json
├── vite.config.ts
├── tsconfig.json
├── tailwind.config.js
├── index.html
└── README.md
```

Frontend repo:  
https://github.com/chelseawzz/samudata_frontend

---

# PANDUAN SETUP LOKAL

Ikuti langkah ini agar SAMUDERA bisa jalan di komputer lokal.

---

# 📌 1. Requirement

Install dulu:

- XAMPP (Apache + MySQL + PHP 8+)
- Git
- Node.js 18+ (untuk frontend)

Download:
- https://apachefriends.org
- https://nodejs.org

Cek instalasi:

```bash
php -v
mysql --version
node -v
npm -v
```
Pastikan node.js yang digunakan versi terbaru v24.

# 2. Install Backend

1. git clone https://github.com/chelseawzz/samudata.git
2. Pindahkan ke Folder XAMPP: C:\xampp\htdocs\samudata
3. Backend bisa diakses di: http://localhost/samudata

# 3. Setup Database

1. Buka phpMyAdmin: http://localhost/phpmyadmin
2. Buat Database Baru: samudata_db (Import File Sql yang sudah ada)

# 4. Jalankan Backend
1. Buka XAMPP
2. Start Apache
3. Start MySQL (Jika Gagal dibuka, maka kemungkinan port 3306 sedang dipakai)

## 🔗 Cara Menyambungkan Backend ke Frontend (Pakai **pnpm**)

Ikuti langkah ini setelah **backend** dan **frontend** sudah berhasil di-install.

---

## 1. Buka Project di VSCode Dulu

1. Buka **VSCode**
2. Klik **File → Open Folder**
3. Pilih folder project:
   - `samudata_frontend`
   - `samudata_backend`

Bisa buka dua VSCode window, atau satu workspace.

---

##  2. Jalankan Backend

1. Buka terminal di folder `samudata_backend`

```bash
pnpm install
pnpm run dev
```  

Kalau pakai database lokal:

1. Jalankan XAMPP
2. Start Apache dan MySQL
3. Pastikan backend jalan di misalnya: http://localhost:8000 (Sesuai Port yang digunakan)
4. Jalankan Frontend
   Di terminal VsCode folder samudata_frontend:
    * pnpm run dev
Atau jika Menggunakan cmd, maka masuk ke folder tampat penyimpanan misalnya C:/ Samudata_frotnend dan jalankan pnpm run dev.


