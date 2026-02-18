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

// Cek session
if (!isset($_SESSION['isLoggedIn']) || $_SESSION['isLoggedIn'] !== true) {
  echo json_encode(['ok' => false, 'message' => 'Tidak terautentikasi']);
  exit;
}

try {
  $user_id = $_SESSION['user_id'] ?? 0;
  $nama = trim($_POST['nama'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $telp = trim($_POST['telp'] ?? '');

  // Validasi
  if (empty($nama) || empty($email)) {
    echo json_encode(['ok' => false, 'message' => 'Nama dan email harus diisi']);
    exit;
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'Email tidak valid']);
    exit;
  }

  // Update hanya kolom yang ada di database
  $st = pdo()->prepare(
    "UPDATE users 
     SET name = ?, email = ?, phone = ? 
     WHERE id = ?"
  );
  $st->execute([$nama, $email, $telp, $user_id]);

  // Update session
  $_SESSION['username'] = $nama;
  $_SESSION['email'] = $email;

  echo json_encode(['ok' => true, 'message' => 'Profil berhasil diperbarui']);

} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'message' => 'Gagal memperbarui profil: ' . $e->getMessage()]);
}

ob_end_flush();
?>