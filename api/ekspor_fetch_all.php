<?php
declare(strict_types=1);

// Mulai output buffering untuk mencegah output sebelum JSON
ob_start();

// Tambahkan CORS headers untuk kompatibilitas frontend React
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Error handling - jangan tampilkan error ke output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

require_once __DIR__ . '/db.php';

/* Kompat lama: sediakan pdo() jika db.php expose $pdo */
if (!function_exists('pdo')) {
  function pdo(): PDO { /** @var PDO $pdo */ global $pdo; return $pdo; }
}

/* ==== Helper Functions - DIDEFINISIKAN DULU ==== */
function ok(array $data): never {
  // Bersihkan output buffer sebelumnya
  if (ob_get_length()) {
    ob_end_clean();
  }
  // Tambahkan JSON_NUMERIC_CHECK agar angka dikirim sebagai number, bukan string
  echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_NUMERIC_CHECK);
  exit;
}

function bad(string $msg, int $code=200): never {
  // Logging error untuk debugging
  error_log('Ekspor fetch all error: ' . $msg);
  
  // Bersihkan output buffer sebelumnya
  if (ob_get_length()) {
    ob_end_clean();
  }
  
  http_response_code($code);
  echo json_encode([
    'ok'=>false,
    'error'=>$msg,
    'available_years'=>[],
    'yearly_data'=>[]
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_NUMERIC_CHECK);
  exit;
}

function norm_label(string $s): string {
  $s = str_replace("\xC2\xA0", ' ', $s); // NBSP -> space
  $s = str_replace("\xA0", ' ', $s);     // NBSP alternatif
  return trim(preg_replace('/\s+/u',' ', $s));
}

function tableExists(PDO $db, string $t): bool {
  try {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->execute([$t]);
    return (int)$st->fetchColumn() > 0;
  } catch (Exception $e) {
    return false;
  }
}

function columnExists(PDO $db, string $t, string $c): bool {
  try {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $st->execute([$t,$c]);
    return (int)$st->fetchColumn() > 0;
  } catch (Exception $e) {
    return false;
  }
}

/* ==== MAIN ==== */
try {
  $db = pdo();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  /* ---------- GET ALL AVAILABLE YEARS ---------- */
  $available_years = [];
  
  // Cek dari tabel ekspor_perikanan_total
  if (tableExists($db, 'ekspor_perikanan_total')) {
    $st = $db->query("
      SELECT DISTINCT tahun 
      FROM ekspor_perikanan_total 
      WHERE tahun IS NOT NULL 
      ORDER BY tahun DESC
    ");
    $available_years = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'tahun');
    $available_years = array_map('intval', $available_years);
  }

  // Jika tidak ada tahun, return empty
  if (empty($available_years)) {
    ok(['available_years' => [], 'yearly_data' => []]);
  }

  /* ---------- GET YEARLY DATA ---------- */
  $yearly_data = [];

  foreach ($available_years as $tahun) {
    $Y = (int)$tahun;

    /* ---------- TOTAL PER TAHUN ---------- */
    $total_volume = 0.0;
    $total_nilai_usd = 0.0;
    $komoditas_list = [];

    if (tableExists($db, 'ekspor_perikanan_total')) {
      // Get total volume dan nilai
      $st = $db->prepare("
        SELECT 
          SUM(CASE WHEN volume_ton IS NOT NULL AND volume_ton != '' THEN volume_ton ELSE 0 END) as total_volume,
          SUM(CASE WHEN nilai_usd IS NOT NULL AND nilai_usd != '' THEN nilai_usd ELSE 0 END) as total_nilai
        FROM ekspor_perikanan_total
        WHERE tahun = ?
      ");
      $st->execute([$Y]);
      $totals = $st->fetch(PDO::FETCH_ASSOC);
      
      $total_volume = isset($totals['total_volume']) ? (float)$totals['total_volume'] : 0.0;
      $total_nilai_usd = isset($totals['total_nilai']) ? (float)$totals['total_nilai'] : 0.0;

      // Get all komoditas untuk tabel
      $st = $db->prepare("
        SELECT 
          TRIM(REPLACE(komoditas, CHAR(160), ' ')) AS komoditas,
          CASE WHEN volume_ton IS NOT NULL AND volume_ton != '' THEN volume_ton ELSE 0 END as volume_ton,
          CASE WHEN nilai_usd IS NOT NULL AND nilai_usd != '' THEN nilai_usd ELSE 0 END as nilai_usd
        FROM ekspor_perikanan_total
        WHERE tahun = ?
        ORDER BY volume_ton DESC
      ");
      $st->execute([$Y]);
      foreach ($st as $r) {
        $komoditas_list[] = [
          'komoditas' => norm_label((string)$r['komoditas']),
          'volume_ton' => (float)$r['volume_ton'],
          'nilai_usd' => (float)$r['nilai_usd']
        ];
      }
    }

    /* ---------- TOP NEGARA PER TAHUN ---------- */
    $top_negara = [];
    
    if (tableExists($db, 'ekspor_perikanan_ringkasan')) {
      $hasUrut = columnExists($db, 'ekspor_perikanan_ringkasan', 'urut');
      $orderBy = $hasUrut ? 'jumlah_ton DESC, urut ASC' : 'jumlah_ton DESC';
      
      $st = $db->prepare("
        SELECT 
          TRIM(REPLACE(negara, CHAR(160), ' ')) AS negara,
          CASE WHEN jumlah_ton IS NOT NULL AND jumlah_ton != '' THEN jumlah_ton ELSE 0 END as jumlah_ton,
          CASE WHEN nilai_usd IS NOT NULL AND nilai_usd != '' THEN nilai_usd ELSE 0 END as nilai_usd
        FROM ekspor_perikanan_ringkasan
        WHERE tahun = ?
        ORDER BY {$orderBy}
        LIMIT 10
      ");
      $st->execute([$Y]);
      foreach ($st as $r) {
        $top_negara[] = [
          'negara' => norm_label((string)$r['negara']),
          'jumlah_ton' => (float)$r['jumlah_ton'],
          'nilai_usd' => (float)$r['nilai_usd']
        ];
      }
    }

    /* ---------- KOMODITAS CHART (TOP 5 + LAINNYA) ---------- */
    $komoditas_chart = [];
    
    if (count($komoditas_list) > 0) {
      $top5 = array_slice($komoditas_list, 0, 5);
      $other_volume = 0.0;
      $other_nilai = 0.0;
      
      // Hitung total untuk komoditas lainnya
      $remaining = array_slice($komoditas_list, 5);
      foreach ($remaining as $k) {
        $other_volume += $k['volume_ton'];
        $other_nilai += $k['nilai_usd'];
      }
      
      $komoditas_chart = $top5;
      
      // Tambahkan "Lainnya" jika ada komoditas lain
      if ($other_volume > 0 || $other_nilai > 0) {
        $komoditas_chart[] = [
          'komoditas' => 'Lainnya',
          'volume_ton' => $other_volume,
          'nilai_usd' => $other_nilai
        ];
      }
    }

    $yearly_data[] = [
      'tahun' => $Y,
      'total_volume' => $total_volume,
      'total_nilai_usd' => $total_nilai_usd,
      'komoditas' => $komoditas_list,        // Semua komoditas untuk tabel detail
      'komoditas_chart' => $komoditas_chart, // Top 5 + Lainnya untuk chart
      'top_negara' => $top_negara            // Top 10 negara untuk visualisasi
    ];
  }

  // Return success
  ok([
    'available_years' => $available_years,
    'yearly_data' => $yearly_data
  ]);

} catch (Throwable $e) {
  // Logging error untuk debugging
  error_log('Ekspor fetch all error: ' . $e->getMessage());
  error_log('Stack trace: ' . $e->getTraceAsString());
  
  // Return error response
  bad('Fetch error: ' . $e->getMessage(), 500);
}
?>