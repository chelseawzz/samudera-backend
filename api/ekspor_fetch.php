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
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db.php';

/* Kompat lama: sediakan pdo() jika db.php expose $pdo */
if (!function_exists('pdo')) {
  function pdo(): PDO { /** @var PDO $pdo */ global $pdo; return $pdo; }
}

/* ==== helpers ==== */
function ok(array $data): never {
  echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_NUMERIC_CHECK);
  exit;
}
function bad(string $msg, int $code=200): never {
  http_response_code($code);
  echo json_encode([
    'ok'=>false,
    'error'=>$msg,
    'total'=>[],
    'utama'=>[],
    'ring'=>[]
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function norm_label(string $s): string {
  $s = str_replace("\xC2\xA0", ' ', $s);
  $s = str_replace("\xA0", ' ', $s);
  return trim(preg_replace('/\s+/u',' ', $s));
}
function tableExists(PDO $db, string $t): bool {
  $st=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
  $st->execute([$t]);
  return (int)$st->fetchColumn()>0;
}
function columnExists(PDO $db, string $t, string $c): bool {
  $st=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $st->execute([$t,$c]);
  return (int)$st->fetchColumn()>0;
}

/* ==== MAIN ==== */
try {
  $tahun = $_GET['tahun'] ?? null;
  if ($tahun===null || !preg_match('/^\d{4}$/', (string)$tahun)) {
    bad('Tahun invalid');
  }
  $Y = (int)$tahun;

  $db = pdo();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  /* ---------- TOTAL ---------- */
  $total = [];
  if (tableExists($db,'ekspor_perikanan_total')) {
    $st=$db->prepare("
      SELECT id,
             TRIM(REPLACE(komoditas, CHAR(160), ' ')) AS komoditas,
             volume_ton, nilai_usd
      FROM ekspor_perikanan_total
      WHERE tahun=?
      ORDER BY id ASC
    ");
    $st->execute([$Y]);
    foreach ($st as $r){
      $total[] = [
        'komoditas'  => norm_label((string)$r['komoditas']),
        'volume_ton' => isset($r['volume_ton']) && $r['volume_ton']!=='' && $r['volume_ton']!==null ? (float)$r['volume_ton'] : 0.0,
        'nilai_usd'  => isset($r['nilai_usd'])  && $r['nilai_usd']  !=='' && $r['nilai_usd']!==null ? (float)$r['nilai_usd']  : 0.0,
      ];
    }
  }

  /* ---------- UTAMA ---------- */
  $utama = [];
  if (tableExists($db,'ekspor_perikanan_utama')) {
    $st=$db->prepare("
      SELECT tahun, sisi, no_urut,
             TRIM(REPLACE(komoditas, CHAR(160), ' ')) AS komoditas,
             angka
      FROM ekspor_perikanan_utama
      WHERE tahun = ?
      ORDER BY 
        CASE sisi 
          WHEN 'VOL' THEN 1 
          WHEN 'USD' THEN 2 
          ELSE 3 
        END,
        no_urut
    ");
    $st->execute([$Y]);
    foreach ($st as $r) {
      $utama[] = [
        'tahun' => (int)$r['tahun'],
        'sisi' => strtoupper(trim((string)$r['sisi'])),
        'no_urut' => (int)$r['no_urut'],
        'komoditas' => norm_label((string)$r['komoditas']),
        'angka' => isset($r['angka']) && $r['angka']!=='' && $r['angka']!==null ? (float)$r['angka'] : 0.0,
      ];
    }
  }

  /* ---------- RING ---------- */
  $ring = [];
  if (tableExists($db,'ekspor_perikanan_ringkasan')) {
    $hasUrut = columnExists($db,'ekspor_perikanan_ringkasan','urut');
    $orderBy = $hasUrut ? 'urut ASC, id ASC' : 'id ASC';
    $selectFields = $hasUrut ? "urut," : "";
    $st=$db->prepare("
      SELECT tahun,
             {$selectFields}
             TRIM(REPLACE(negara, CHAR(160), ' ')) AS negara,
             jumlah_ton, nilai_usd
      FROM ekspor_perikanan_ringkasan
      WHERE tahun = ?
      ORDER BY {$orderBy}
    ");
    $st->execute([$Y]);
    foreach ($st as $r){
      $negara = norm_label((string)$r['negara']);
      $ring[]=[
        'tahun'=>$Y,
        'urut'=>$hasUrut ? (isset($r['urut']) ? (int)$r['urut'] : null) : null,
        'negara'=>$negara,
        'jumlah_ton'=>isset($r['jumlah_ton']) && $r['jumlah_ton']!=='' && $r['jumlah_ton']!==null ? (float)$r['jumlah_ton'] : 0.0,
        'nilai_usd' =>isset($r['nilai_usd']) && $r['nilai_usd']!=='' && $r['nilai_usd']!==null ? (float)$r['nilai_usd'] : 0.0,
      ];
    }
  }

  ok(['total'=>$total, 'utama'=>$utama, 'ring'=>$ring]);

} catch (Throwable $e) {
  error_log('Ekspor fetch error: ' . $e->getMessage());
  bad('Fetch error: '.$e->getMessage(), 500);
}
?>