<?php
// api/db.php
declare(strict_types=1);

/**
 * === KREDENSIAL DB (XAMPP LOCAL) ===
 * Sesuaikan dengan pengaturan XAMPP-mu
 */
const DB_HOST = 'localhost';
const DB_NAME = 'samudata_db';   
const DB_USER = 'root';         
const DB_PASS = '';              

/* ---- PDO singleton ---- */
function pdo(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  try {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+07:00'",
    ]);

    $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

  } catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok' => false,
      'message' => 'Database connection error'
    ]);
    exit;
  }

  return $pdo;
}


/* ---- Helper JSON ---- */
function json_out(array $arr, int $code = 200): never {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function json_ok(array $data = []): never {
  json_out(['ok' => true] + $data, 200);
}
function json_err(string $msg, array $extra = [], int $code = 400): never {
  json_out(['ok' => false, 'error' => $msg] + $extra, $code);
}

/* ---- Util baca JSON body ---- */
function read_json(): array {
  $raw = file_get_contents('php://input');
  $j = json_decode($raw ?: '', true);
  if (!is_array($j)) json_err('Invalid JSON body');
  return $j;
}

/* ---- Normalisasi angka ---- */
function num($v): float {
  if ($v === null || $v === '') return 0.0;
  $s = preg_replace('/[^\d.,\-]/u', '', (string)$v);
  // normalisasi koma/titik
  if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
  } elseif (strpos($s, ',') !== false) {
    $s = str_replace(',', '.', $s);
  }
  return is_numeric($s) ? (float)$s : 0.0;
}

/* ---- Session secure helper (opsional) ---- */
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
