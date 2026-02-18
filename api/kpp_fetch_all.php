<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

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

  $sel = [];
  $sel[] = "tahun";
  $sel[] = "kab_kota";

  // Luas lahan -> kirim sebagai 'luas_lahan_ha'
  if (!empty($have['l_total_ha'])) {
    $sel[] = "l_total_ha AS luas_lahan_ha";
  } elseif (!empty($have['luas_lahan_ha'])) {
    $sel[] = "luas_lahan_ha AS luas_lahan_ha";
  } else {
    $sel[] = "0 AS luas_lahan_ha";
  }

  // Petambak -> kirim sebagai 'jumlah_petambak' (FIXED: missing opening quote)
  if (!empty($have['sigma_petambak'])) {
    $sel[] = "sigma_petambak AS jumlah_petambak";
  } elseif (!empty($have['jumlah_petambak'])) {
    $sel[] = "jumlah_petambak AS jumlah_petambak";
  } else {
    $sel[] = "0 AS jumlah_petambak"; // ✅ FIXED: added missing opening quote
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

  // Ambil semua data yang bukan PT Garam
  $sql = "SELECT ".implode(', ', $sel)." FROM kpp_garam WHERE kab_kota != 'PT Garam' ORDER BY tahun DESC, kab_kota ASC";
  $st = $pdo->prepare($sql);
  $st->execute();
  $allData = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Ambil tahun-tahun yang tersedia
  $years = array_unique(array_column($allData, 'tahun'));
  rsort($years); // Urutkan descending

  // Hitung summary per tahun (exclude PT Garam)
  $yearlySummary = [];
  foreach ($years as $year) {
    $yearData = array_filter($allData, function($row) use ($year) {
      return $row['tahun'] == $year;
    });
    
    $luasLahan = array_sum(array_map(function($row) {
      return (float)($row['luas_lahan_ha'] ?? 0);
    }, $yearData));
    
    $jumlahKelompok = array_sum(array_map(function($row) {
      return (int)($row['jumlah_kelompok'] ?? 0);
    }, $yearData));
    
    $jumlahPetambak = array_sum(array_map(function($row) {
      return (int)($row['jumlah_petambak'] ?? 0);
    }, $yearData));
    
    $volumeProduksi = array_sum(array_map(function($row) {
      return (float)($row['volume_produksi_ton'] ?? 0);
    }, $yearData));
    
    // Hitung growth dari tahun sebelumnya
    $prevYearData = array_filter($allData, function($row) use ($year) {
      return $row['tahun'] == ($year - 1);
    });
    
    $prevLuasLahan = $prevYearData ? array_sum(array_map(function($row) {
      return (float)($row['luas_lahan_ha'] ?? 0);
    }, $prevYearData)) : $luasLahan;
    
    $prevKelompok = $prevYearData ? array_sum(array_map(function($row) {
      return (int)($row['jumlah_kelompok'] ?? 0);
    }, $prevYearData)) : $jumlahKelompok;
    
    $prevPetambak = $prevYearData ? array_sum(array_map(function($row) {
      return (int)($row['jumlah_petambak'] ?? 0);
    }, $prevYearData)) : $jumlahPetambak;
    
    $prevVolume = $prevYearData ? array_sum(array_map(function($row) {
      return (float)($row['volume_produksi_ton'] ?? 0);
    }, $prevYearData)) : $volumeProduksi;
    
    $growthLuasLahan = $prevLuasLahan > 0 ? (($luasLahan - $prevLuasLahan) / $prevLuasLahan) * 100 : 0;
    $growthKelompok = $prevKelompok > 0 ? (($jumlahKelompok - $prevKelompok) / $prevKelompok) * 100 : 0;
    $growthPetambak = $prevPetambak > 0 ? (($jumlahPetambak - $prevPetambak) / $prevPetambak) * 100 : 0;
    $growthVolume = $prevVolume > 0 ? (($volumeProduksi - $prevVolume) / $prevVolume) * 100 : 0;
    
    $yearlySummary[] = [
      'tahun' => (int)$year,
      'luas_lahan_ha' => round($luasLahan, 2),
      'jumlah_kelompok' => (int)$jumlahKelompok,
      'jumlah_petambak' => (int)$jumlahPetambak,
      'volume_produksi_ton' => round($volumeProduksi, 2),
      'growth_luas_lahan' => round($growthLuasLahan, 2),
      'growth_kelompok' => round($growthKelompok, 2),
      'growth_petambak' => round($growthPetambak, 2),
      'growth_volume' => round($growthVolume, 2),
    ];
  }

  ok([
    'available_years' => array_values($years),
    'yearly_data' => $yearlySummary,
    'all_data' => $allData
  ]);

} catch (Throwable $e) {
  bad('Fetch error: '.$e->getMessage(), 500);
}