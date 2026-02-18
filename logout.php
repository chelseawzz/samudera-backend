<?php
// logout.php — update audit jadi 'logout' lalu destroy session
ini_set('display_errors','0'); error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/api/db.php';

try {
  if (!empty($_SESSION['audit_id'])) {
    $aid = (int)$_SESSION['audit_id'];
    pdo()->prepare("UPDATE login_audit SET status='logout', event_time=NOW() WHERE id=?")->execute([$aid]);
  } elseif (!empty($_SESSION['user_id'])) {
    // fallback: jika audit_id hilang, tutup sesi berdasarkan session_id
    $sid = session_id();
    pdo()->prepare("UPDATE login_audit SET status='logout', event_time=NOW() WHERE user_id=? AND session_id=? ORDER BY id DESC LIMIT 1")
        ->execute([(int)$_SESSION['user_id'], $sid]);
  }
} catch (Throwable $e) { /* abaikan */ }

// bersihkan session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: /samudata/login.php');
exit;
