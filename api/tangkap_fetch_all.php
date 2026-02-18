<?php
// /api/tangkap_fetch_all.php
declare(strict_types=1);

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

function ok(array $x): never {
  echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit();
}

$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  $out = [
    'ok' => true,
    'available_years' => [],
    'yearly_data' => []
  ];

  /* =================== GET ALL AVAILABLE YEARS =================== */
  $years = [];
  
  // Check tangkap_ringkasan table
  if ($pdo->query("SHOW TABLES LIKE 'tangkap_ringkasan'")->fetch()) {
    $st = $pdo->query("SELECT DISTINCT tahun FROM tangkap_ringkasan ORDER BY tahun DESC");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $years[] = (int)$r['tahun'];
    }
  }
  
  // Check tangkap_wilayah table
  if ($pdo->query("SHOW TABLES LIKE 'tangkap_wilayah'")->fetch()) {
    $st = $pdo->query("SELECT DISTINCT tahun FROM tangkap_wilayah ORDER BY tahun DESC");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      if (!in_array((int)$r['tahun'], $years)) {
        $years[] = (int)$r['tahun'];
      }
    }
  }
  
  // Remove duplicates and sort
  $years = array_unique($years);
  sort($years);
  $out['available_years'] = $years;

  /* =================== FETCH DATA FOR EACH YEAR =================== */
  foreach ($years as $tahun) {
    $yearData = [
      'tahun' => $tahun,
      'nelayan_total' => 0,
      'nelayan_laut' => 0,
      'nelayan_pud' => 0,
      'armada_total' => 0,
      'armada_laut' => 0,
      'armada_pud' => 0,
      'alat_tangkap_total' => 0,
      'alat_tangkap_laut' => 0,
      'alat_tangkap_pud' => 0,
      'rtp_pp_total' => 0,
      'rtp_pp_laut' => 0,
      'rtp_pp_pud' => 0,
      'volume_total' => 0.0,
      'nilai_total' => 0.0
    ];

    /* -------- RINGKASAN DATA -------- */
if ($pdo->query("SHOW TABLES LIKE 'tangkap_ringkasan'")->fetch()) {
  $st = $pdo->prepare("
    SELECT 
      nelayan_orang,
      armada_buah,
      alat_tangkap_unit,
      rtp_pp,
      volume_ton,
      nilai_rp_1000
    FROM tangkap_ringkasan
    WHERE tahun = ? AND cabang_usaha = 'JUMLAH - Total'
  ");
  $st->execute([$tahun]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  
  if ($r) {
    $yearData['nelayan_total'] = (int)$r['nelayan_orang'];
    $yearData['armada_total'] = (int)$r['armada_buah'];
    $yearData['alat_tangkap_total'] = (int)$r['alat_tangkap_unit'];
    $yearData['rtp_pp_total'] = (int)$r['rtp_pp'];
    $yearData['volume_total'] = (float)$r['volume_ton'];
    $yearData['nilai_total'] = (float)$r['nilai_rp_1000'];
  }
}


    /* -------- WILAYAH DATA (Laut vs PUD) -------- */
    if ($pdo->query("SHOW TABLES LIKE 'tangkap_wilayah'")->fetch()) {
      $st = $pdo->prepare("
        SELECT 
          SUM(CASE WHEN jenis_perairan = 'Laut' THEN nelayan_orang ELSE 0 END) AS nelayan_laut,
          SUM(CASE WHEN jenis_perairan = 'Perairan Umum Darat' THEN nelayan_orang ELSE 0 END) AS nelayan_pud,
          SUM(CASE WHEN jenis_perairan = 'Laut' THEN armada_buah ELSE 0 END) AS armada_laut,
          SUM(CASE WHEN jenis_perairan = 'Perairan Umum Darat' THEN armada_buah ELSE 0 END) AS armada_pud,
          SUM(CASE WHEN jenis_perairan = 'Laut' THEN alat_tangkap_unit ELSE 0 END) AS alat_laut,
          SUM(CASE WHEN jenis_perairan = 'Perairan Umum Darat' THEN alat_tangkap_unit ELSE 0 END) AS alat_pud,
          SUM(CASE WHEN jenis_perairan = 'Laut' THEN rtp_pp ELSE 0 END) AS rtp_laut,
          SUM(CASE WHEN jenis_perairan = 'Perairan Umum Darat' THEN rtp_pp ELSE 0 END) AS rtp_pud
        FROM tangkap_wilayah
        WHERE tahun = ?
      ");
      $st->execute([$tahun]);
      $r = $st->fetch(PDO::FETCH_ASSOC);
      
      if ($r) {
        $yearData['nelayan_laut'] = (int)$r['nelayan_laut'];
        $yearData['nelayan_pud'] = (int)$r['nelayan_pud'];
        $yearData['armada_laut'] = (int)$r['armada_laut'];
        $yearData['armada_pud'] = (int)$r['armada_pud'];
        $yearData['alat_tangkap_laut'] = (int)$r['alat_laut'];
        $yearData['alat_tangkap_pud'] = (int)$r['alat_pud'];
        $yearData['rtp_pp_laut'] = (int)$r['rtp_laut'];
        $yearData['rtp_pp_pud'] = (int)$r['rtp_pud'];
      }
    }

    $out['yearly_data'][] = $yearData;
  }

  /* =================== CALCULATE GROWTH (YEAR-OVER-YEAR) =================== */
  $withGrowth = [];
  for ($i = 0; $i < count($out['yearly_data']); $i++) {
    $current = $out['yearly_data'][$i];
    
    if ($i === 0) {
      // First year has no growth
      $withGrowth[] = array_merge($current, [
        'growth_nelayan' => 0.0,
        'growth_armada' => 0.0,
        'growth_alat' => 0.0,
        'growth_rtp' => 0.0,
        'growth_volume' => 0.0,
        'growth_nilai' => 0.0
      ]);
    } else {
      $prev = $out['yearly_data'][$i - 1];
      
      $growthNelayan = $prev['nelayan_total'] > 0 
        ? (($current['nelayan_total'] - $prev['nelayan_total']) / $prev['nelayan_total']) * 100 
        : 0;
      $growthArmada = $prev['armada_total'] > 0 
        ? (($current['armada_total'] - $prev['armada_total']) / $prev['armada_total']) * 100 
        : 0;
      $growthAlat = $prev['alat_tangkap_total'] > 0 
        ? (($current['alat_tangkap_total'] - $prev['alat_tangkap_total']) / $prev['alat_tangkap_total']) * 100 
        : 0;
      $growthRtp = $prev['rtp_pp_total'] > 0 
        ? (($current['rtp_pp_total'] - $prev['rtp_pp_total']) / $prev['rtp_pp_total']) * 100 
        : 0;
      $growthVolume = $prev['volume_total'] > 0 
        ? (($current['volume_total'] - $prev['volume_total']) / $prev['volume_total']) * 100 
        : 0;
      $growthNilai = $prev['nilai_total'] > 0 
        ? (($current['nilai_total'] - $prev['nilai_total']) / $prev['nilai_total']) * 100 
        : 0;
      
      $withGrowth[] = array_merge($current, [
        'growth_nelayan' => round($growthNelayan, 2),
        'growth_armada' => round($growthArmada, 2),
        'growth_alat' => round($growthAlat, 2),
        'growth_rtp' => round($growthRtp, 2),
        'growth_volume' => round($growthVolume, 2),
        'growth_nilai' => round($growthNilai, 2)
      ]);
    }
  }
  
  $out['yearly_data'] = $withGrowth;

  ok($out);

} catch (Throwable $e) {
  ok([
    'ok' => false,
    'message' => $e->getMessage(),
    'available_years' => [],
    'yearly_data' => []
  ]);
}
?>