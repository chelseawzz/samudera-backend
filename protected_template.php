<?php
// protected_template.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Izinkan halaman di-embed oleh halaman lain dari origin yang sama (dashboard.php)
if (!headers_sent()) {
  header('X-Frame-Options: SAMEORIGIN');
  header("Content-Security-Policy: frame-ancestors 'self'");
}

// Deteksi mode embed (dipanggil dari dashboard via iframe)
$is_embed = (isset($_GET['dashboard']) && $_GET['dashboard'] === '1');

function start_protected_page(string $page_title = 'Samudera', string $active = '') {
  $display_name = $_SESSION['display_name'] ?? 'admin';
  // akses variabel embed di dalam fungsi
  $is_embed = (isset($_GET['dashboard']) && $_GET['dashboard'] === '1');
  ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($page_title) ?> – Samudera</title>

  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    :root{ --samudera-bg:#0b1b2b; --samudera-bg2:#0e2740; }
    .topbar{background:linear-gradient(180deg,var(--samudera-bg),var(--samudera-bg2));color:#fff;box-shadow:0 2px 10px rgba(0,0,0,.25)}
    .nav-item{display:inline-flex;align-items:center;gap:.45rem;padding:.40rem .80rem;border-radius:.40rem;color:#fff;opacity:.92;transition:.2s}
    .nav-item:hover{opacity:1;background:rgba(255,255,255,.12)}
    .nav-item.active{background:rgba(255,255,255,.12)}
    .dropdown{position:relative}
    .dropdown-menu{position:absolute;top:100%;left:0;min-width:240px;background:#0f1f34;border-radius:.6rem;padding:.35rem;display:none;box-shadow:0 10px 30px rgba(0,0,0,.3);z-index:60}
    .dropdown-menu a{display:flex;align-items:center;gap:.55rem;color:#fff;opacity:.9;padding:.55rem .75rem;border-radius:.45rem}
    .dropdown-menu a i{width:1.1rem;text-align:center}
    .dropdown-menu a:hover{background:rgba(255,255,255,.12);opacity:1}
    .show{display:block}
    .logo-wrap{background:rgba(255,255,255,.92);border-radius:9999px;padding:4px;box-shadow:0 0 0 2px rgba(255,255,255,.55),0 3px 8px rgba(0,0,0,.25)}
    .logo-img{width:36px;height:36px;border-radius:9999px;display:block;filter:drop-shadow(0 0 1px #fff) drop-shadow(0 0 6px rgba(255,255,255,.35))}
    /* ======= Embed tweaks: ketika ?dashboard=1 ======= */
    <?php if ($is_embed): ?>
    header.topbar{display:none !important;}
    html,body{height:auto!important;overflow:visible!important}
    <?php endif; ?>
  </style>
</head>
<body class="bg-gray-50 text-slate-900">

<?php if (!$is_embed): ?>
<header class="topbar w-full">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <div class="flex items-center">
      <div class="bg-white/90 rounded-full p-1.5 shadow-md flex items-center justify-center">
        <span class="logo-wrap">
          <img src="/images/logo.png" alt="Logo Samudera" class="logo-img" onerror="this.style.display='none'">
        </span>
      </div>
      <span class="ml-2 font-bold text-white">SAMUDERA</span>
    </div>

    <nav class="hidden md:flex items-center gap-4 text-sm">
      <a href="/dashboard.php" class="nav-item <?= $active==='dashboard'?'active':'' ?>"><i class="fa-solid fa-globe"></i> Dashboard</a>
      <a href="/files.php" class="nav-item <?= $active==='files'?'active':'' ?>"><i class="fa-solid fa-diagram-project"></i> File Manager</a>

      <div class="dropdown">
        <button class="nav-item <?= in_array($active,['tangkap','budidaya','kpp','pengolahan','ekspor','investasi'])?'active':'' ?>" id="btnBidang">
          <i class="fa-solid fa-layer-group"></i> Bidang
          <i class="fa-solid fa-chevron-down text-xs opacity-80"></i>
        </button>
        <div class="dropdown-menu" id="menuBidang">
          <a href="/perikanan-tangkap.php"><i class="fa-solid fa-fish"></i> Perikanan Tangkap</a>
          <a href="/perikanan-budidaya.php"><i class="fa-solid fa-seedling"></i> Perikanan Budidaya</a>
          <a href="/kpp.php"><i class="fa-solid fa-shield-halved"></i> KPP</a>
          <a href="/pengolahan-pemasaran.php"><i class="fa-solid fa-industry"></i> Pengolahan &amp; Pemasaran</a>
          <a href="/ekspor-perikanan.php"><i class="fa-solid fa-truck-plane"></i> Ekspor Perikanan</a>
          <a href="/investasi.php"><i class="fa-solid fa-chart-line"></i> Investasi KP</a>
        </div>
      </div>

      <div class="dropdown">
        <button class="nav-item <?= $active==='akun'?'active':'' ?>" id="btnAkun">
          <i class="fa-solid fa-user"></i> Akun
          <i class="fa-solid fa-chevron-down text-xs opacity-80"></i>
        </button>
        <div class="dropdown-menu" id="menuAkun" style="right:0;left:auto;">
          <a href="/pengaturan-akun.php"><i class="fa-solid fa-user-gear"></i> Pengaturan Akun</a>
          <a href="/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
      </div>
    </nav>
  </div>
</header>
<?php endif; ?>

<?php if (!$is_embed): ?>
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
<?php endif; ?>

<!-- ========= TAMBAHAN: Modal Kode Admin + JS guard (pakai endpoint) ========= -->
<?php if (!$is_embed): ?>
<style>
  .modal-mask{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:100}
  .modal-mask.show{display:flex}
  .modal-card{background:#fff;border-radius:14px;padding:16px 18px;max-width:420px;width:92%;box-shadow:0 18px 60px rgba(0,0,0,.25)}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:.55rem .9rem;border-radius:.6rem;font-weight:600}
  .btn-plain{border:1px solid #e5e7eb}
  .btn-primary{background:#0b4fb3;color:#fff}
</style>

<div id="admModal" class="modal-mask" role="dialog" aria-modal="true" aria-labelledby="admTitle">
  <div class="modal-card">
    <h3 id="admTitle" class="font-bold text-lg mb-1">Akses Administrator</h3>
    <p class="text-sm text-slate-600 mb-3">Masukkan kode akses administrator untuk melanjutkan.</p>
    <input id="admInput" type="password" class="w-full border rounded-lg px-3 py-2" placeholder="Kode akses">
    <p id="admMsg" class="text-sm text-red-600 mt-2 hidden">Kode salah. Coba lagi.</p>
    <div class="mt-4 flex items-center justify-end gap-2">
      <button id="admCancel" type="button" class="btn btn-plain">Batal</button>
      <button id="admOk" type="button" class="btn btn-primary">Lanjut</button>
    </div>
  </div>
</div>

<script>
(function(){
  // role dari session (default 'user')
  const ROLE = <?= json_encode($_SESSION['userType'] ?? 'user') ?>;

  // Selektor elemen yang butuh admin: File Manager + semua link Bidang
  const needsAdmin = ['a[href="/files.php"]', '#menuBidang a'];

  const modal = document.getElementById('admModal');
  const input = document.getElementById('admInput');
  const msg   = document.getElementById('admMsg');
  const okBtn = document.getElementById('admOk');
  const cancelBtn = document.getElementById('admCancel');
  let nextHref = null;

  function openModal(href){
    nextHref = href || null;
    msg.classList.add('hidden');
    input.value = '';
    modal.classList.add('show');
    setTimeout(()=>input.focus(), 50);
  }
  function closeModal(){
    modal.classList.remove('show');
    nextHref = null;
  }

  async function isAdminSession(){
    try{
      const r = await fetch('/api/check_admin_session.php', {cache:'no-store'});
      const j = await r.json();
      return !!(j && j.is_admin);
    }catch(_){ return (ROLE === 'admin'); }
  }

  // intercept klik
  document.querySelectorAll(needsAdmin.join(',')).forEach(a=>{
    a.addEventListener('click', async (e)=>{
      const admin = await isAdminSession();
      if (!admin) {
        e.preventDefault();
        openModal(a.getAttribute('href'));
      }
    });
  });

  okBtn.addEventListener('click', async ()=>{
    const code = input.value.trim();
    if (!code) { msg.textContent='Masukkan kodenya.'; msg.classList.remove('hidden'); return; }
    try{
      const r = await fetch('/api/validate_admin_code.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({code})
      });
      const j = await r.json();
      if (j && j.ok) {
        closeModal();
        if (nextHref) location.href = nextHref; else location.reload();
      } else {
        msg.textContent = (j && j.error) ? j.error : 'Kode salah.';
        msg.classList.remove('hidden');
      }
    }catch(_){
      msg.textContent = 'Gagal koneksi.';
      msg.classList.remove('hidden');
    }
  });

  cancelBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e)=>{ if(e.target === modal) closeModal(); });
  input.addEventListener('keydown', (e)=>{ if(e.key === 'Enter') okBtn.click(); });
})();

    const ADMIN_CODE = 'Diskanla';

</script>
<?php endif; ?>
<!-- ========= /TAMBAHAN ========= -->

<main class="max-w-7xl mx-auto px-4 <?= $is_embed ? 'py-0' : 'py-6' ?>">
<?php } // end start_protected_page

function end_protected_page() { ?>
</main>
</body>
</html>
<?php } // end end_protected_page
