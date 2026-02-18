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

if (!function_exists('pdo')) {
  function pdo(): PDO { /** @var PDO $pdo */ global $pdo; return $pdo; }
}

/* ===== Helpers ===== */
function ok(array $data = []): never {
  echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
  exit;
}

function bad(string $msg, int $code = 200): never {
  http_response_code($code);
  echo json_encode([
    'ok'         => false,
    'message'    => $msg,
    'tahun'      => null,
    'aki'        => [],
    'pemasaran'  => [],
    'olahankab'  => [],
    'olahjenis'  => [],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function norm_label(string $s): string {
  $s = str_replace(["\xC2\xA0", "\xA0"], ' ', $s);
  return trim(preg_replace('/\s+/u', ' ', $s));
}

function safeFloat($value): float {
  if ($value === null || $value === '' || $value === '-' || $value === 'N/A') {
    return 0.0;
  }
  return floatval($value);
}

function tableExists(PDO $db, string $t): bool {
  try {
    $st = $db->prepare(
      "SELECT COUNT(*) FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $st->execute([$t]);
    return ((int)$st->fetchColumn()) > 0;
  } catch (Throwable $e) {
    return false;
  }
}

/* ===== Main ===== */
try {
  $tahun = $_GET['tahun'] ?? null;
  if ($tahun === null || !preg_match('/^\d{4}$/', (string)$tahun)) {
    bad('Parameter tahun wajib (format YYYY).');
  }
  $Y = (int)$tahun;

  $db = pdo();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  /* ========================================
     AKI per KAB/KOTA
     ======================================== */
  $aki = [];
  if (tableExists($db, 'pengolahan_pemasaran_aki')) {
    $st = $db->prepare("
      SELECT tahun,
             TRIM(REPLACE(kab_kota, CHAR(160), ' ')) AS kab_kota,
             kidrt, kilrt, ktt, aki
      FROM pengolahan_pemasaran_aki
      WHERE tahun = ?
      ORDER BY kab_kota ASC
    ");
    $st->execute([$Y]);
    foreach ($st as $r) {
      $aki[] = [
        'tahun'    => $Y,
        'kab_kota' => norm_label((string)$r['kab_kota']),
        'kidrt'    => safeFloat($r['kidrt'] ?? 0),
        'kilrt'    => safeFloat($r['kilrt'] ?? 0),
        'ktt'      => safeFloat($r['ktt'] ?? 0),
        'aki'      => safeFloat($r['aki'] ?? 0),
      ];
    }
  }

  /* ========================================
     PEMASARAN
     ======================================== */
  $pemasaran = [];
  $pemasaran_table = 'pengolahan_pemasaran_pemasaran';
  
  if (tableExists($db, $pemasaran_table)) {
    $st = $db->prepare("
      SELECT tahun,
             TRIM(REPLACE(kab_kota, CHAR(160), ' ')) AS kab_kota,
             pengecer, pengumpul, jumlah_unit
      FROM {$pemasaran_table}
      WHERE tahun = ?
      ORDER BY kab_kota ASC
    ");
    $st->execute([$Y]);
    foreach ($st as $r) {
      $pemasaran[] = [
        'tahun'       => $Y,
        'kab_kota'    => norm_label((string)$r['kab_kota']),
        'pengecer'    => safeFloat($r['pengecer']),
        'pengumpul'   => safeFloat($r['pengumpul']),
        'jumlah_unit' => safeFloat($r['jumlah_unit']),
      ];
    }
  } else {
    error_log("Tabel {$pemasaran_table} tidak ditemukan di database");
  }

  /* ========================================
     OLAHAN per KAB/KOTA
     ======================================== */
  $olahankab = [];
  if (tableExists($db, 'pengolahan_pemasaran_olahankab')) {
    $st = $db->prepare("
      SELECT tahun,
             TRIM(REPLACE(kab_kota, CHAR(160), ' ')) AS kab_kota,
             fermentasi,
             pelumatan_daging_ikan,
             pembekuan,
             pemindangan,
             penanganan_produk_segar,
             pengalengan,
             pengasapan_pemanggangan,
             pereduksian_ekstraksi,
             penggaraman_pengeringan,
             pengolahan_lainnya,
             jumlah_unit
      FROM pengolahan_pemasaran_olahankab
      WHERE tahun = ?
      ORDER BY kab_kota ASC
    ");
    $st->execute([$Y]);
    foreach ($st as $r) {
      $olahankab[] = [
        'tahun'                      => $Y,
        'kab_kota'                   => norm_label((string)$r['kab_kota']),
        'fermentasi'                 => safeFloat($r['fermentasi']),
        'pelumatan_daging_ikan'      => safeFloat($r['pelumatan_daging_ikan']),
        'pembekuan'                  => safeFloat($r['pembekuan']),
        'pemindangan'                => safeFloat($r['pemindangan']),
        'penanganan_produk_segar'    => safeFloat($r['penanganan_produk_segar']),
        'pengalengan'                => safeFloat($r['pengalengan']),
        'pengasapan_pemanggangan'    => safeFloat($r['pengasapan_pemanggangan']),
        'pereduksian_ekstraksi'      => safeFloat($r['pereduksian_ekstraksi']),
        'penggaraman_pengeringan'    => safeFloat($r['penggaraman_pengeringan']),
        'pengolahan_lainnya'         => safeFloat($r['pengolahan_lainnya']),
        'jumlah_unit'                => safeFloat($r['jumlah_unit']),
      ];
    }
  }

  /* ========================================
     OLAHAN menurut JENIS
     ======================================== */
  $olahjenis = [];
  if (tableExists($db, 'pengolahan_pemasaran_olahjenis')) {
    $st = $db->prepare("
      SELECT tahun,
             TRIM(REPLACE(jenis_kegiatan_pengolahan, CHAR(160), ' ')) AS jenis_kegiatan_pengolahan,
             jumlah_upi
      FROM pengolahan_pemasaran_olahjenis
      WHERE tahun = ?
      ORDER BY jenis_kegiatan_pengolahan ASC
    ");
    $st->execute([$Y]);
    foreach ($st as $r) {
      $olahjenis[] = [
        'tahun'                     => $Y,
        'jenis_kegiatan_pengolahan' => norm_label((string)$r['jenis_kegiatan_pengolahan']),
        'jumlah_upi'                => safeFloat($r['jumlah_upi']),
      ];
    }
  }

  if (empty($pemasaran) && empty($olahankab)) {
    ok([
      'tahun'     => $Y,
      'aki'       => [],
      'pemasaran' => [],
      'olahankab' => [],
      'olahjenis' => [],
    ]);
  }

  ok([
    'tahun'     => $Y,
    'aki'       => $aki,
    'pemasaran' => $pemasaran,
    'olahankab' => $olahankab,
    'olahjenis' => $olahjenis,
  ]);

} catch (PDOException $e) {
  error_log("Database error: " . $e->getMessage());
  bad('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
  error_log("Unexpected error: " . $e->getMessage());
  bad('Fetch error: ' . $e->getMessage(), 500);
}
?>