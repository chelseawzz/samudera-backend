<?php
// /api/investasi_fetch.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function get_pdo(): PDO {
  $candidates = [
    __DIR__ . '/db.php',
    dirname(__DIR__) . '/api/db.php',
    dirname(__DIR__) . '/db.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/db.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/db.php',
  ];
  foreach ($candidates as $p) {
    if ($p && is_file($p)) { require_once $p; break; }
  }
  if (function_exists('pdo')) {
    $p = pdo();
    if ($p instanceof PDO) return $p;
  }
  global $pdo;
  if (isset($pdo) && $pdo instanceof PDO) return $pdo;

  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db.php / PDO tidak ditemukan'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // Validasi tahun: bila kosong/invalid kembalikan payload kosong (UI akan handle)
  $tahun = isset($_GET['tahun']) ? preg_replace('/\D/', '', (string)($_GET['tahun'])) : '';
  if ($tahun === '' || strlen($tahun) !== 4) {
    echo json_encode(['ok'=>true,'sektor_total'=>[],'detail'=>[],'sumber'=>[],'bidang'=>[],'kota'=>[],'pma'=>[]], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $y = (int)$tahun;
  if ($y < 2000 || $y > 2100) {
    echo json_encode(['ok'=>true,'sektor_total'=>[],'detail'=>[],'sumber'=>[],'bidang'=>[],'kota'=>[],'pma'=>[]], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo = get_pdo();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  /* ===================== SEKTOR TOTAL ===================== */
  $st = $pdo->prepare("
    SELECT sektor, COALESCE(nilai_rp_juta,0) AS nilai_rp_juta
    FROM investasi_sektor_total
    WHERE tahun = ?
    ORDER BY nilai_rp_juta DESC, sektor ASC
  ");
  $st->execute([$y]);
  $sektor = array_map(
    static fn(array $r) => [
      'sektor'        => (string)$r['sektor'],
      'nilai_rp_juta' => (float)$r['nilai_rp_juta'],
    ],
    $st->fetchAll(PDO::FETCH_ASSOC) ?: []
  );

  /* ===================== DETAIL ===================== */
  // Alias header UPPERCASE supaya sinkron dengan renderer di investasi.php
  $sqlDetail = "
    SELECT
      tahun AS `TAHUN`,
      nama_perusahaan AS `NAMA PERUSAHAAN`,
      alamat_perusahaan AS `ALAMAT PERUSAHAAN`,
      kbli AS `KBLI`,
      bidang_usaha AS `BIDANG USAHA`,
      provinsi AS `PROVINSI`,
      kab_kota AS `KAB/KOTA`,
      negara AS `NEGARA`,
      status_perusahaan AS `STATUS`,
      triwulan AS `TRIWULAN`,
      COALESCE(nilai_investasi_rp_juta,0) AS `NILAI INVESTASI Rp JUTA`,
      COALESCE(nilai_investasi_usd_ribu,0) AS `NILAI INVESTASI US$ RIBU`
    FROM investasi_detail
    WHERE tahun = ?
    ORDER BY COALESCE(triwulan,0), nama_perusahaan
    LIMIT 10000
  ";
  $st = $pdo->prepare($sqlDetail);
  $st->execute([$y]);
  $detail = array_map(
    static function (array $r): array {
      $r['TAHUN']                         = (int)$r['TAHUN'];
      $r['TRIWULAN']                      = ($r['TRIWULAN'] === null || $r['TRIWULAN'] === '') ? null : (int)$r['TRIWULAN'];
      $r['NILAI INVESTASI Rp JUTA']       = (float)$r['NILAI INVESTASI Rp JUTA'];
      $r['NILAI INVESTASI US$ RIBU']      = (float)$r['NILAI INVESTASI US$ RIBU'];
      return $r;
    },
    $st->fetchAll(PDO::FETCH_ASSOC) ?: []
  );

  /* ===================== REKAP GENERIK (Rp Juta, apa adanya) ===================== */
  $fetchRekap = static function(string $table, string $labelCol) use ($pdo, $y): array {
    $sql = "SELECT {$labelCol} AS label,
                   COALESCE(q1,0) AS q1, COALESCE(q2,0) AS q2,
                   COALESCE(q3,0) AS q3, COALESCE(q4,0) AS q4
            FROM {$table}
            WHERE tahun = ?
            ORDER BY {$labelCol} ASC";
    $st = $pdo->prepare($sql);
    $st->execute([$y]);
    return array_map(
      static fn(array $r): array => [
        'label' => (string)$r['label'],
        'q1'    => (float)$r['q1'],
        'q2'    => (float)$r['q2'],
        'q3'    => (float)$r['q3'],
        'q4'    => (float)$r['q4'],
      ],
      $st->fetchAll(PDO::FETCH_ASSOC) ?: []
    );
  };

  $sumber = $fetchRekap('investasi_rekap_sumber','sumber');
  $bidang = $fetchRekap('investasi_rekap_bidang','bidang_usaha');
  $kota   = $fetchRekap('investasi_rekap_kota','kab_kota');
  $pma    = $fetchRekap('investasi_rekap_pma_negara','negara');

  echo json_encode(
    [
      'ok'          => true,
      'sektor_total'=> $sektor,
      'detail'      => $detail,
      'sumber'      => $sumber,
      'bidang'      => $bidang,
      'kota'        => $kota,
      'pma'         => $pma,
    ],
    JSON_UNESCAPED_UNICODE
  );

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Fetch error: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
