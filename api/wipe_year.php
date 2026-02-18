<?php
// /api/wipe_year.php — hapus data per tahun untuk 6 bidang:
// KPP (garam), Ekspor Perikanan, Pengolahan & Pemasaran, Investasi, Budidaya, Perikanan Tangkap
// Fitur: alias grup, dry_run, confirm=WIPE, method guard, kompat pdo(), CORS,
//        auto-skip jika tabel belum ada (tidak meledak), dan preview via GET.
declare(strict_types=1);

/* ===== Headers / CORS ===== */
$debug = (isset($_GET['debug']) && $_GET['debug'] === '1');
if ($debug) { ini_set('display_errors', '1'); error_reporting(E_ALL); }

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db.php';
if (!function_exists('pdo')) {
  function pdo(): PDO { /** @var PDO $pdo */ global $pdo; return $pdo; }
}

/* ===== Helpers ===== */
function bad(string $msg, int $code = 400, array $extra = []): void {
  if ($code === 405) header('Allow: GET, POST, DELETE, OPTIONS');
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function ok(array $extra = []): void {
  echo json_encode(['ok' => true] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function table_exists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
  $st->execute([$table]);
  return (bool)$st->fetchColumn();
}

/* ===== Method guard ===== */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST','DELETE','OPTIONS'], true)) {
  bad('Method tidak diizinkan. Gunakan GET/POST/DELETE.', 405, ['allow' => ['GET','POST','DELETE','OPTIONS']]);
}

/* ===== Parse input ===== */
try {
  $raw  = file_get_contents('php://input') ?: '';
  $json = $raw !== '' ? json_decode($raw, true) : null;

  $tahun   = $json['tahun']   ?? ($_GET['tahun']   ?? null);
  $tables  = $json['tables']  ?? ($_GET['tables']  ?? null);
  $groups  = $json['groups']  ?? ($_GET['groups']  ?? null);
  $dryRun  = $json['dry_run'] ?? ($_GET['dry_run'] ?? false);
  $confirm = $json['confirm'] ?? ($_GET['confirm'] ?? null);

  if ($method === 'GET') {
    ok([
      'message' => 'Endpoint siap. Gunakan POST/DELETE untuk eksekusi wipe.',
      'params'  => ['tahun','tables','groups','dry_run','confirm'],
      'confirm' => 'Set confirm=WIPE untuk eksekusi non-dry-run.',
      'groups_available' => [
        'kpp','garam','ekspor','pengolahan','investasi',
        'budidaya','perikanan_budidaya',
        'tangkap','perikanan_tangkap','penangkapan',
        'all'
      ],
      'examples' => [
        'dry_run' => 'POST {"tahun":2024,"groups":"all","dry_run":true}',
        'execute' => 'DELETE {"tahun":2024,"groups":["tangkap","ekspor"],"confirm":"WIPE"}'
      ],
    ]);
  }

  if ($tahun === null || $tahun === '') bad('Parameter "tahun" wajib diisi');
  $tahun_int = (int)preg_replace('/[^\d]/', '', (string)$tahun);
  if ($tahun_int < 1900 || $tahun_int > 2100) bad('Parameter "tahun" tidak valid');
  $tahun_str = (string)$tahun_int;

  /* ===== Kelompok tabel ===== */
  $KPP = ['kpp_garam'];

  $EKSPOR = [
    'ekspor_perikanan_total',
    'ekspor_perikanan_utama',
    'ekspor_perikanan_ringkasan',
  ];

  $PENGOLAHAN_PEMASARAN = [
    'pengolahan_pemasaran_aki',
    'pengolahan_pemasaran_pemasaran',
    'pengolahan_pemasaran_olahankab',
    'pengolahan_pemasaran_olahjenis',
  ];

  $INVESTASI = [
    'investasi_detail',
    'investasi_sektor_total',
    'investasi_rekap_sumber',
    'investasi_rekap_bidang',
    'investasi_rekap_kota',
    'investasi_rekap_pma_negara',
  ];

  $BUDIDAYA = [
    'budidaya_ringkasan',
    'budidaya_volume_bulanan',
    'budidaya_nilai_bulanan',
    'budidaya_luas',
    'budidaya_produksi_kabkota',
    'budidaya_pembudidaya',
    'budidaya_komoditas',
  ];

  // ===== TANGKAP (BARU + kompat lama) =====
  $TANGKAP = [
    'tangkap_ringkasan',
    'tangkap_produksi_matrix',
    'tangkap_volume_bulanan',
    'tangkap_nilai_bulanan',
    'tangkap_komoditas', // tabel baru (parent-child)
  ];

  $GROUPS = [
    'kpp'               => $KPP,
    'garam'             => $KPP,
    'ekspor'            => $EKSPOR,
    'pengolahan'        => $PENGOLAHAN_PEMASARAN,
    'investasi'         => $INVESTASI,
    'budidaya'          => $BUDIDAYA,
    'perikanan_budidaya'=> $BUDIDAYA,
    'tangkap'           => $TANGKAP,
    'perikanan_tangkap' => $TANGKAP,
    'penangkapan'       => $TANGKAP,
    'all'               => array_values(array_unique(array_merge(
                          $KPP,$EKSPOR,$PENGOLAHAN_PEMASARAN,$INVESTASI,$BUDIDAYA,$TANGKAP
                        ))),
  ];

  $ALLOWED = $GROUPS['all'];

  // Normalisasi params array/string
  if (is_string($groups)) {
    $groups = array_values(array_filter(array_map('trim', explode(',', $groups)), fn($x)=>$x!==''));
  }
  if ($groups !== null && !is_array($groups)) bad('Parameter "groups" harus array atau string dipisah koma');

  if (is_string($tables)) {
    $tables = array_values(array_filter(array_map('trim', explode(',', $tables)), fn($x)=>$x!==''));
  } elseif ($tables !== null && !is_array($tables)) {
    bad('Parameter "tables" harus array atau string dipisah koma');
  }

  // Hitung target tabel
  $target = [];
  if (is_array($groups) && $groups) {
    foreach ($groups as $g) {
      $key = strtolower($g);
      if (!isset($GROUPS[$key])) bad("Alias grup tidak dikenali: $g", 400, ['allowed_groups'=>array_keys($GROUPS)]);
      $target = array_merge($target, $GROUPS[$key]);
    }
  }
  if (is_array($tables) && $tables) $target = array_merge($target, $tables);
  if (!$target) $target = $ALLOWED;
  $target = array_values(array_unique($target));

  foreach ($target as $t) {
    if (!in_array($t, $ALLOWED, true)) bad("Tabel tidak diizinkan: $t", 400, ['allowed' => $ALLOWED]);
  }

  $pdo = pdo();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $dryRun = filter_var($dryRun, FILTER_VALIDATE_BOOLEAN);

  /* ===== Dry-run: hitung yang akan dihapus ===== */
  if ($dryRun) {
    $would = []; $total = 0; $missing = [];
    foreach ($target as $tbl) {
      if (!table_exists($pdo, $tbl)) { $missing[] = $tbl; $would[$tbl] = 0; continue; }
      $st = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `tahun` = ?");
      $st->execute([$tahun_str]);
      $c = (int)$st->fetchColumn();
      $would[$tbl] = $c; $total += $c;
    }
    ok([
      'dry_run' => true,
      'tahun'   => $tahun_int,
      'tables'  => $target,
      'would_delete' => $would,
      'total_would_delete' => $total,
      'missing_tables' => $missing,
      'bidang' => [
        'kpp'                  => $KPP,
        'ekspor_perikanan'     => $EKSPOR,
        'pengolahan_pemasaran' => $PENGOLAHAN_PEMASARAN,
        'investasi'            => $INVESTASI,
        'budidaya'             => $BUDIDAYA,
        'tangkap'              => $TANGKAP,
      ],
      'hint_execute' => 'Kirim confirm=WIPE untuk eksekusi non-dry-run.'
    ]);
  }

  if ($confirm !== 'WIPE') {
    bad('Butuh konfirmasi. Sertakan "confirm=WIPE" untuk eksekusi non-dry-run atau set "dry_run=true" untuk simulasi.', 400, [
      'hint' => 'DELETE body: {"tahun":2024,"groups":"all","confirm":"WIPE"}',
    ]);
  }

  /* ===== Eksekusi hapus ===== */
  $deleted = []; $total = 0; $missing = [];
  $pdo->beginTransaction();
  try {
    foreach ($target as $tbl) {
      if (!table_exists($pdo, $tbl)) { $missing[] = $tbl; $deleted[$tbl] = 0; continue; }
      $st = $pdo->prepare("DELETE FROM `$tbl` WHERE `tahun` = ?");
      $st->execute([$tahun_str]);
      $deleted[$tbl] = $st->rowCount();
      $total += $deleted[$tbl];
    }
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }

  ok([
    'dry_run' => false,
    'tahun'   => $tahun_int,
    'tables'  => $target,
    'deleted' => $deleted,
    'total_deleted' => $total,
    'missing_tables' => $missing,
    'bidang' => [
      'kpp'                  => $KPP,
      'ekspor_perikanan'     => $EKSPOR,
      'pengolahan_pemasaran' => $PENGOLAHAN_PEMASARAN,
      'investasi'            => $INVESTASI,
      'budidaya'             => $BUDIDAYA,
      'tangkap'              => $TANGKAP,
    ],
  ]);

} catch (Throwable $e) {
  bad('Wipe error: '.$e->getMessage(), 500);
}
