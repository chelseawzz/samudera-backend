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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Length: 0');
    http_response_code(204);
    exit(0);
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

function ok(array $x): never {
  echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function iyear($v): int { $y=(int)$v; return ($y>=2000 && $y<=2100) ? $y : 0; }

$y = isset($_GET['tahun']) ? iyear($_GET['tahun']) : 0;

$empty = [
  'ringkasan'      => [],
  'matrix'         => ['rows' => []],
  'volume_bulanan' => [],
  'nilai_bulanan'  => [],
  'komoditas'      => [],
];
if ($y === 0) ok($empty);

$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$out = $empty;

try {
  /* =================== RINGKASAN =================== */
  if ($pdo->query("SHOW TABLES LIKE 'tangkap_ringkasan'")->fetch()) {
    $st = $pdo->prepare("
      SELECT cabang_usaha, nelayan_orang, rtp_pp, armada_buah, alat_tangkap_unit, volume_ton, nilai_rp_1000
      FROM tangkap_ringkasan
      WHERE tahun = ?
      ORDER BY cabang_usaha
    ");
    $st->execute([$y]);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $out['ringkasan'][] = [
        'CABANG USAHA'            => (string)$r['cabang_usaha'],
        'Nelayan (Orang)'         => (int)   $r['nelayan_orang'],
        'RTP/PP (Orang/Unit)'     => (int)   $r['rtp_pp'],
        'Armada Perikanan (Buah)' => (int)   $r['armada_buah'],
        'Alat Tangkap (Unit)'     => (int)   $r['alat_tangkap_unit'],
        'Volume (Ton)'            => (float) $r['volume_ton'],
        'Nilai (Rp 1.000)'        => (float) $r['nilai_rp_1000'],
      ];
    }
  }

  /* =================== MATRIX =================== */
  if ($pdo->query("SHOW TABLES LIKE 'tangkap_wilayah'")->fetch()) {
    $st = $pdo->prepare("
      SELECT 
        kab_kota,
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
      GROUP BY kab_kota
      ORDER BY kab_kota
    ");
    $st->execute([$y]);

    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $out['matrix']['rows'][] = [
        'Wilayah' => $r['kab_kota'],
        'Nelayan Laut' => (int)$r['nelayan_laut'],
        'Nelayan PUD' => (int)$r['nelayan_pud'],
        'Armada Laut' => (int)$r['armada_laut'],
        'Armada PUD' => (int)$r['armada_pud'],
        'Alat Laut' => (int)$r['alat_laut'],
        'Alat PUD' => (int)$r['alat_pud'],
        'RTP Laut' => (int)$r['rtp_laut'],
        'RTP PUD' => (int)$r['rtp_pud']
      ];
    }
  }
/* =================== PRODUKSI PER WILAYAH (UNTUK PETA) =================== */
$produksiWilayah = [];
if ($pdo->query("SHOW TABLES LIKE 'tangkap_produksi_matrix'")->fetch()) {
  $st = $pdo->prepare("
    SELECT 
      kab_kota,
      SUM(CASE WHEN subsektor = 'Laut - Non Pelabuhan' THEN volume_ton ELSE 0 END) AS laut,
      SUM(CASE WHEN subsektor = 'Perairan Umum - Open Water' THEN volume_ton ELSE 0 END) AS pud,
      SUM(CASE WHEN subsektor != 'JUMLAH - Total' THEN volume_ton ELSE 0 END) AS total
    FROM tangkap_produksi_matrix
    WHERE tahun = ?
    GROUP BY kab_kota
    ORDER BY kab_kota
  ");
  $st->execute([$y]);
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $produksiWilayah[] = [
      'wilayah' => $r['kab_kota'],
      'laut' => (float)$r['laut'],
      'pud' => (float)$r['pud'],
      'total' => (float)$r['total']
    ];
  }
}
$out['produksi_wilayah'] = $produksiWilayah;

  /* =================== VOLUME BULANAN =================== */
  if ($pdo->query("SHOW TABLES LIKE 'tangkap_volume_bulanan'")->fetch()) {
    $st = $pdo->prepare("
      SELECT uraian,januari,februari,maret,april,mei,juni,juli,agustus,september,oktober,november,desember,jumlah
      FROM tangkap_volume_bulanan
      WHERE tahun = ?
      ORDER BY uraian
    ");
    $st->execute([$y]);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $out['volume_bulanan'][] = [
        'Uraian'    => (string)$r['uraian'],
        'Januari'   => (float) $r['januari'],
        'Februari'  => (float) $r['februari'],
        'Maret'     => (float) $r['maret'],
        'April'     => (float) $r['april'],
        'Mei'       => (float) $r['mei'],
        'Juni'      => (float) $r['juni'],
        'Juli'      => (float) $r['juli'],
        'Agustus'   => (float) $r['agustus'],
        'September' => (float) $r['september'],
        'Oktober'   => (float) $r['oktober'],
        'November'  => (float) $r['november'],
        'Desember'  => (float) $r['desember'],
        'Jumlah'    => (float) $r['jumlah'],
      ];
    }
  }

  /* =================== NILAI BULANAN =================== */
  if ($pdo->query("SHOW TABLES LIKE 'tangkap_nilai_bulanan'")->fetch()) {
    $st = $pdo->prepare("
      SELECT uraian,januari,februari,maret,april,mei,juni,juli,agustus,september,oktober,november,desember,jumlah
      FROM tangkap_nilai_bulanan
      WHERE tahun = ?
      ORDER BY uraian
    ");
    $st->execute([$y]);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $out['nilai_bulanan'][] = [
        'Uraian'    => (string)$r['uraian'],
        'Januari'   => (float) $r['januari'],
        'Februari'  => (float) $r['februari'],
        'Maret'     => (float) $r['maret'],
        'April'     => (float) $r['april'],
        'Mei'       => (float) $r['mei'],
        'Juni'      => (float) $r['juni'],
        'Juli'      => (float) $r['juli'],
        'Agustus'   => (float) $r['agustus'],
        'September' => (float) $r['september'],
        'Oktober'   => (float) $r['oktober'],
        'November'  => (float) $r['november'],
        'Desember'  => (float) $r['desember'],
        'Jumlah'    => (float) $r['jumlah'],
      ];
    }
  }

  /* =================== KOMODITAS (parent–child) =================== */
  if ($pdo->query("SHOW TABLES LIKE 'tangkap_komoditas'")->fetch()) {
    $st = $pdo->prepare("
      SELECT `no`, komoditas, volume, COALESCE(is_sub,0) AS is_sub, COALESCE(is_note,0) AS is_note
      FROM tangkap_komoditas
      WHERE tahun = ?
      ORDER BY
        CASE WHEN `no` IS NULL THEN 1 ELSE 0 END,
        `no`,
        is_sub,
        komoditas
    ");
    $st->execute([$y]);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $out['komoditas'][] = [
        'no'        => $r['no'] === null ? null : (int)$r['no'],
        'komoditas' => (string)$r['komoditas'],
        'volume'    => $r['volume'] === null ? '' : (string)$r['volume'],
        'is_sub'    => (int)$r['is_sub'],
        'is_note'   => (int)$r['is_note'],
      ];
    }
  }

  ok($out);

} catch (Throwable $e) {
  ok($out);
}
?>