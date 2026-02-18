<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(0);

session_start();
require_once __DIR__ . '/db.php';

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

$response = ['ok' => false];

// Cek session
if (!isset($_SESSION['isLoggedIn']) || $_SESSION['isLoggedIn'] !== true) {
  echo json_encode(['ok' => false, 'message' => 'Tidak terautentikasi']);
  exit;
}

try {
  $user_id = $_SESSION['user_id'] ?? 0;
  $current_password = $_POST['current_password'] ?? '';
  $new_password = $_POST['new_password'] ?? '';

  // Validasi
  if (strlen($new_password) < 8) {
    echo json_encode(['ok' => false, 'message' => 'Password minimal 8 karakter']);
    exit;
  }

  // Ambil password hash dari database
  $st = pdo()->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
  $st->execute([$user_id]);
  $user = $st->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    echo json_encode(['ok' => false, 'message' => 'User tidak ditemukan']);
    exit;
  }

  // Verifikasi password lama
  if (!password_verify($current_password, $user['password_hash'])) {
    echo json_encode(['ok' => false, 'message' => 'Password saat ini salah']);
    exit;
  }

  // Hash password baru
  $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

  // Update password
  $st = pdo()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
  $st->execute([$new_hash, $user_id]);

  echo json_encode(['ok' => true, 'message' => 'Password berhasil diubah']);

} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'message' => 'Gagal mengubah password']);
}

ob_end_flush();
?>