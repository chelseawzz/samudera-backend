<?php
// api.php — single endpoint (profile_get, profile_save, avatar_upload, password_change, login_list)
declare(strict_types=1);

require_once __DIR__ . '/api/db.php'; // pdo(), json_ok/json_err(), read_json()

// --- Polyfill kecil kalau PHP < 8 (Hostinger biasanya 8, tapi aman saja)
if (!function_exists('str_starts_with')) {
  function str_starts_with(string $haystack, string $needle): bool {
    return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
  }
}

// Session (buat simpan user_id & CSRF)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* === Fallback dev: pakai user pertama kalau belum ada session ===
   HAPUS bagian ini setelah login-mu sudah meng-set $_SESSION['user_id'] */
if (!isset($_SESSION['user_id'])) {
  try {
    $firstId = pdo()->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($firstId) $_SESSION['user_id'] = (int)$firstId;
  } catch (Throwable $e) {
    // biarin, bakal jatuh ke Unauthorized di bawah
  }
}
/* === end fallback === */

// ========== AUTH ==========
if (!isset($_SESSION['user_id'])) {
  json_err('Unauthorized', [], 401);
}
$userId = (int) $_SESSION['user_id'];

// ========== CSRF ==========
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function require_csrf(): void {
  $hdr = $_SERVER['HTTP_X_CSRF'] ?? '';
  if ($hdr !== ($_SESSION['csrf'] ?? '')) json_err('Invalid CSRF token', [], 403);
}
function clean_phone(string $s): string { return preg_replace('/[^0-9+]/', '', $s) ?? ''; }

// ========== ROUTER ==========
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// -------- profile_get (GET)
if ($action === 'profile_get' && $method === 'GET') {
  $stm = pdo()->prepare("SELECT name,email,phone,avatar_path FROM users WHERE id=?");
  $stm->execute([$userId]);
  $u = $stm->fetch();
  if (!$u) json_err('User not found', [], 404);
  $u['avatar_url'] = $u['avatar_path'] ?: 'images/avatar-placeholder.png';
  json_ok(['profile'=>$u, 'csrf'=>$_SESSION['csrf']]);
}

// -------- profile_save (POST JSON)
if ($action === 'profile_save' && $method === 'POST') {
  require_csrf();
  $in    = read_json();
  $name  = trim((string)($in['name']  ?? ''));
  $phone = clean_phone((string)($in['phone'] ?? ''));

  if ($name === '')                      json_err('Nama wajib diisi', [], 422);
  if (mb_strlen($name) > 120)            json_err('Nama terlalu panjang', [], 422);
  if ($phone && mb_strlen($phone) > 40)  json_err('Nomor telepon terlalu panjang', [], 422);

  pdo()->prepare("UPDATE users SET name=?, phone=? WHERE id=?")
      ->execute([$name ?: null, $phone ?: null, $userId]);

  json_ok(['message'=>'Profil tersimpan']);
}

// -------- avatar_upload (POST multipart)
if ($action === 'avatar_upload' && $method === 'POST') {
  require_csrf();
  if (!isset($_FILES['avatar'])) json_err('File tidak ditemukan', [], 400);
  $f = $_FILES['avatar'];
  if ($f['error'] !== UPLOAD_ERR_OK) json_err('Gagal upload (code '.$f['error'].')', [], 400);
  if ($f['size'] > 1024*1024)       json_err('Maksimum 1MB', [], 422);

  $fi   = new finfo(FILEINFO_MIME_TYPE);
  $mime = $fi->file($f['tmp_name']);
  $exts = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/gif'=>'.gif','image/webp'=>'.webp'];
  if (!isset($exts[$mime])) json_err('Format gambar tidak didukung', [], 422);

  $root   = __DIR__;
  $dirAbs = $root . '/uploads/avatars';
  if (!is_dir($dirAbs)) mkdir($dirAbs, 0755, true);

  // hapus lama
  $prev = pdo()->prepare("SELECT avatar_path FROM users WHERE id=?");
  $prev->execute([$userId]);
  $old = (string)$prev->fetchColumn();
  if ($old && str_starts_with($old, 'uploads/avatars/')) {
    $oldAbs = $root . '/' . $old;
    if (is_file($oldAbs)) @unlink($oldAbs);
  }

  $newName = 'u'.$userId.'_'.bin2hex(random_bytes(6)).$exts[$mime];
  $abs     = $dirAbs . '/' . $newName;
  if (!move_uploaded_file($f['tmp_name'], $abs)) json_err('Tidak bisa menyimpan file', [], 500);

  $rel = 'uploads/avatars/'.$newName;
  pdo()->prepare("UPDATE users SET avatar_path=? WHERE id=?")->execute([$rel, $userId]);

  json_ok(['message'=>'Avatar diperbarui','avatar_url'=>$rel]);
}

// -------- password_change (POST JSON)
if ($action === 'password_change' && $method === 'POST') {
  require_csrf();
  $in  = read_json();
  $cur = (string)($in['current'] ?? '');
  $n1  = (string)($in['new1'] ?? '');
  $n2  = (string)($in['new2'] ?? '');

  if ($cur === '' || $n1 === '' || $n2 === '') json_err('Lengkapi semua kolom', [], 422);
  if ($n1 !== $n2)                               json_err('Konfirmasi password tidak sama', [], 422);
  if (strlen($n1) < 8)                            json_err('Minimal 8 karakter', [], 422);

  $stm = pdo()->prepare("SELECT password_hash FROM users WHERE id=?");
  $stm->execute([$userId]);
  $ph = (string)$stm->fetchColumn();
  if (!$ph || !password_verify($cur, $ph)) json_err('Password saat ini salah', [], 403);

  $newHash = password_hash($n1, PASSWORD_DEFAULT);
  pdo()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$newHash, $userId]);
  json_ok(['message'=>'Password berhasil diubah']);
}

// -------- login_list (GET)
if ($action === 'login_list' && $method === 'GET') {
  $page = max(1, (int)($_GET['page'] ?? 1));
  $size = min(50, max(1, (int)($_GET['size'] ?? 10)));
  $off  = ($page - 1) * $size;

  $cnt = pdo()->prepare("SELECT COUNT(*) FROM login_audit WHERE user_id=?");
  $cnt->execute([$userId]);
  $total = (int)$cnt->fetchColumn();

  $stm = pdo()->prepare(
    "SELECT event_time, ip, device, location, status
     FROM login_audit
     WHERE user_id=?
     ORDER BY event_time DESC
     LIMIT ? OFFSET ?"
  );
  $stm->bindValue(1, $userId, PDO::PARAM_INT);
  $stm->bindValue(2, $size,   PDO::PARAM_INT);
  $stm->bindValue(3, $off,    PDO::PARAM_INT);
  $stm->execute();
  $rows = $stm->fetchAll();

  json_ok([
    'items'=>$rows,
    'page'=>$page,
    'size'=>$size,
    'total'=>$total,
    'pages'=>$size ? (int)ceil($total / $size) : 1,
    'csrf'=>$_SESSION['csrf']
  ]);
}

// fallback
json_err('Unknown action', ['action'=>$action], 404);
