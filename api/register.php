<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);


// ======== START: ROBUST CORS CONFIGURATION ========
$allowedOrigin = 'http://localhost:5173'; // Sesuaikan jika perlu

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: $allowedOrigin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit;
}

header("Access-Control-Allow-Origin: $allowedOrigin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");
header("Access-Control-Allow-Methods: POST, GET");
header('Content-Type: application/json; charset=utf-8');
// ======== END: ROBUST CORS CONFIGURATION ========

session_start();
require_once __DIR__.'/db.php';

// Baca JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$name  = trim($input['name'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$pass  = $input['password'] ?? '';
$role = ($input['userType'] ?? 'user') === 'admin' ? 'admin' : 'user';


/* VALIDASI */
if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false,'message'=>'Data tidak valid']);
    exit;
}
if (strlen($pass) < 8) {
    echo json_encode(['ok'=>false,'message'=>'Password minimal 8 karakter']);
    exit;
}

/* CEK DUPLIKAT */
$cek = pdo()->prepare("SELECT id FROM users WHERE email=?");
$cek->execute([$email]);
if ($cek->fetch()) {
    echo json_encode(['ok'=>false,'message'=>'Email sudah terdaftar']);
    exit;
}

/* SIMPAN */
$hash = password_hash($pass, PASSWORD_DEFAULT);

// Debug: pastikan hash berhasil dibuat
if ($hash === false) {
    error_log("Password hash failed for email: " . $email);
    echo json_encode(['ok' => false, 'message' => 'Gagal membuat hash password']);
    exit;
}

$st = pdo()->prepare("INSERT INTO users (name,email,role,password_hash) VALUES (?,?,?,?)");
$st->execute([$name,$email,$role,$hash]);

// Buat session
$user_id = pdo()->lastInsertId();
$_SESSION['isLoggedIn'] = true;
$_SESSION['userType']   = $role;
$_SESSION['username']   = $name;
$_SESSION['email']      = $email;
$_SESSION['user_id']    = (int)$user_id;
$_SESSION['avatar']     = null;
session_regenerate_id(true);

// Catat ke audit log
try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $dev = 'Web/React';
    $loc = null;
    $sid = session_id();

    $audit = pdo()->prepare("INSERT INTO login_audit (user_id,session_id,event_time,ip,device,location,status) VALUES (?,?,?,?,?,?, 'active')");
    $audit->execute([(int)$user_id, $sid, date('Y-m-d H:i:s'), $ip, $dev, $loc]);
    $_SESSION['audit_id'] = (int)pdo()->lastInsertId();
} catch (Throwable $e) {
    // abaikan error audit
}

echo json_encode([
    'ok' => true,
    'message' => 'Registrasi berhasil',
    'role' => $role
]);