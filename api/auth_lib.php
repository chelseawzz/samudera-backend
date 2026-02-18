<?php
// api/auth_lib.php — helper autentikasi user
declare(strict_types=1);
require_once __DIR__ . '/db.php';

/* ---- Cari user berdasarkan email ---- */
function find_user(string $email): ?array {
  try {
    $sql = "SELECT id, name, email, role, password_hash, avatar_path FROM users WHERE email = ? LIMIT 1";
    $st = pdo()->prepare($sql);
    $st->execute([ strtolower(trim($email)) ]);
    $u = $st->fetch();
    return $u ?: null;
  } catch (Throwable $e) {
    return null;
  }
}

/* ---- CSRF Token ---- */
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_check(?string $token): bool {
  return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}
