<?php
session_start();
require_once __DIR__.'/api/db.php';
require_once __DIR__.'/api/auth_lib.php';

const ADMIN_ACCESS_CODE = 'Diskanla';

$err=''; $ok='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $err='Sesi kadaluarsa. Muat ulang halaman.';
  } else {
    $name=trim($_POST['name']??''); $email=strtolower(trim($_POST['email']??''));
    $p1=(string)($_POST['password']??''); $p2=(string)($_POST['password2']??'');
    $adm=(string)($_POST['admin_code']??'');

    if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || $p1==='' || $p2==='')      $err='Lengkapi semua kolom.';
    elseif ($p1!==$p2)                                                                          $err='Konfirmasi password tidak sama.';
    elseif (strlen($p1)<8)                                                                      $err='Password minimal 8 karakter.';
    else {
      $stm=pdo()->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1"); $stm->execute([$email]);
      if ($stm->fetch()) $err='Email sudah terdaftar.';
      else {
        $role = ($adm===ADMIN_ACCESS_CODE) ? 'admin' : 'user';
        pdo()->prepare("INSERT INTO users (name,email,role,password_hash,created_at)
                        VALUES (?,?,?,?,NOW())")
            ->execute([$name,$email,$role,password_hash($p1,PASSWORD_DEFAULT)]);
        $ok='Pendaftaran berhasil. Silakan login.';
      }
    }
  }
}
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar - Samudera</title>
<link rel="icon" href="/images/logo.png">
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-b from-blue-900 to-sky-500">
  <div class="w-full max-w-md bg-white/10 backdrop-blur rounded-2xl p-6 text-white border border-white/20">
    <div class="text-center mb-4">
      <div class="flex items-center justify-center mb-2"><img src="/images/logo.png" class="h-8 mr-2" alt="SAMUDERA"><h1 class="font-extrabold text-xl">Daftar Akun Samudera</h1></div>
      <p class="text-xs text-blue-100">Role default: <b>User</b> (isi kode admin jika perlu)</p>
    </div>

    <?php if ($err): ?><div class="mb-3 text-sm bg-red-500/15 border border-red-300/40 text-red-100 rounded p-3"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($ok):  ?><div class="mb-3 text-sm bg-green-500/15 border border-green-300/40 text-green-100 rounded p-3"><?= htmlspecialchars($ok)  ?></div><?php endif; ?>

    <form method="POST" action="register.php" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

      <label class="block text-sm mb-1">Nama Lengkap</label>
      <input name="name" class="w-full mb-3 px-3 py-2 rounded bg-white/15 border border-white/30" placeholder="Nama Anda" required>

      <label class="block text-sm mb-1">Email</label>
      <input type="email" name="email" class="w-full mb-3 px-3 py-2 rounded bg-white/15 border border-white/30" placeholder="nama@samudera.com" required>

      <label class="block text-sm mb-1">Password</label>
      <input type="password" name="password" class="w-full mb-3 px-3 py-2 rounded bg-white/15 border border-white/30" placeholder="Minimal 8 karakter" required>

      <label class="block text-sm mb-1">Konfirmasi Password</label>
      <input type="password" name="password2" class="w-full mb-4 px-3 py-2 rounded bg-white/15 border border-white/30" required>

      <label class="block text-sm mb-1">Kode Admin (opsional)</label>
      <input type="password" name="admin_code" class="w-full mb-5 px-3 py-2 rounded bg-white/15 border border-white/30" placeholder="Isi 'Diskanla' jika mendaftar sebagai Admin">

      <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl">Daftar</button>
      <p class="text-center text-xs mt-3">Sudah punya akun? <a href="login.php" class="underline">Masuk</a></p>
    </form>
  </div>
</body></html>
