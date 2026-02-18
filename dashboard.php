<?php
require_once __DIR__.'/auth_guard.php';
require_login();

$username = $_SESSION['username'] ?? 'User';
$userType = $_SESSION['userType'] ?? 'user';

$selectedYear = (isset($_GET['tahun']) && ctype_digit($_GET['tahun'])) ? (int)$_GET['tahun'] : null;
$allowedBidang = ['tangkap','budidaya','kpp','pengolahan','ekspor','investasi'];
$selectedBidang = isset($_GET['bidang']) && in_array($_GET['bidang'], $allowedBidang, true) ? $_GET['bidang'] : null;

// Tahun untuk KPI (kalau tidak pilih → tahun berjalan)
$kpiYear = $selectedYear ?: (int)date('Y');

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard - Samudera</title>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<style>
:root{
  --navy-1:#0b1b2b; --navy-2:#0e2740;
  --toska-1:#00a2b2; --toska-2:#11c9df;
  --line:#e6eef6;
  --samudera-bg:#0b1b2b; --samudera-bg2:#0e2740;
}

/* HEADER */
.topbar{
  background:linear-gradient(180deg,var(--samudera-bg),var(--samudera-bg2));
  color:#fff;
  box-shadow:0 2px 10px rgba(0,0,0,.25)
}
.nav-item{
  display:inline-flex;
  align-items:center;
  gap:.45rem;
  padding:.40rem .80rem;
  border-radius:.40rem;
  color:#fff;
  opacity:.92;
  transition:.2s
}
.nav-item:hover{opacity:1;background:rgba(255,255,255,.12)}
.nav-item.active{background:rgba(255,255,255,.12)}
.dropdown{position:relative}
.dropdown-menu{
  position:absolute;
  top:100%;left:0;
  min-width:240px;
  background:#0f1f34;
  border-radius:.6rem;
  padding:.35rem;
  display:none;
  box-shadow:0 10px 30px rgba(0,0,0,.3);
  z-index:60
}
.dropdown-menu a,.dropdown-menu button{
  display:flex;
  align-items:center;
  gap:.55rem;
  color:#fff;
  opacity:.9;
  padding:.55rem .75rem;
  border-radius:.45rem
}
.dropdown-menu a i,.dropdown-menu button i{width:1.1rem;text-align:center}
.dropdown-menu a:hover,.dropdown-menu button:hover{
  background:rgba(255,255,255,.12);
  opacity:1
}
.show{display:block}
.logo-wrap{
  background:rgba(255,255,255,.92);
  border-radius:9999px;
  padding:4px;
  box-shadow:0 0 0 2px rgba(255,255,255,.55),0 3px 8px rgba(0,0,0,.25)
}
.logo-img{
  width:36px;height:36px;
  border-radius:9999px;
  display:block;
  filter:drop-shadow(0 0 1px #fff) drop-shadow(0 0 6px rgba(255,255,255,.35))
}

/* BODY */
body{background:#f8fafc;color:#0f172a;}
.hero{
  background:linear-gradient(90deg,var(--toska-1),var(--toska-2));
  border-radius:16px;
  color:#fff;
  box-shadow:0 10px 28px rgba(0,0,0,.15);
}
.launcher{
  display:grid;
  grid-template-columns:repeat(6,minmax(0,1fr));
  gap:1rem;
}
.launcher a{
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  border-radius:16px;
  padding:14px;
  background:rgba(255,255,255,.15);
  transition:.2s;
  color:#fff;
}
.launcher a:hover{
  transform:translateY(-3px);
  background:rgba(255,255,255,.25);
}
@media(max-width:1024px){.launcher{grid-template-columns:repeat(3,minmax(0,1fr));}}
.card{
  border-radius:18px;
  padding:18px;
  background:#fff;
  box-shadow:0 12px 28px rgba(2,8,20,.05);
}
#mapJatim{height:480px;border-radius:12px;}
.dashboard-frame{width:100%;border:0;display:block;background:#fff;border-radius:12px;min-height:620px;}
.frame-wrap{position:relative;}
.frame-skel{
  position:absolute;inset:0;
  display:flex;align-items:center;justify-content:center;
  background:linear-gradient(180deg,#fff,#f7fbfe);
  border:1px dashed #dce7f2;
  border-radius:12px;
}
.hidden{display:none;}

/* CONTROLS soft */
.controls-soft{
  background:linear-gradient(180deg,rgba(255,255,255,.25),rgba(255,255,255,.15));
  padding:.35rem .5rem;
  border-radius:14px;
  box-shadow:inset 0 0 0 1px rgba(255,255,255,.2)
}
.input-tahun{
  height:36px; min-width:120px;
  padding:.40rem .70rem;
  font-size:.95rem; line-height:1;
  text-align:center;
  border-radius:12px;
  color:#0b1b2b;
  caret-color:var(--toska-2);
  font-weight:600; letter-spacing:.3px;
  background:
    linear-gradient(180deg,#e9fbff 0%,#ffffff 60%) padding-box,
    linear-gradient(135deg,var(--toska-1),var(--toska-2)) border-box;
  border:2px solid transparent;
  box-shadow:0 6px 18px rgba(17,201,223,.18);
  transition:box-shadow .18s ease, transform .18s ease;
}
.input-tahun::placeholder{ color:#0ea5b7; opacity:.95; }
.input-tahun:focus{
  outline:none;
  box-shadow:0 0 0 3px rgba(17,201,223,.35), 0 10px 26px rgba(2,8,20,.10);
  transform:translateY(-1px);
}
.input-tahun::-webkit-outer-spin-button,
.input-tahun::-webkit-inner-spin-button{
  -webkit-appearance:none;margin:0;
}
.input-tahun[type=number]{ -moz-appearance:textfield; }

.select-wrap{ position:relative; display:inline-block; }
.select-bidang{
  min-width:260px; height:36px;
  padding:0 .70rem; padding-right:2rem;
  font-size:.92rem;
  border-radius:12px;
  appearance:none; -webkit-appearance:none; -moz-appearance:none;
  color:#0f172a;
  background:
    linear-gradient(180deg,#eefcff 0%,#ffffff 65%) padding-box,
    linear-gradient(135deg,var(--toska-1),var(--toska-2)) border-box;
  border:2px solid transparent;
  box-shadow:0 6px 18px rgba(17,201,223,.18);
  transition: box-shadow .18s ease, transform .18s ease, background .18s ease;
}
.select-bidang:hover{
  transform:translateY(-1px);
  box-shadow:0 8px 22px rgba(17,201,223,.22);
}
.select-bidang:focus{
  outline:none;
  box-shadow:0 0 0 3px rgba(17,201,223,.35), 0 10px 26px rgba(2,8,20,.10);
}
.select-wrap .chev{
  position:absolute;
  right:.65rem; top:50%; transform:translateY(-50%);
  pointer-events:none; font-size:.85rem;
  color:var(--toska-2); opacity:.95;
  text-shadow:0 2px 6px rgba(17,201,223,.35);
}
.btn-main{
  background:linear-gradient(135deg,#004e58,#00727f);
  color:#fff; border-radius:12px;
  padding:.55rem 1rem; font-weight:700;
  box-shadow:0 8px 20px rgba(0,114,127,.25);
  transition:.18s ease;
}
.btn-main:hover{
  transform:translateY(-1px);
  box-shadow:0 10px 26px rgba(0,114,127,.32);
}
.btn-secondary{
  background:#eef5f7; color:#0f172a;
  border-radius:12px; padding:.55rem 1rem; font-weight:700;
  border:1px solid #d7eef2;
}
.dropdown-menu a{font-size:.9rem;padding:.45rem .6rem;}

/* ========= KPI CARDS ========= */
.kpi-wrap{ margin-top:16px; }
.kpi-row{
  display:grid;
  grid-template-columns: repeat(6, minmax(0,1fr));
  gap:16px;
}
@media (max-width:1280px){ .kpi-row{ grid-template-columns: repeat(4,minmax(0,1fr)); } }
@media (max-width:1024px){ .kpi-row{ grid-template-columns: repeat(3,minmax(0,1fr)); } }
@media (max-width:768px) { .kpi-row{ grid-template-columns: repeat(2,minmax(0,1fr)); } }
@media (max-width:480px) { .kpi-row{ grid-template-columns: 1fr; } }

/* mode 1 kartu saja saat filter bidang */
.kpi-row.single-one{
  grid-template-columns:1fr;
}

.kpi-card{
  position:relative;
  background:linear-gradient(180deg,#ffffff,#f3f9ff);
  border:1px solid #dcecf7;
  border-radius:14px;
  padding:14px 14px 16px;
  box-shadow:0 10px 24px rgba(2,8,20,.06);
}
.kpi-card .top-accent{
  position:absolute; left:8px; right:8px; top:8px; height:6px;
  border-radius:6px;
  background:linear-gradient(90deg,#06b6d4,#22d3ee);
}
.kpi-headline{ margin-top:10px; display:flex; align-items:center; gap:8px; }
.kpi-icon{
  width:28px; height:28px; border-radius:8px;
  display:inline-flex; align-items:center; justify-content:center;
  background:#e9fbff; color:#0ea5b7; border:1px solid #bff1fb;
  font-size:14px;
}
.kpi-title{ font-size:13px; line-height:1.15; font-weight:700; color:#073043; }
.kpi-sub{ font-size:11px; color:#5f7d92; margin-top:2px; }

.kpi-value{
  margin-top:10px;
  font-weight:900;
  color:#083a45;
  line-height:1.1;
  font-size:32px;
  white-space:nowrap;
  overflow:hidden;
}
.kpi-value > span{
  display:inline-block;
}

.kpi--tangkap .kpi-icon{ background:#e9f7ff; color:#0ea5b7; border-color:#b6e9f7; }
.kpi--budidaya .kpi-icon{ background:#ecfff1; color:#16a34a; border-color:#c4f3d3; }
.kpi--kpp     .kpi-icon{ background:#fff7ec; color:#f59e0b; border-color:#ffe1a3; }
.kpi--olah    .kpi-icon{ background:#fff0f7; color:#e879f9; border-color:#ffd9ef; }
.kpi--ekspor  .kpi-icon{ background:#f1f6ff; color:#6366f1; border-color:#d8e4ff; }
.kpi--invest  .kpi-icon{ background:#fff6e6; color:#f59e0b; border-color:#ffe2b8; }
</style>
</head>
<body>

<header class="topbar w-full">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <div class="flex items-center">
      <div class="bg-white/90 rounded-full p-1.5 shadow-md flex items-center justify-center">
        <span class="logo-wrap">
          <img src="images/logo.png" alt="Logo Samudera" class="logo-img" onerror="this.style.display='none'">
        </span>
      </div>
      <span class="ml-2 font-bold text-white">SAMUDERA</span>
    </div>

    <nav class="flex items-center gap-4 text-sm" id="topnav">
      <a href="/samudata/dashboard.php" class="nav-item active"><i class="fa-solid fa-globe"></i> Dashboard</a>
      <a href="/samudata/files.php" class="nav-item"><i class="fa-solid fa-diagram-project"></i> File Manager</a>

      <div class="dropdown">
        <button class="nav-item" id="btnBidang">
          <i class="fa-solid fa-layer-group"></i> Bidang
          <i class="fa-solid fa-chevron-down text-xs opacity-80"></i>
        </button>
        <div class="dropdown-menu" id="menuBidang">
          <a href="/samudata/perikanan-tangkap.php"><i class="fa-solid fa-fish"></i> Perikanan Tangkap</a>
          <a href="/samudata/perikanan-budidaya.php"><i class="fa-solid fa-water"></i> Perikanan Budidaya</a>
          <a href="/samudata/kpp.php"><i class="fa-solid fa-mound"></i> KPP (Garam)</a>
          <a href="/samudata/pengolahan-pemasaran.php"><i class="fa-solid fa-box-open"></i> Pengolahan &amp; Pemasaran</a>
          <a href="/samudata/ekspor-perikanan.php"><i class="fa-solid fa-truck-plane"></i> Ekspor Perikanan</a>
          <a href="/samudata/investasi.php"><i class="fa-solid fa-sack-dollar"></i> Investasi KP</a>
        </div>
      </div>

      <div class="dropdown">
        <button class="nav-item" id="btnAkun">
          <i class="fa-solid fa-user"></i> Akun
          <i class="fa-solid fa-chevron-down text-xs opacity-80"></i>
        </button>
        <div class="dropdown-menu" id="menuAkun" style="right:0;left:auto;">
          <a href="pengaturan-akun.php"><i class="fa-solid fa-user-gear"></i> Pengaturan Akun</a>
          <form action="logout.php" method="post" style="margin:0;">
            <button type="submit" class="w-full text-left px-3 py-2 rounded hover:bg-white/10">
              <i class="fa-solid fa-right-from-bracket"></i> Logout
            </button>
          </form>
        </div>
      </div>
    </nav>
  </div>
</header>

<script>
const btnBidang=document.getElementById('btnBidang');
const menuBidang=document.getElementById('menuBidang');
const btnAkun=document.getElementById('btnAkun');
const menuAkun=document.getElementById('menuAkun');
function closeAll(){menuBidang?.classList.remove('show');menuAkun?.classList.remove('show');}
btnBidang?.addEventListener('click',e=>{e.stopPropagation();menuBidang.classList.toggle('show');menuAkun?.classList.remove('show');});
btnAkun?.addEventListener('click',e=>{e.stopPropagation();menuAkun.classList.toggle('show');menuBidang?.classList.remove('show');});
document.addEventListener('click',closeAll);
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeAll(); });
</script>

<section class="max-w-7xl mx-auto px-4 mt-8">
  <div class="hero p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold">Selamat datang di Dashboard Samudera</h2>
        <p class="text-white/90">“Lautnya luas, Datanya Jelas, Samudera Solusi Cerdas”</p>
      </div>

      <div class="controls-soft flex items-center gap-2">
        <input id="tahun" type="number" class="input-tahun"
               placeholder="Tahun" min="2000" max="2100" aria-label="Tahun"
               value="<?= $selectedYear ? e($selectedYear) : '' ?>">

        <div class="select-wrap" aria-label="Pilih Bidang">
          <select id="bidang" class="select-bidang">
            <option value="">– Pilih Bidang –</option>
            <option value="tangkap"   <?= $selectedBidang==='tangkap'?'selected':''; ?>>Perikanan Tangkap</option>
            <option value="budidaya"  <?= $selectedBidang==='budidaya'?'selected':''; ?>>Perikanan Budidaya</option>
            <option value="kpp"       <?= $selectedBidang==='kpp'?'selected':''; ?>>KPP (Garam)</option>
            <option value="pengolahan"<?= $selectedBidang==='pengolahan'?'selected':''; ?>>Pengolahan &amp; Pemasaran</option>
            <option value="ekspor"    <?= $selectedBidang==='ekspor'?'selected':''; ?>>Ekspor Perikanan</option>
            <option value="investasi" <?= $selectedBidang==='investasi'?'selected':''; ?>>Investasi KP</option>
          </select>
          <i class="fa-solid fa-chevron-down chev"></i>
        </div>

        <button id="applyYear" class="btn-main">Terapkan</button>
        <button id="resetYear" class="btn-secondary <?= $selectedYear ? '' : 'hidden' ?>">Reset</button>
      </div>
    </div>

    <div class="launcher mt-6">
      <a href="perikanan-tangkap.php">
        <i class="fa-solid fa-fish text-2xl mb-2"></i>
        <span>Tangkap</span>
      </a>
      <a href="perikanan-budidaya.php">
        <i class="fa-solid fa-water text-2xl mb-2"></i>
        <span>Budidaya</span>
      </a>
      <a href="kpp.php">
        <i class="fa-solid fa-mound text-2xl mb-2"></i>
        <span>KPP (Garam)</span>
      </a>
      <a href="pengolahan-pemasaran.php">
        <i class="fa-solid fa-box-open text-2xl mb-2"></i>
        <span>Pengolahan</span>
      </a>
      <a href="ekspor-perikanan.php">
        <i class="fa-solid fa-truck-plane text-2xl mb-2"></i>
        <span>Ekspor</span>
      </a>
      <a href="investasi.php">
        <i class="fa-solid fa-sack-dollar text-2xl mb-2"></i>
        <span>Investasi</span>
      </a>
    </div>
  </div>
</section>

<!-- ========= KPI SECTION ========= -->
<section class="max-w-7xl mx-auto px-4 kpi-wrap">
  <div class="kpi-row" id="kpiRow">
    <div class="kpi-card kpi--tangkap" data-bidang="tangkap">
      <div class="top-accent"></div>
      <div class="kpi-headline">
        <span class="kpi-icon"><i class="fa-solid fa-fish"></i></span>
        <div>
          <div class="kpi-title">Produksi Perikanan Tangkap</div>
          <div class="kpi-sub">Tahun <?= e($kpiYear) ?></div>
        </div>
      </div>
      <div class="kpi-value"><span id="kpiTangkap">0</span></div>
      <strong class="kpi-unit">Ton</strong>
    </div>

    <div class="kpi-card kpi--budidaya" data-bidang="budidaya">
      <div class="top-accent"></div>
      <div class="kpi-headline">
        <span class="kpi-icon"><i class="fa-solid fa-water"></i></span>
        <div>
          <div class="kpi-title">Produksi Perikanan Budidaya</div>
          <div class="kpi-sub">Tahun <?= e($kpiYear) ?></div>
        </div>
      </div>
      <div class="kpi-value"><span id="kpiBudidaya">0</span></div>
      <strong class="kpi-unit">Ton</strong>
    </div>

    <div class="kpi-card kpi--kpp" data-bidang="kpp">
      <div class="top-accent"></div>
      <div class="kpi-headline">
        <span class="kpi-icon"><i class="fa-solid fa-mound"></i></span>
        <div>
          <div class="kpi-title">Produksi Garam</div>
          <div class="kpi-sub">Tahun <?= e($kpiYear) ?></div>
        </div>
      </div>
      <div class="kpi-value"><span id="kpiKpp">0</span></div>
      <strong class="kpi-unit">Ton</strong>
    </div>

    <div class="kpi-card kpi--olah" data-bidang="pengolahan">
      <div class="top-accent"></div>
      <div class="kpi-headline">
        <span class="kpi-icon"><i class="fa-solid fa-box-open"></i></span>
        <div>
          <div class="kpi-title">Pengolahan Produk KP</div>
          <div class="kpi-sub">Tahun <?= e($kpiYear) ?></div>
        </div>
      </div>
      <div class="kpi-value"><span id="kpiOlahan">0</span></div>
      <strong class="kpi-unit">Unit</strong>
    </div>

    <div class="kpi-card kpi--ekspor" data-bidang="ekspor">
      <div class="top-accent"></div>
      <div class="kpi-headline">
        <span class="kpi-icon"><i class="fa-solid fa-truck-plane"></i></span>
        <div>
          <div class="kpi-title">Ekspor Perikanan</div>
          <div class="kpi-sub">Tahun <?= e($kpiYear) ?></div>
        </div>
      </div>
      <div class="kpi-value"><span id="kpiEkspor">0</span></div>
      <strong class="kpi-unit">Ton</strong>
    </div>

    <div class="kpi-card kpi--invest" data-bidang="investasi">
      <div class="top-accent"></div>
      <div class="kpi-headline">
        <span class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></span>
        <div>
          <div class="kpi-title">Investasi Kelautan &amp; Perikanan</div>
          <div class="kpi-sub">Tahun <?= e($kpiYear) ?></div>
        </div>
      </div>
      <div class="kpi-value"><span id="kpiInvestasi">0</span></div>
      <strong class="kpi-unit">Juta</strong>
    </div>
  </div>
</section>
<!-- ========= /KPI SECTION ========= -->

<main class="max-w-7xl mx-auto px-4 py-8">
<?php if (!$selectedYear): ?>
  <div class="card mb-8">
    <h3 class="text-lg font-semibold mb-2">Peta Wilayah Kabupaten/Kota Jawa Timur</h3>
    <p class="text-sm text-gray-600 mb-3">Klik marker untuk melihat nama kabupaten/kota.</p>
    <div id="mapJatim"></div>
  </div>
<?php elseif($selectedYear && !$selectedBidang): ?>
  <div class="card mb-8">
    <h3 class="text-lg font-semibold mb-2">Filter belum lengkap</h3>
    <p class="text-sm text-gray-600">Silakan pilih <strong>Bidang</strong> terlebih dahulu untuk menampilkan tabel tahun <?= e($selectedYear) ?>.</p>
  </div>
<?php else: ?>
  <div class="frame-wrap">
    <div id="frameSkel" class="frame-skel"><span class="text-sm text-slate-500">Memuat tabel…</span></div>
    <iframe class="dashboard-frame" id="frameDash" src=""></iframe>
  </div>
<?php endif; ?>
</main>

<!-- FOOTER -->
<style>
  .smd-footer{
    background:linear-gradient(180deg,#0e2740,#081626);
    color:#cdd7e2;
    margin-top:24px;
    font-size:.82rem;
    line-height:1.5;
  }
  .smd-footer .wrap{max-width:72rem;margin:0 auto;padding:1.25rem 1rem}
  .smd-footer h4{color:#f1f5f9;font-weight:700;margin:0 0 .4rem 0;font-size:.9rem;}
  .smd-footer p{color:#b8c3d2;margin:0}
  .smd-footer ul{margin:.25rem 0 0 0;padding-left:1rem;list-style:disc}
  .smd-footer li{margin:.15rem 0;color:#aebed0;line-height:1.4}
  .smd-footer a{color:#bcd7f0;text-decoration:none}
  .smd-footer a:hover{color:#fff;text-decoration:underline}
  .smd-footer .grid{display:grid;gap:1.25rem}
  @media (min-width:768px){ .smd-footer .grid{grid-template-columns:2fr 1.3fr 1.2fr} }
  .smd-footer .bottom{border-top:1px solid rgba(255,255,255,.06);margin-top:1rem;padding-top:.55rem;color:#95a3b8;font-size:.75rem;text-align:center;}
</style>

<footer class="smd-footer">
  <div class="wrap">
    <div class="grid">
      <section>
        <h4>Samudera</h4>
        <p>
          Sistem Manajemen Data Kelautan dan Perikanan Provinsi Jawa Timur.
          Mendukung ingest Excel, validasi, penyimpanan terstruktur per-bidang,
          dan ringkasan tahunan untuk analisis serta pelaporan.
        </p>
      </section>
      <section>
        <h4>Fitur</h4>
        <ul>
          <li>Unggah Excel multi-file &amp; deteksi template</li>
          <li>Normalisasi angka (ton, USD, dst.)</li>
          <li>Penyimpanan per-bidang (Tangkap, Budidaya, KPP, Pengolahan, Ekspor, Investasi)</li>
          <li>Dashboard per-bidang dengan filter tahun</li>
          <li>Pencarian &amp; filter file pada File Manager</li>
          <li>Kontrol akses berbasis peran</li>
        </ul>
      </section>

      <section>
        <h4>Kontak DKP Provinsi Jawa Timur</h4>
        <ul style="list-style:none;padding-left:0">
          <li><strong>Alamat:</strong> Jl. A. Yani 152 B, Surabaya 60231</li>
          <li><strong>Telepon:</strong> (031) 8281672</li>
          <li><strong>Email:</strong> <a href="mailto:diskanla@jatimprov.go.id">diskanla@jatimprov.go.id</a></li>
          <li><strong>Situs:</strong> <a href="https://dkp.jatimprov.go.id/" target="_blank" rel="noopener">dkp.jatimprov.go.id</a></li>
        </ul>
      </section>
    </div>

    <div class="bottom">© <?= date('Y') ?> Samudera — Dinas Kelautan dan Perikanan Provinsi Jawa Timur.</div>
  </div>
</footer>

<?php if (!$selectedYear): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  // Map fokus ke Jawa Timur
  var map=L.map('mapJatim',{zoomControl:true}).setView([-7.7,112.5],7);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
    maxZoom:18
  }).addTo(map);

  // ±32 titik kab/kota di Jawa Timur
  var points=[
    ["Surabaya",-7.2575,112.7521],
    ["Sidoarjo",-7.4469,112.7170],
    ["Gresik",-7.1568,112.6555],
    ["Lamongan",-7.1167,112.4167],
    ["Tuban",-6.9000,112.0500],
    ["Bojonegoro",-7.1502,111.8817],
    ["Nganjuk",-7.6051,111.9035],
    ["Jombang",-7.5460,112.2331],
    ["Mojokerto",-7.4722,112.4333],
    ["Kediri",-7.8167,112.0167],
    ["Ngawi",-7.4039,111.4463],
    ["Magetan",-7.6449,111.3380],
    ["Madiun",-7.6298,111.5239],
    ["Ponorogo",-7.8651,111.4663],
    ["Pacitan",-8.2000,111.0833],
    ["Trenggalek",-8.0500,111.7167],
    ["Tulungagung",-8.0667,111.9000],
    ["Blitar",-8.1000,112.1667],
    ["Malang",-7.9839,112.6214],
    ["Lumajang",-8.1333,113.2167],
    ["Jember",-8.1721,113.6995],
    ["Bondowoso",-7.9133,113.8214],
    ["Situbondo",-7.7067,113.9654],
    ["Probolinggo",-7.7540,113.2156],
    ["Pasuruan",-7.6453,112.9066],
    ["Banyuwangi",-8.2325,114.3576],
    ["Bangkalan",-7.0350,112.9139],
    ["Sampang",-7.1890,113.2417],
    ["Pamekasan",-7.1616,113.4826],
    ["Sumenep",-7.0167,113.8667],
    ["Kota Batu",-7.8700,112.5200]
  ];

  points.forEach(function(m){
    L.marker([m[1],m[2]]).addTo(map).bindPopup("<b>"+m[0]+"</b>");
  });
})();
</script>
<?php endif; ?>

<script>
const tahunInp=document.getElementById('tahun');
const bidangSel=document.getElementById('bidang');
const applyBtn=document.getElementById('applyYear');
const resetBtn=document.getElementById('resetYear');

function applyYear(){
  const y=tahunInp.value.trim();
  const b=bidangSel.value;
  if(!/^\d{4}$/.test(y)){ alert('Masukkan tahun 4 digit valid.'); return; }
  if(!b){ alert('Silakan pilih Bidang terlebih dahulu.'); return; }
  let url='dashboard.php?tahun='+encodeURIComponent(y)+'&bidang='+encodeURIComponent(b);
  location.href=url;
}
function resetYear(){ location.href='dashboard.php'; }

applyBtn?.addEventListener('click',applyYear);
tahunInp?.addEventListener('keydown',e=>{if(e.key==='Enter')applyYear();});
resetBtn?.addEventListener('click',resetYear);

const mapPage={
  tangkap:'/perikanan-tangkap.php',
  budidaya:'/perikanan-budidaya.php',
  kpp:'/kpp.php',
  pengolahan:'/pengolahan-pemasaran.php',
  ekspor:'/ekspor-perikanan.php',
  investasi:'/investasi.php'
};

<?php if ($selectedYear && $selectedBidang): ?>
(function(){
  const bidang='<?= e($selectedBidang) ?>';
  const tahun='<?= e($selectedYear) ?>';
  const url=(mapPage[bidang])+'?dashboard=1&tahun='+encodeURIComponent(tahun);
  const f=document.getElementById('frameDash');
  const s=document.getElementById('frameSkel');

  const TYPE_SPEED = 0;

  function typeText(el, text, speed){
    if(!el) return;
    if(!speed || speed<=0){ el.textContent = text; return; }
    el.textContent=''; let i=0; const tmr=setInterval(()=>{ el.textContent += text[i++]||''; if(i>=text.length) clearInterval(tmr); }, speed);
  }

  const YEAR_SELECTORS = [
    '[data-ringkasan-tahun]',
    '#tahunPemasaranHead',
    '#tahunOlahanKabHead',
    '#tahunOlahanJenisHead',
    '#tahunRingkasanHead',
    '[id^="tahun"][id$="Head"]'
  ];
  function fillKnownTargets(doc, y){
    let hit=0;
    YEAR_SELECTORS.forEach(sel=>{
      doc.querySelectorAll(sel).forEach(el=>{ typeText(el, String(y), TYPE_SPEED); hit++; });
    });
    return hit;
  }

  function replacePlainTextYear(doc, y){
    let changed = 0;
    const candidates = doc.querySelectorAll('div,span,strong,small,p,td,th,h1,h2,h3,label');
    candidates.forEach(el=>{
      if (el.children && el.children.length) return;
      const t = (el.textContent||'').trim();
      const replaced = t
        .replace(/^(Tahun)\s*[: ]*\s*[–—-]\s*$/i, `$1 ${y}`)
        .replace(/(Tahun)\s*[: ]*\s*[–—-]\s*(?!\d{4})/i, `$1 ${y}`);
      if(replaced !== t){ el.textContent = replaced; changed++; }
    });
    return changed;
  }

  function forceFillYear(doc, y){
    let hits = 0;
    hits += fillKnownTargets(doc, y);
    hits += replacePlainTextYear(doc, y);
    return hits;
  }
  function tryFillLoop(doc, y){
    [0, 300, 900, 1600].forEach(delay=>{
      setTimeout(()=>{ try{ forceFillYear(doc, y); }catch(_){/* noop */} }, delay);
    });
  }

  if(f){
    f.addEventListener('load',()=>{
      s?.classList.add('hidden');
      try{ f.contentWindow.postMessage({ type:'setYear', tahun }, location.origin); }catch(_){}
      try{
        const doc = f.contentWindow?.document;
        if(!doc) return;
        if(doc.readyState !== 'loading') { tryFillLoop(doc, tahun); }
        else { doc.addEventListener('DOMContentLoaded', ()=>tryFillLoop(doc, tahun), {once:true}); }
      }catch(_){}
    });
    f.src=url;
  }

  window.addEventListener('message',e=>{
    if(e.origin!==location.origin) return;
    if(!e.data||e.data.type!=='resizeFrame') return;
    const h=Math.max(600,Number(e.data.height)||0);
    if(f) f.style.height=h+'px';
  });
})();
<?php endif; ?>
</script>

<script>
/* ========= Ambil angka KPI & auto-fit + filter per bidang ========= */
(function(){
  const year = '<?= e($kpiYear) ?>';
  const selectedBidang = '<?= $selectedBidang ? e($selectedBidang) : '' ?>';

  const el = {
    tangkap:   document.getElementById('kpiTangkap'),
    budidaya:  document.getElementById('kpiBudidaya'),
    kpp:       document.getElementById('kpiKpp'),
    olahan:    document.getElementById('kpiOlahan'),
    ekspor:    document.getElementById('kpiEkspor'),
    investasi: document.getElementById('kpiInvestasi'),
  };

  const fmt = n => new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 2
  }).format(+n || 0);

  function shrinkToFit(span, opts){
    if(!span) return;
    const box = span.parentElement;
    if(!box) return;

    const { start = 32, min = 12, step = 1 } = (opts||{});

    span.style.fontSize = start + 'px';
    if (span.scrollWidth <= box.clientWidth) return;

    let size = start;
    while (size > min && span.scrollWidth > box.clientWidth) {
      size -= step;
      span.style.fontSize = size + 'px';
    }
  }

  function fitAll(){
    shrinkToFit(el.tangkap,   {start: 32, min: 12});
    shrinkToFit(el.budidaya,  {start: 32, min: 12});
    shrinkToFit(el.kpp,       {start: 32, min: 12});
    shrinkToFit(el.olahan,    {start: 32, min: 12});
    shrinkToFit(el.ekspor,    {start: 32, min: 12});
    shrinkToFit(el.investasi, {start: 32, min: 12});
  }
  window.__fitKPI = fitAll;

  // FILTER: kalau ada bidang terpilih → tampilkan cuma kartu itu & bikin full width
  (function(){
    if(!selectedBidang) return;
    const row = document.getElementById('kpiRow');
    const cards = row?.querySelectorAll('.kpi-card') || [];
    cards.forEach(card=>{
      if(card.dataset.bidang !== selectedBidang){
        card.style.display='none';
      }
    });
    if(row){
      row.classList.add('single-one');
    }
  })();

  fetch('/api/dashboard_totals.php?tahun='+encodeURIComponent(year), {credentials:'same-origin'})
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(d => {
      const t = d?.totals || {};
      if (el.tangkap)   el.tangkap.textContent   = fmt(t.tangkap_ton);
      if (el.budidaya)  el.budidaya.textContent  = fmt(t.budidaya_ton);
      if (el.kpp)       el.kpp.textContent       = fmt(t.kpp_garam_ton);
      if (el.olahan)    el.olahan.textContent    = fmt(t.pengolahan_unit);
      if (el.ekspor)    el.ekspor.textContent    = fmt(t.ekspor_ton);
      if (el.investasi) el.investasi.textContent = fmt(t.investasi_juta);

      requestAnimationFrame(() => requestAnimationFrame(fitAll));
    })
    .catch(_ => {
      Object.values(el).forEach(x => x && (x.textContent='0'));
      requestAnimationFrame(fitAll);
    });

  let rid;
  window.addEventListener('resize', () => {
    cancelAnimationFrame(rid);
    rid = requestAnimationFrame(fitAll);
  });

  requestAnimationFrame(fitAll);
})();
</script>

</body>
</html>
