<?php
// login.php — SAMUDERA (animasi laut bergerak + day/night) + login DB + audit sesi aktif

ini_set('display_errors','0'); // set '1' kalau perlu debug
error_reporting(E_ALL);

session_start();
require_once __DIR__.'/api/db.php';        // pdo()
require_once __DIR__.'/api/auth_lib.php';  // csrf_token(), csrf_check()

const ADMIN_ACCESS_CODE = 'Diskanla'; // ganti sesuai kebutuhan

/* ============================ UTIL ============================ */
function http_get(string $url, array $headers = [], int $timeout = 4): ?string {
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => $timeout,
      CURLOPT_TIMEOUT        => $timeout,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTPHEADER     => $headers,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_USERAGENT      => 'samudera-login/1.0',
    ]);
    $out = curl_exec($ch);
    curl_close($ch);
    return $out !== false ? $out : null;
  }
  $ctx = stream_context_create(['http'=>[
    'method'=>'GET','timeout'=>$timeout,
    'header'=>implode("\r\n",$headers)
  ]]);
  $out = @file_get_contents($url,false,$ctx);
  return $out !== false ? $out : null;
}

function detect_device(string $ua): string {
  $ua = strtolower($ua);
  $os = 'OS';
  if (str_contains($ua,'windows')) $os='Windows';
  elseif (str_contains($ua,'android')) $os='Android';
  elseif (str_contains($ua,'iphone')||str_contains($ua,'ios')) $os='iOS';
  elseif (str_contains($ua,'mac os')||str_contains($ua,'macintosh')) $os='macOS';
  elseif (str_contains($ua,'linux')) $os='Linux';
  $br = 'Browser';
  if (str_contains($ua,'edg/')) $br='Edge';
  elseif (str_contains($ua,'chrome/')) $br='Chrome';
  elseif (str_contains($ua,'firefox/')) $br='Firefox';
  elseif (str_contains($ua,'safari/') && !str_contains($ua,'chrome/')) $br='Safari';
  if (preg_match('~chrome/(\d+)~i',$ua,$m)) $br.=' '.$m[1];
  return $os.' · '.$br;
}

// Koordinat → nama lokasi (opsional)
function reverse_geocode(?float $lat, ?float $lng): ?string {
  if ($lat === null || $lng === null) return null;
  $url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat={$lat}&lon={$lng}&zoom=10&accept-language=id";
  $json = http_get($url, ['User-Agent: samudera-login/1.0']);
  if (!$json) return null;
  $d = json_decode($json,true);
  if (!is_array($d)) return null;
  if (!empty($d['address'])) {
    $a=$d['address']; $parts=[];
    foreach (['village','suburb','city','town','municipality','county','state','country'] as $k) {
      if (!empty($a[$k])) $parts[]=$a[$k];
    }
    if ($parts) return implode(', ', array_slice(array_unique($parts),0,3));
  }
  return $d['display_name'] ?? null;
}

// IP → lokasi (fallback, mencoba beberapa layanan)
function ip_lookup_city(?string $ip): ?string {
  if (!$ip || $ip==='127.0.0.1') return null;
  // 1) ipapi.co
  $j = http_get("https://ipapi.co/{$ip}/json/");
  if ($j) {
    $d = json_decode($j,true);
    if (is_array($d)) {
      $parts=[]; foreach (['city','region','country_name'] as $k) if (!empty($d[$k])) $parts[]=$d[$k];
      if ($parts) return implode(', ',$parts);
    }
  }
  // 2) ipinfo.io (fallback)
  $j = http_get("https://ipinfo.io/{$ip}/json");
  if ($j) {
    $d = json_decode($j,true);
    if (is_array($d)) {
      if (!empty($d['city']) || !empty($d['region']) || !empty($d['country'])) {
        $parts=[]; foreach (['city','region','country'] as $k) if (!empty($d[$k])) $parts[]=$d[$k];
        return implode(', ',$parts);
      }
    }
  }
  return null;
}

function db_find_user(string $email): ?array {
  try {
    $stm = pdo()->prepare("SELECT id,name,email,role,password_hash,phone,avatar_path FROM users WHERE email=? LIMIT 1");
    $stm->execute([$email]);
    $u = $stm->fetch();
    return $u ?: null;
  } catch (Throwable $e) { return null; }
}
function db_create_user(string $name, string $email, string $role, string $password): ?int {
  try {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stm = pdo()->prepare("INSERT INTO users (name,email,role,password_hash) VALUES (?,?,?,?)");
    $stm->execute([$name, $email, $role, $hash]);
    return (int)pdo()->lastInsertId();
  } catch (Throwable $e) { return null; }
}

/* ========== SUDAH LOGIN? LANGSUNG ARAHKAN ========== */
if (!empty($_SESSION['isLoggedIn'])) {
  header('Location: /samudata/dashboard.php'); exit;
}

/* ============================ STATE ============================ */
$err = '';
$email_value = '';
$role_value  = 'user';

/* ============================ SUBMIT ============================ */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $err = 'Sesi kadaluarsa. Muat ulang halaman.';
  } else {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    $role  = (($_POST['userType'] ?? 'user') === 'admin') ? 'admin' : 'user';
    $code  = trim($_POST['access_code'] ?? '');
    $lat   = isset($_POST['geo_lat']) && $_POST['geo_lat'] !== '' ? (float)$_POST['geo_lat'] : null;
    $lng   = isset($_POST['geo_lng']) && $_POST['geo_lng'] !== '' ? (float)$_POST['geo_lng'] : null;

    $email_value = htmlspecialchars($email);
    $role_value  = $role;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = 'Format email tidak valid.';
    } elseif ($role === 'admin' && $code !== ADMIN_ACCESS_CODE) {
      $err = 'Kode akses administrator salah.';
    } elseif (strlen($pass) < 8) {
      $err = 'Password minimal 8 karakter.';
    } else {
      $dbUser = db_find_user($email);
      $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
      $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
      $dev = detect_device($ua);
      $loc = reverse_geocode($lat,$lng) ?? ip_lookup_city($ip) ?? null;

      if ($dbUser) {
        if (!password_verify($pass, (string)$dbUser['password_hash'])) {
          $err = 'Password salah.';
        } else {
          $_SESSION['isLoggedIn'] = true;
          $_SESSION['userType']   = ($role === 'admin') ? 'admin' : ($dbUser['role'] ?? 'user');
          $_SESSION['username']   = $dbUser['name'] ?: 'User';
          $_SESSION['email']      = $dbUser['email'];
          $_SESSION['user_id']    = (int)$dbUser['id'];
          $_SESSION['avatar']     = $dbUser['avatar_path'] ?? null;

          session_regenerate_id(true);
          $sid = session_id();

          try {
            $st = pdo()->prepare("INSERT INTO login_audit (user_id,session_id,event_time,ip,device,location,lat,lng,status)
                                  VALUES (?,?,?,?,?,?,?,?, 'active')");
            $st->execute([(int)$dbUser['id'], $sid, date('Y-m-d H:i:s'), $ip, $dev, $loc, $lat, $lng]);
            $_SESSION['audit_id'] = (int)pdo()->lastInsertId();
          } catch (Throwable $e) {}

          header('Location: /samudata/dashboard.php'); exit;
        }
      } else {
        // Auto-register
        $name  = strstr($email,'@',true) ?: 'User';
        $newId = db_create_user($name, $email, $role, $pass);
        if (!$newId) {
          $err = 'Gagal membuat pengguna baru. Coba lagi.';
        } else {
          $_SESSION['isLoggedIn'] = true;
          $_SESSION['userType']   = $role;
          $_SESSION['username']   = $name;
          $_SESSION['email']      = $email;
          $_SESSION['user_id']    = (int)$newId;
          $_SESSION['avatar']     = null;

          session_regenerate_id(true);
          $sid = session_id();

          try {
            $st = pdo()->prepare("INSERT INTO login_audit (user_id,session_id,event_time,ip,device,location,lat,lng,status)
                                  VALUES (?,?,?,?,?,?,?,?, 'active')");
            $st->execute([(int)$newId, $sid, date('Y-m-d H:i:s'), $ip, $dev, $loc, $lat, $lng]);
            $_SESSION['audit_id'] = (int)pdo()->lastInsertId();
          } catch (Throwable $e) {}

          header('Location: /samudata/dashboard.php'); exit;
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - SAMUDERA</title>
  <link rel="icon" href="/images/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{ --sky-top:#0a2a6a; --sky-mid:#0b4fb3; --sky-bot:#17a7df; --sun:#ffdf6e; --moon:#f4f7ff; --star:#cfe3ff; }
    body{min-height:100vh;overflow:hidden;background:linear-gradient(180deg,var(--sky-top) 0%,var(--sky-mid) 40%,var(--sky-bot) 100%);}
    body.night{ --sky-top:#071642; --sky-mid:#0b2d6a; --sky-bot:#0d4a8a; }
    body.night .sun{display:none} body:not(.night) .moon, body:not(.night) .stars{display:none}

    .sky-objects{position:fixed;inset:0;pointer-events:none;z-index:0}
    .sun,.moon{position:absolute; top:10vh; left:-15vh; width:70px; height:70px; border-radius:50%;
      box-shadow:0 0 30px rgba(255,255,255,.35); animation:across 40s linear infinite;}
    .sun{background:var(--sun)} .moon{background:var(--moon)}
    .stars{position:absolute; inset:0; background:
      radial-gradient(2px 2px at 10% 20%, var(--star) 50%, transparent 60%),
      radial-gradient(1.5px 1.5px at 30% 70%, var(--star) 50%, transparent 60%),
      radial-gradient(1.8px 1.8px at 60% 40%, var(--star) 50%, transparent 60%),
      radial-gradient(1.6px 1.6px at 80% 15%, var(--star) 50%, transparent 60%),
      radial-gradient(1.2px 1.2px at 75% 80%, var(--star) 50%, transparent 60%);
      opacity:.8; filter:drop-shadow(0 0 2px var(--star)); animation:twinkle 3.5s ease-in-out infinite alternate; }
    @keyframes across { 0%{transform:translateX(0)} 100%{transform:translateX(130vw)} }
    @keyframes twinkle { from{opacity:.4} to{opacity:.9} }

    .ocean{position:fixed;left:0;right:0;bottom:-1px;height:42vh;pointer-events:none;z-index:0}
    .wave{position:absolute;left:0;bottom:0;width:200%;height:120px;background-repeat:repeat-x;opacity:.65}
    .wave1{background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="120" viewBox="0 0 1200 120"><path fill="%23ffffff" d="M0 80c150 0 150-36 300-36s150 36 300 36 150-36 300-36 150 36 300 36v40H0z"/></svg>');animation:wave1 16s linear infinite}
    .wave2{bottom:10px;opacity:.45;filter:blur(1px);background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="120" viewBox="0 0 1200 120"><path fill="%23e6f6ff" d="M0 80c150 0 150-36 300-36s150 36 300 36 150-36 300-36 150 36 300 36v40H0z"/></svg>');animation:wave2 22s linear infinite reverse}
    .wave3{bottom:22px;opacity:.25;filter:blur(2px);background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="120" viewBox="0 0 1200 120"><path fill="%23bbe7ff" d="M0 80c150 0 150-36 300-36s150 36 300 36 150-36 300-36 150 36 300 36v40H0z"/></svg>');animation:wave3 28s linear infinite}
    @keyframes wave1{to{transform:translateX(-50%)}}
    @keyframes wave2{to{transform:translateX(50%)}}
    @keyframes wave3{to{transform:translateX(-50%)}}

    .glass{background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(12px)}
    .input{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.25)}
    .input::placeholder{color:#e6f0ff99}
    .btn{background:#1f6feb}.btn:hover{background:#1a5ed1}
    .login-card{position:relative;z-index:2;opacity:0;transform:translateY(8px);transition:.45s ease}
    .login-card.show{opacity:1;transform:none}
    .role-card{cursor:pointer;border:2px solid transparent;border-radius:.9rem;padding:.9rem 1rem;background:rgba(255,255,255,.09);display:flex;flex-direction:column;align-items:center;gap:.35rem;transition:.18s}
    .role-card.active{background:#fff;border-color:#9cc7ff;color:#0a2a6a;box-shadow:0 8px 24px rgba(0,0,0,.12)}
    .role-card i{opacity:.9}.role-card.active i{color:#0b4fb3}
  </style>
</head>
<body>
  <div class="sky-objects"><div class="sun"></div><div class="moon"></div><div class="stars"></div></div>
  <div class="ocean" aria-hidden="true"><div class="wave wave1"></div><div class="wave wave2"></div><div class="wave wave3"></div></div>

  <div class="w-full min-h-screen flex items-center justify-center px-4">
    <div id="card" class="login-card mx-auto max-w-sm glass rounded-2xl shadow-2xl p-6 md:p-7 text-white">
      <div class="text-center mb-5">
        <div class="flex items-center justify-center gap-2">
          <img src="/images/logo.png" alt="SAMUDERA" class="h-8">
          <h2 class="text-2xl font-extrabold tracking-wide">SAMUDERA</h2>
        </div>
      </div>

      <?php if (!empty($err)): ?>
      <div class="mb-4 p-3 rounded-lg bg-red-500/15 border border-red-300/40 text-red-100 text-sm">
        <i class="fas fa-circle-exclamation mr-2"></i><?= htmlspecialchars($err) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="login.php" novalidate autocomplete="off" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="geo_lat" id="geo_lat">
        <input type="hidden" name="geo_lng" id="geo_lng">

        <div class="mb-3">
          <label for="email" class="block text-sm mb-1">Email</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-envelope text-white/70"></i></div>
            <input type="email" id="email" name="email" value="<?= $email_value ?>" class="input w-full pl-10 pr-3 py-2.5 rounded-lg" placeholder="nama@emailkamu.com" autocomplete="username" required>
          </div>
        </div>

        <div class="mb-3">
          <label for="password" class="block text-sm mb-1">Password</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-lock text-white/70"></i></div>
            <input type="password" id="password" name="password" class="input w-full pl-10 pr-10 py-2.5 rounded-lg" placeholder="Minimal 8 karakter" autocomplete="current-password" required>
            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-white/80"><i class="fas fa-eye"></i></button>
          </div>
        </div>

        <div class="mt-3 mb-3">
          <p class="block text-sm mb-2">Jenis Akses</p>
          <div class="grid grid-cols-2 gap-3" id="roleCards">
            <label class="role-card" id="cardAdmin">
              <input type="radio" name="userType" value="admin" class="sr-only" <?= $role_value==='admin'?'checked':''; ?>>
              <i class="fas fa-user-shield text-lg"></i><span>Administrator</span>
            </label>
            <label class="role-card" id="cardUser">
              <input type="radio" name="userType" value="user" class="sr-only" <?= $role_value!=='admin'?'checked':''; ?>>
              <i class="fas fa-user text-lg"></i><span>User</span>
            </label>
          </div>
        </div>

        <div id="accessWrap" class="mb-4" style="display: <?= $role_value==='admin'?'block':'none' ?>;">
          <label for="access_code" class="block text-sm mb-1">Kode Akses Administrator</label>
          <input type="password" id="access_code" name="access_code" class="input w-full py-2.5 px-3 rounded-lg" placeholder="Masukkan kode akses">
          <p class="text-xs text-blue-100 mt-1">Hanya diperlukan jika memilih Administrator.</p>
        </div>

        <button type="submit" class="btn w-full text-white font-semibold py-2.5 px-4 rounded-xl">
          <i class="fas fa-right-to-bracket mr-2"></i> Masuk / Daftar
        </button>
      </form>

      <p class="text-center mt-6 text-blue-50 text-xs">© 2024 Dinas Kelautan dan Perikanan Jawa Timur</p>
    </div>
  </div>

  <script>
    window.addEventListener('load', ()=>{ document.getElementById('card').classList.add('show'); });
    (function(){const h=new Date().getHours(); if (h>=18||h<6) document.body.classList.add('night');})();
    (function(){
      const adminCard=document.getElementById('cardAdmin');
      const userCard =document.getElementById('cardUser');
      const access   =document.getElementById('accessWrap');
      const adminR   =adminCard.querySelector('input');
      const userR    =userCard.querySelector('input');
      function sync(){ adminCard.classList.toggle('active',adminR.checked); userCard.classList.toggle('active',userR.checked); access.style.display=adminR.checked?'block':'none'; }
      [adminCard,userCard,adminR,userR].forEach(el=>el.addEventListener('click',sync));
      [adminR,userR].forEach(el=>el.addEventListener('change',sync));
      sync();
    })();
    document.getElementById('togglePassword')?.addEventListener('click',function(){
      const i=document.getElementById('password'); i.type=(i.type==='password')?'text':'password';
      this.querySelector('i').classList.toggle('fa-eye'); this.querySelector('i').classList.toggle('fa-eye-slash');
    });
    // Geolokasi opsional
    (function(){
      if (!navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition(function(pos){
        document.getElementById('geo_lat').value = pos.coords.latitude.toFixed(6);
        document.getElementById('geo_lng').value = pos.coords.longitude.toFixed(6);
      }, function(){}, {enableHighAccuracy:false, timeout:5000, maximumAge:600000});
    })();
  </script>
</body>
</html>
