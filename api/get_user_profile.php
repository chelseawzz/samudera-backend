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
  
  $st = pdo()->prepare("SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1");
  $st->execute([$user_id]);
  $user = $st->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    echo json_encode(['ok' => false, 'message' => 'User tidak ditemukan']);
    exit;
  }

  echo json_encode([
    'ok' => true,
    'nama' => $user['name'] ?? '',
    'email' => $user['email'] ?? '',
    'telp' => $user['phone'] ?? '', // Sesuai kolom database
    'jabatan' => 'Administrator Sistem' // Default value, tidak disimpan di DB
  ]);

} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

ob_end_flush();
?>