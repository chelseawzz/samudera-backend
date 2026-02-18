<?php
// /api/save_rows.php
declare(strict_types=1);

/*
 * Endpoint serbaguna untuk menyimpan rows ke berbagai tabel.
 * - Perikanan Tangkap: handler khusus (ringkasan, matrix, bulanan, KOMODITAS satu tabel parent–child).
 * - Lainnya: handler generik (auto-match kolom).
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db.php';

/* fallback bila db.php lama pakai $pdo global */
if (!function_exists('pdo')) {
  function pdo(): PDO { /** @var PDO $pdo */ global $pdo; return $pdo; }
}

/* ==== helpers ==== */
function bad(string $msg, int $code = 400): never {
  http_response_code($code);
  echo json_encode(['ok'=>false, 'error'=>$msg], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function ok(array $extra = []): never {
  echo json_encode(['ok'=>true] + $extra, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function s($v): string { return trim((string)($v ?? '')); }
function i0($v): int   { return (int)($v ?? 0); }

/* normalisasi label (hapus NBSP, rapikan spasi) */
function norm_label(string $v): string {
  $v = str_replace("\xC2\xA0", ' ', $v);
  $v = preg_replace('/\s+/u', ' ', $v);
  return trim($v);
}

/* parse angka ke float (1.234.567,89 / 1,234,567.89) -> float */
function f0($v): float {
  if ($v === null || $v === '') return 0.0;
  if (is_numeric($v)) return (float)$v;
  $s = (string)$v;
  $s = preg_replace('/[^\d.,\-]/u', '', $s);
  if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
    $lastComma = strrpos($s, ',');
    $lastDot   = strrpos($s, '.');
    $decSep = ($lastComma > $lastDot) ? ',' : '.';
    $thouSep = ($decSep === ',') ? '.' : ','; $s = str_replace($thouSep, '', $s);
    if ($decSep === ',') $s = str_replace(',', '.', $s);
  } elseif (strpos($s, ',') !== false) {
    $parts = explode(',', $s, 2);
    if (isset($parts[1]) && strlen($parts[1]) <= 3) {
      $s = str_replace('.', '', $parts[0]) . '.' . preg_replace('/\D/u', '', $parts[1]);
    } else { $s = str_replace(',', '', $s); }
  } else {
    if (substr_count($s, '.') > 1) $s = str_replace('.', '', $s);
  }
  $s = preg_replace('/[^\d.\-]/u', '', $s);
  return is_numeric($s) ? (float)$s : 0.0;
}

/* ===== introspeksi kolom & tabel (untuk handler generik) ===== */
function table_columns(PDO $pdo, string $table): array {
  try {
    $db = $pdo->query('select database()')->fetchColumn();
    $q = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $q->execute([$db, $table]);
    return array_map('strval', $q->fetchAll(PDO::FETCH_COLUMN) ?: []);
  } catch (Throwable) { return []; }
}

/* ===== handler generik untuk tabel non-Tangkap =====
 * - Cocokkan kolom row dengan kolom tabel (case-sensitive).
 * - Jika ada kolom `tahun`, hapus data tahun tsb sebelum insert.
 */
function save_generic(PDO $pdo, string $table, array $rows): int {
  if (!$rows) return 0;

  $colsInDb = table_columns($pdo, $table);
  if (!$colsInDb) throw new RuntimeException("Tabel '$table' tidak ditemukan atau kolomnya tidak terbaca.");

  $colsSet = [];
  foreach ($rows as $r) foreach ($r as $k => $_) if (in_array($k, $colsInDb, true)) $colsSet[$k] = true;
  $cols = array_values(array_keys($colsSet));
  if (!$cols) return 0;

  if (in_array('tahun', $colsInDb, true)) {
    $tahun = 0;
    foreach ($rows as $r) { if (isset($r['tahun'])) { $tahun = (int)$r['tahun']; if ($tahun>0) break; } }
    if ($tahun > 0) $pdo->prepare("DELETE FROM `$table` WHERE tahun=?")->execute([$tahun]);
  }

  $ph = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
  $sql = "INSERT INTO `$table` (" . implode(',', array_map(fn($c)=>"`$c`", $cols)) . ") VALUES $ph";
  $st  = $pdo->prepare($sql);

  $saved = 0;
  foreach ($rows as $r) {
    $params = []; foreach ($cols as $c) $params[] = $r[$c] ?? null;
    $st->execute($params); $saved++;
  }
  return $saved;
}

/* ===== main ===== */
try {
  $raw = file_get_contents('php://input');
  if ($raw === false) bad('No body');
  $json = json_decode($raw, true);
  if (!is_array($json)) bad('Invalid JSON');

  $table = $json['table'] ?? '';
  $rows  = $json['rows']  ?? [];
  if (!$table || !is_array($rows)) bad('Payload harus {table, rows[]}');

  $ALLOWED = [
    // ===== Perikanan Tangkap =====
    'tangkap_ringkasan',
    'tangkap_produksi_matrix',
    'tangkap_volume_bulanan',
    'tangkap_nilai_bulanan',
    'tangkap_komoditas',  // satu tabel parent–child (No + is_sub)

    // ===== Budidaya =====
    'budidaya_ringkasan',
    'budidaya_volume_bulanan',
    'budidaya_nilai_bulanan',
    'budidaya_luas',
    'budidaya_produksi_kabkota',
    'budidaya_pembudidaya',
    'budidaya_komoditas',

    // ===== Pengolahan & Pemasaran =====
    'pengolahan_pemasaran_aki',
    'pengolahan_pemasaran_pemasaran',
    'pengolahan_pemasaran_olahankab',
    'pengolahan_pemasaran_olahjenis',

    // ===== Ekspor =====
    'ekspor_perikanan_total',
    'ekspor_perikanan_utama',
    'ekspor_perikanan_ringkasan',
    'ekspor_nilai_komoditas',
    'ekspor_volume_komoditas',
    'ekspor_ringkasan_negara',

    // ===== KPP Garam =====
    'kpp_garam',

    // ===== Investasi =====
    'investasi_sektor_total',
    'investasi_detail',
    'investasi_rekap_sumber',
    'investasi_rekap_bidang',
    'investasi_rekap_kota',
    'investasi_rekap_pma_negara',
  ];
  if (!in_array($table, $ALLOWED, true)) bad("Table not allowed: $table");

  $pdo = pdo();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $saved = 0;
  $pdo->beginTransaction();

  /* =================== HANDLER KHUSUS: PERIKANAN TANGKAP =================== */
  switch ($table) {

    /* 1) RINGKASAN */
    case 'tangkap_ringkasan': {
      if (!$rows) break;
      $tahun = 0; foreach ($rows as $r) { $yy=i0($r['tahun']??0); if($yy>0){$tahun=$yy;break;} }
      if ($tahun<=0) bad('Tahun tidak valid (tangkap_ringkasan)');

      $pdo->prepare("DELETE FROM tangkap_ringkasan WHERE tahun=?")->execute([$tahun]);

      $sql="INSERT INTO tangkap_ringkasan
            (tahun,cabang_usaha,nelayan_orang,rtp_pp,armada_buah,alat_tangkap_unit,volume_ton,nilai_rp_1000,is_total)
            VALUES (?,?,?,?,?,?,?,?,?)";
      $st=$pdo->prepare($sql);

      foreach ($rows as $r){
        $cabang = norm_label(s($r['cabang_usaha'] ?? $r['CABANG USAHA'] ?? $r['Uraian'] ?? ''));
        if ($cabang==='') continue;
        $st->execute([
          $tahun,
          $cabang,
          f0($r['nelayan_orang'] ?? $r['Nelayan (Orang)'] ?? 0),
          f0($r['rtp_pp'] ?? $r['RTP/PP (Orang/Unit)'] ?? 0),
          f0($r['armada_buah'] ?? $r['Armada Perikanan (Buah)'] ?? 0),
          f0($r['alat_tangkap_unit'] ?? $r['Alat Tangkap (Unit)'] ?? 0),
          f0($r['volume_ton'] ?? $r['Volume (Ton)'] ?? 0),
          f0($r['nilai_rp_1000'] ?? $r['Nilai (Rp 1.000)'] ?? 0),
          (int)($r['is_total'] ?? 0),
        ]);
        $saved++;
      }
    } break;

    /* 2) MATRIX subsektor */
    case 'tangkap_produksi_matrix': {
      if (!$rows) break;
      $tahun = 0; foreach ($rows as $r) { $yy=i0($r['tahun']??0); if($yy>0){$tahun=$yy;break;} }
      if ($tahun<=0) bad('Tahun tidak valid (tangkap_produksi_matrix)');

      $pdo->prepare("DELETE FROM tangkap_produksi_matrix WHERE tahun=?")->execute([$tahun]);

      $sql="INSERT INTO tangkap_produksi_matrix (tahun,kab_kota,subsektor,volume_ton) VALUES (?,?,?,?)";
      $st=$pdo->prepare($sql);

      foreach ($rows as $r){
        $kab  = norm_label(s($r['kab_kota'] ?? $r['Wilayah'] ?? ''));
        $sub  = norm_label(s($r['subsektor'] ?? ''));
        if ($kab==='' || $sub==='') continue;
        $st->execute([$tahun, $kab, $sub, f0($r['volume_ton'] ?? ($r[$sub] ?? 0))]);
        $saved++;
      }
    } break;

    /* 3) VOLUME bulanan */
    case 'tangkap_volume_bulanan': {
      if (!$rows) break;
      $tahun = 0; foreach ($rows as $r) { $yy=i0($r['tahun']??0); if($yy>0){$tahun=$yy;break;} }
      if ($tahun<=0) bad('Tahun tidak valid (tangkap_volume_bulanan)');

      $pdo->prepare("DELETE FROM tangkap_volume_bulanan WHERE tahun=?")->execute([$tahun]);

      $sql="INSERT INTO tangkap_volume_bulanan
           (tahun,uraian,januari,februari,maret,april,mei,juni,juli,agustus,september,oktober,november,desember,jumlah)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
      $st=$pdo->prepare($sql);

      foreach ($rows as $r){
        $ura = norm_label(s($r['uraian'] ?? $r['Uraian'] ?? '')); if ($ura==='') continue;
        $st->execute([
          $tahun, $ura,
          f0($r['januari']??$r['Januari']??0),
          f0($r['februari']??$r['Februari']??0),
          f0($r['maret']??$r['Maret']??0),
          f0($r['april']??$r['April']??0),
          f0($r['mei']??$r['Mei']??0),
          f0($r['juni']??$r['Juni']??0),
          f0($r['juli']??$r['Juli']??0),
          f0($r['agustus']??$r['Agustus']??0),
          f0($r['september']??$r['September']??0),
          f0($r['oktober']??$r['Oktober']??0),
          f0($r['november']??$r['November']??0),
          f0($r['desember']??$r['Desember']??0),
          f0($r['jumlah']??$r['Jumlah']??0),
        ]);
        $saved++;
      }
    } break;

    /* 4) NILAI bulanan */
    case 'tangkap_nilai_bulanan': {
      if (!$rows) break;
      $tahun = 0; foreach ($rows as $r) { $yy=i0($r['tahun']??0); if($yy>0){$tahun=$yy;break;} }
      if ($tahun<=0) bad('Tahun tidak valid (tangkap_nilai_bulanan)');

      $pdo->prepare("DELETE FROM tangkap_nilai_bulanan WHERE tahun=?")->execute([$tahun]);

      $sql="INSERT INTO tangkap_nilai_bulanan
           (tahun,uraian,januari,februari,maret,april,mei,juni,juli,agustus,september,oktober,november,desember,jumlah)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
      $st=$pdo->prepare($sql);

      foreach ($rows as $r){
        $ura = norm_label(s($r['uraian'] ?? $r['Uraian'] ?? '')); if ($ura==='') continue;
        $st->execute([
          $tahun, $ura,
          f0($r['januari']??$r['Januari']??0),
          f0($r['februari']??$r['Februari']??0),
          f0($r['maret']??$r['Maret']??0),
          f0($r['april']??$r['April']??0),
          f0($r['mei']??$r['Mei']??0),
          f0($r['juni']??$r['Juni']??0),
          f0($r['juli']??$r['Juli']??0),
          f0($r['agustus']??$r['Agustus']??0),
          f0($r['september']??$r['September']??0),
          f0($r['oktober']??$r['Oktober']??0),
          f0($r['november']??$r['November']??0),
          f0($r['desember']??$r['Desember']??0),
          f0($r['jumlah']??$r['Jumlah']??0),
        ]);
        $saved++;
      }
    } break;

    /* 5) KOMODITAS — satu tabel parent–child */
    case 'tangkap_komoditas': {
      if (!$rows) break;
      $tahun = 0; foreach ($rows as $r) { $yy = i0($r['tahun'] ?? 0); if ($yy > 0) { $tahun = $yy; break; } }
      if ($tahun <= 0) bad('Tahun tidak valid (tangkap_komoditas)');

      $pdo->prepare("DELETE FROM tangkap_komoditas WHERE tahun=?")->execute([$tahun]);
      $sql  = "INSERT INTO tangkap_komoditas (tahun, `no`, komoditas, volume, is_sub, is_note) VALUES (?,?,?,?,?,?)";
      $st   = $pdo->prepare($sql);

      $lastNo = null;
      foreach ($rows as $r) {
        $kom = norm_label(s($r['komoditas'] ?? $r['Komoditas'] ?? ''));
        if ($kom === '') continue;

        $explicit = array_key_exists('is_sub', $r);
        $noRaw    = $r['no'] ?? $r['No'] ?? null;
        $hasNo    = !($noRaw === '' || $noRaw === null);

        if ($hasNo) {
          $no    = i0($noRaw);
          $lastNo = $no;
          $isSub = $explicit ? (int)$r['is_sub'] : 0;   // baris bernomor → parent
        } else {
          $no    = $lastNo;                              // warisi parent terakhir
          $isSub = $explicit ? (int)$r['is_sub'] : 1;   // No kosong → default anak
        }

        // simpan volume STRING apa adanya
        $vol = '';
        if (array_key_exists('volume', $r))           $vol = (string)$r['volume'];
        elseif (array_key_exists('Volume', $r))       $vol = (string)$r['Volume'];
        elseif (array_key_exists('Volume (Ton)', $r)) $vol = (string)$r['Volume (Ton)'];

        $isNote = (int)($r['is_note'] ?? 0);

        $st->execute([$tahun, $no, $kom, trim($vol), $isSub, $isNote]);
        $saved++;
      }
    } break;

    /* =================== LAINNYA: GENERIC =================== */
    default: {
      $saved += save_generic($pdo, $table, $rows);
    }
  }

  $pdo->commit();
  ok(['saved'=>$saved]);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  bad('Save error: '.$e->getMessage(), 500);
}
