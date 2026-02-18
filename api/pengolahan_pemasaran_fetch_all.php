<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

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

  // Cek kolom yang tersedia di tabel pemasaran
  $qColsPemasaran = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengolahan_pemasaran_pemasaran'
  ");
  $qColsPemasaran->execute();
  $havePemasaran = array_fill_keys(array_map('strval', $qColsPemasaran->fetchAll(PDO::FETCH_COLUMN) ?: []), true);

  // Cek kolom yang tersedia di tabel olahankab
  $qColsOlahan = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengolahan_pemasaran_olahankab'
  ");
  $qColsOlahan->execute();
  $haveOlahan = array_fill_keys(array_map('strval', $qColsOlahan->fetchAll(PDO::FETCH_COLUMN) ?: []), true);

  // Ambil semua data pemasaran
  $sqlPemasaran = "SELECT * FROM pengolahan_pemasaran_pemasaran ORDER BY tahun DESC, kab_kota ASC";
  $stPemasaran = $pdo->prepare($sqlPemasaran);
  $stPemasaran->execute();
  $allPemasaran = $stPemasaran->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Ambil semua data pengolahan
  $sqlOlahan = "SELECT * FROM pengolahan_pemasaran_olahankab ORDER BY tahun DESC, kab_kota ASC";
  $stOlahan = $pdo->prepare($sqlOlahan);
  $stOlahan->execute();
  $allOlahan = $stOlahan->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Ambil tahun-tahun yang tersedia (dari kedua tabel)
  $yearsPemasaran = array_unique(array_column($allPemasaran, 'tahun'));
  $yearsOlahan = array_unique(array_column($allOlahan, 'tahun'));
  $allYears = array_unique(array_merge($yearsPemasaran, $yearsOlahan));
  rsort($allYears); // Urutkan descending

  // Hitung summary per tahun untuk pemasaran
  $yearlySummaryPemasaran = [];
  foreach ($allYears as $year) {
    $yearData = array_filter($allPemasaran, function($row) use ($year) {
      return $row['tahun'] == $year;
    });
    
    $totalPengecer = array_sum(array_map(function($row) {
      return (int)($row['pengecer'] ?? 0);
    }, $yearData));
    
    $totalPengumpul = array_sum(array_map(function($row) {
      return (int)($row['pengumpul'] ?? 0);
    }, $yearData));
    
    $totalUnit = array_sum(array_map(function($row) {
      return (int)($row['jumlah_unit'] ?? 0);
    }, $yearData));
    
    $yearlySummaryPemasaran[$year] = [
      'tahun' => (int)$year,
      'total_pengecer' => (int)$totalPengecer,
      'total_pengumpul' => (int)$totalPengumpul,
      'total_unit' => (int)$totalUnit,
      // Hapus growth calculation
    ];
  }

  // Hitung summary per tahun untuk pengolahan
  $yearlySummaryOlahan = [];
  foreach ($allYears as $year) {
    $yearData = array_filter($allOlahan, function($row) use ($year) {
      return $row['tahun'] == $year;
    });
    
    $totalUnit = array_sum(array_map(function($row) {
      return (int)($row['jumlah_unit'] ?? 0);
    }, $yearData));
    
    // Hitung total untuk setiap jenis pengolahan
    $fermentasi = array_sum(array_map(function($row) {
      return (int)($row['fermentasi'] ?? 0);
    }, $yearData));
    
    $pelumatan = array_sum(array_map(function($row) {
      return (int)($row['pelumatan_daging_ikan'] ?? 0);
    }, $yearData));
    
    $pembekuan = array_sum(array_map(function($row) {
      return (int)($row['pembekuan'] ?? 0);
    }, $yearData));
    
    $pemindangan = array_sum(array_map(function($row) {
      return (int)($row['pemindangan'] ?? 0);
    }, $yearData));
    
    $penanganan = array_sum(array_map(function($row) {
      return (int)($row['penanganan_produk_segar'] ?? 0);
    }, $yearData));
    
    $pengalengan = array_sum(array_map(function($row) {
      return (int)($row['pengalengan'] ?? 0);
    }, $yearData));
    
    $pengasapan = array_sum(array_map(function($row) {
      return (int)($row['pengasapan_pemanggangan'] ?? 0);
    }, $yearData));
    
    $pereduksian = array_sum(array_map(function($row) {
      return (int)($row['pereduksian_ekstraksi'] ?? 0);
    }, $yearData));
    
    $penggaraman = array_sum(array_map(function($row) {
      return (int)($row['penggaraman_pengeringan'] ?? 0);
    }, $yearData));
    
    $pengolahanLain = array_sum(array_map(function($row) {
      return (int)($row['pengolahan_lainnya'] ?? 0);
    }, $yearData));
    
    $yearlySummaryOlahan[$year] = [
      'tahun' => (int)$year,
      'total_unit' => (int)$totalUnit,
      'fermentasi' => (int)$fermentasi,
      'pelumatan_daging_ikan' => (int)$pelumatan,
      'pembekuan' => (int)$pembekuan,
      'pemindangan' => (int)$pemindangan,
      'penanganan_produk_segar' => (int)$penanganan,
      'pengalengan' => (int)$pengalengan,
      'pengasapan_pemanggangan' => (int)$pengasapan,
      'pereduksian_ekstraksi' => (int)$pereduksian,
      'penggaraman_pengeringan' => (int)$penggaraman,
      'pengolahan_lainnya' => (int)$pengolahanLain,
      // Hapus growth calculation
    ];
  }

  // Gabungkan summary untuk kedua bidang
  $yearlyData = [];
  foreach ($allYears as $year) {
    $yearlyData[] = [
      'tahun' => (int)$year,
      // Pemasaran
      'pemasaran_pengecer' => $yearlySummaryPemasaran[$year]['total_pengecer'] ?? 0,
      'pemasaran_pengumpul' => $yearlySummaryPemasaran[$year]['total_pengumpul'] ?? 0,
      'pemasaran_unit' => $yearlySummaryPemasaran[$year]['total_unit'] ?? 0,
      // Pengolahan
      'pengolahan_unit' => $yearlySummaryOlahan[$year]['total_unit'] ?? 0,
      'pengolahan_fermentasi' => $yearlySummaryOlahan[$year]['fermentasi'] ?? 0,
      'pengolahan_pelumatan_daging_ikan' => $yearlySummaryOlahan[$year]['pelumatan_daging_ikan'] ?? 0,
      'pengolahan_pembekuan' => $yearlySummaryOlahan[$year]['pembekuan'] ?? 0,
      'pengolahan_pemindangan' => $yearlySummaryOlahan[$year]['pemindangan'] ?? 0,
      'pengolahan_penanganan_produk_segar' => $yearlySummaryOlahan[$year]['penanganan_produk_segar'] ?? 0,
      'pengolahan_pengalengan' => $yearlySummaryOlahan[$year]['pengalengan'] ?? 0,
      'pengolahan_pengasapan_pemanggangan' => $yearlySummaryOlahan[$year]['pengasapan_pemanggangan'] ?? 0,
      'pengolahan_pereduksian_ekstraksi' => $yearlySummaryOlahan[$year]['pereduksian_ekstraksi'] ?? 0,
      'pengolahan_penggaraman_pengeringan' => $yearlySummaryOlahan[$year]['penggaraman_pengeringan'] ?? 0,
      'pengolahan_lainnya' => $yearlySummaryOlahan[$year]['pengolahan_lainnya'] ?? 0,
    ];
  }

  ok([
    'available_years' => array_values($allYears),
    'yearly_data' => $yearlyData,
    'pemasaran_data' => $allPemasaran,
    'pengolahan_data' => $allOlahan
  ]);

} catch (Throwable $e) {
  bad('Fetch error: '.$e->getMessage(), 500);
}