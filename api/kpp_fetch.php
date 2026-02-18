<?php
declare(strict_types=1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:5173', 'http://127.0.0.1:5173', 'http://localhost:5174'];
$finalOrigin = in_array($origin, $allowedOrigins) ? $origin : 'http://localhost:5173';

header("Access-Control-Allow-Origin: $finalOrigin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// HANDLE PREFLIGHT OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Length: 0');
    http_response_code(204);
    exit(0);
}

// SET CONTENT TYPE
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

function bad(string $msg, int $code = 400): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function ok(array $data = []): void {
  echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
  if ($tahun <= 0) bad('Parameter tahun wajib');

  $pdo = pdo();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Cek kolom yang tersedia di kpp_garam
  $qCols = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kpp_garam'
  ");
  $qCols->execute();
  $have = array_fill_keys(array_map('strval', $qCols->fetchAll(PDO::FETCH_COLUMN) ?: []), true);

  // Susun SELECT: pakai kolom baru jika ada; fallback ke nama lama
  $sel = [];
  $sel[] = "kab_kota";

  // Luas lahan -> kirim sebagai 'luas_lahan_ha'
  if (!empty($have['l_total_ha'])) {
    $sel[] = "l_total_ha AS luas_lahan_ha";
  } elseif (!empty($have['luas_lahan_ha'])) {
    $sel[] = "luas_lahan_ha AS luas_lahan_ha";
  } else {
    $sel[] = "0 AS luas_lahan_ha";
  }

  // Petambak -> kirim sebagai 'jumlah_petambak'
  if (!empty($have['sigma_petambak'])) {
    $sel[] = "sigma_petambak AS jumlah_petambak";
  } elseif (!empty($have['jumlah_petambak'])) {
    $sel[] = "jumlah_petambak AS jumlah_petambak";
  } else {
    $sel[] = "0 AS jumlah_petambak";
  }

  // Produksi -> kirim sebagai 'volume_produksi_ton'
  if (!empty($have['sigma_prod_ton'])) {
    $sel[] = "sigma_prod_ton AS volume_produksi_ton";
  } elseif (!empty($have['volume_produksi_ton'])) {
    $sel[] = "volume_produksi_ton AS volume_produksi_ton";
  } else {
    $sel[] = "0 AS volume_produksi_ton";
  }

  // Kelompok (opsional, hanya skema baru)
  if (!empty($have['jumlah_kelompok'])) {
    $sel[] = "jumlah_kelompok";
  } else {
    $sel[] = "NULL AS jumlah_kelompok";
  }

  $sql = "SELECT ".implode(', ', $sel)." FROM kpp_garam WHERE tahun = ? ORDER BY kab_kota ASC";
  $st = $pdo->prepare($sql);
  $st->execute([$tahun]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  ok(['tahun' => $tahun, 'garam' => $rows]);

} catch (Throwable $e) {
  bad('Fetch error: '.$e->getMessage(), 500);
}
?>