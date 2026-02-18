<?php
declare(strict_types=1);

/* ================== HARD FIX ================== */
ob_start();
ini_set('display_errors', '0');
error_reporting(0);

session_start();
require_once __DIR__ . '/db.php';

/* ================== CORS ================== */
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';

header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

/* ================== INPUT ================== */
$email = strtolower(trim($_POST['email'] ?? ''));
$pass  = $_POST['password'] ?? '';
$role  = 'admin'; // Hanya admin
$code  = trim($_POST['access_code'] ?? '');

const ADMIN_ACCESS_CODE = 'Diskanla';

/* ================== VALIDASI ================== */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok'=>false,'message'=>'Email tidak valid']); exit;
}

if (strlen($pass) < 8) {
  echo json_encode(['ok'=>false,'message'=>'Password minimal 8 karakter']); exit;
}

if ($code !== ADMIN_ACCESS_CODE) {
  echo json_encode(['ok'=>false,'message'=>'Kode administrator salah']); exit;
}

/* ================== CEK USER ================== */
$st = pdo()->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
$st->execute([$email]);
$user = $st->fetch(PDO::FETCH_ASSOC);

/* ================== AUTO REGISTER ================== */
if (!$user) {
  $name = strstr($email, '@', true) ?: 'Admin';
  $hash = password_hash($pass, PASSWORD_DEFAULT);

  $st = pdo()->prepare(
    "INSERT INTO users (name, email, password_hash, role)
     VALUES (?, ?, ?, 'admin')"
  );
  $st->execute([$name, $email, $hash]);

  $user = [
    'id'            => (int)pdo()->lastInsertId(),
    'name'          => $name,
    'email'         => $email,
    'password_hash'=> $hash,
    'role'          => 'admin'
  ];
}

/* ================== PASSWORD ================== */
if (!password_verify($pass, $user['password_hash'])) {
  echo json_encode(['ok'=>false,'message'=>'Password salah']); exit;
}

/* ================== SESSION ================== */
session_regenerate_id(true);

$_SESSION['isLoggedIn'] = true;
$_SESSION['user_id']   = (int)$user['id'];
$_SESSION['username']  = $user['name'];
$_SESSION['email']     = $user['email'];
$_SESSION['userType']  = 'admin'; // Hanya admin

/* ================== AUDIT (OPTIONAL) ================== */
try {
  $st = pdo()->prepare(
    "INSERT INTO login_audit (user_id, session_id, event_time, ip, device, status)
     VALUES (?, ?, ?, ?, ?, 'active')"
  );
  $st->execute([
    $user['id'],
    session_id(),
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? null,
    $_SERVER['HTTP_USER_AGENT'] ?? ''
  ]);
} catch (Throwable $e) {}

/* ================== RESPONSE ================== */
echo json_encode([
  'ok'       => true,
  'role'     => 'admin',
  'username' => $user['name']
]);

ob_end_flush();
?>