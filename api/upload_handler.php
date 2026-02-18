<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function out_ok(array $data): void {
    echo json_encode([
        'ok' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    exit;
}

function out_err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode([
        'ok' => false,
        'error' => $msg
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ========================================
// HELPER FUNCTION - PARSE NUMERIC
// ========================================
function parseNumeric($value) {
    if ($value === null || $value === '') return 0;
    $value = (string)$value;
    if (strpos($value, ',') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    }
    $num = floatval($value);
    return is_nan($num) ? 0 : $num;
}

// ==============================
// MAIN PROCESS
// ==============================
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        out_err('Method not allowed', 405);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak tersedia',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Ekstensi PHP menghentikan upload',
        ];
        $error_code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        out_err('Upload gagal: ' . ($errors[$error_code] ?? 'Error tidak dikenal'), 400);
    }

    $file   = $_FILES['file'];
    $bidang = $_POST['bidang'] ?? '';
    $tahun  = (int)($_POST['tahun'] ?? 0);

    if ($tahun < 2000 || $tahun > 2100) {
        out_err('Tahun tidak valid (2000-2100)');
    }

    if (!in_array($bidang, ['tangkap', 'budidaya', 'kpp', 'pengolahan', 'ekspor'])) {
        out_err('Bidang tidak valid');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    $allowedMimes = [
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv'
    ];

    if (!in_array($mimeType, $allowedMimes)) {
        out_err('Hanya file Excel atau CSV yang diperbolehkan');
    }

    if ($file['size'] > 40 * 1024 * 1024) {
        out_err('Ukuran file melebihi 40MB');
    }

    // ==============================
    // SIMPAN FILE
    // ==============================
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            out_err('Gagal membuat folder uploads');
        }
    }

    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $storedName = bin2hex(random_bytes(8)) . '_' . time() . '.' . $fileExt;
    $filePath = $uploadDir . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        out_err('Gagal menyimpan file');
    }

    $sha1Hash = sha1_file($filePath);

    // ==============================
    // BACA FILE
    // ==============================
    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
    } catch (Throwable $e) {
        unlink($filePath);
        out_err('Gagal membaca file Excel: ' . $e->getMessage());
    }

    if (!$rows || count($rows) === 0) {
        unlink($filePath);
        out_err('File kosong atau tidak dapat dibaca');
    }

    // ==============================
    // AMBIL HEADER DAN DATA
    // ==============================
    $firstRowKey = array_key_first($rows);
    $headers = array_map('trim', $rows[$firstRowKey]);
    unset($rows[$firstRowKey]);
    $rows = array_values($rows);

    // ==============================
    // KONEKSI DATABASE
    // ==============================
    $pdo = pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==============================
    // SIMPAN METADATA KE fm_files DULU (SEBELUM IMPORT)
    // ==============================
    $fileId = saveFileMetadata($pdo, [
        'original_name' => $file['name'],
        'stored_name'   => $storedName,
        'bidang'        => $bidang,
        'tahun'         => $tahun,
        'size_bytes'    => $file['size'],
        'mime_type'     => $mimeType,
        'sha1_hash'     => $sha1Hash,
        'uploader_name' => 'Admin',
        'uploader_email'=> 'admin@local',
        'deleted_at'    => null,
        'meta_json'     => json_encode(['status' => 'importing'])
    ]);

    // ==============================
    // PROSES IMPORT BERDASARKAN BIDANG
    // ==============================
    $imported = 0;
    try {
        switch ($bidang) {
            case 'tangkap':
                $imported = importTangkap($pdo, $tahun, $headers, $rows, $fileId);
                break;
            case 'budidaya':
                $imported = importBudidaya($pdo, $tahun, $headers, $rows, $fileId);
                break;
            case 'kpp':
                $imported = importKPP($pdo, $tahun, $headers, $rows, $fileId);
                break;
            case 'pengolahan':
                $imported = importPengolahan($pdo, $tahun, $headers, $rows, $fileId);
                break;
            case 'ekspor':
                $imported = importEkspor($pdo, $tahun, $headers, $rows, $fileId);
                break;
            default:
                throw new Exception('Bidang tidak dikenali');
        }

        // Update metadata setelah import berhasil
        $stmt = $pdo->prepare("UPDATE fm_files SET meta_json = ? WHERE id = ?");
        $stmt->execute([json_encode(['total_rows' => $imported, 'status' => 'success']), $fileId]);

    } catch (Throwable $e) {
        // Hapus file dan metadata jika gagal import
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $stmt = $pdo->prepare("DELETE FROM fm_files WHERE id = ?");
        $stmt->execute([$fileId]);
        throw $e;
    }

    // ==============================
    // SIMPAN LOG KE fm_actions
    // ==============================
    saveActionLog($pdo, $fileId, 'upload', 'Admin', [
        'rows_imported' => $imported,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    // ==============================
    // SUCCESS RESPONSE
    // ==============================
    out_ok([
        'message' => 'Upload & import berhasil',
        'rows_imported' => $imported,
        'file_id' => $fileId
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
    exit;
}

// ========================================
// IMPORT FUNCTIONS - PERIKANAN TANGKAP
// ========================================
function importTangkap(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;

    $hasCabangUsaha = false;
    $hasKabKota = false;
    $hasKomoditas = false;
    $hasSubsektor = false;
    $hasUraian = false;
    $hasJenisPerairan = false;

    foreach ($headers as $header) {
        $h = strtolower(trim($header));
        if (strpos($h, 'cabang') !== false && strpos($h, 'usaha') !== false) $hasCabangUsaha = true;
        if (strpos($h, 'kab') !== false || strpos($h, 'kota') !== false) $hasKabKota = true;
        if (strpos($h, 'komoditas') !== false) $hasKomoditas = true;
        if (strpos($h, 'subsektor') !== false) $hasSubsektor = true;
        if (strpos($h, 'uraian') !== false) $hasUraian = true;
        if (strpos($h, 'jenis') !== false && strpos($h, 'perairan') !== false) $hasJenisPerairan = true;
    }

    if ($hasCabangUsaha) {
        return importTangkapRingkasan($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasKabKota && $hasJenisPerairan && !$hasKomoditas && !$hasSubsektor) {
        return importTangkapWilayah($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasKomoditas) {
        return importTangkapKomoditas($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasKabKota && $hasSubsektor) {
        return importTangkapProduksiMatrix($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasUraian) {
        $hasVolume = false;
        $hasNilai = false;
        foreach ($headers as $header) {
            $h = strtolower(trim($header));
            if (preg_match('/volume|ton|kg/i', $h)) $hasVolume = true;
            if (preg_match('/nilai|rp|rupiah|usd/i', $h)) $hasNilai = true;
        }
        if ($hasVolume) {
            return importTangkapVolumeBulanan($pdo, $tahun, $headers, $rows, $fileId);
        } elseif ($hasNilai) {
            return importTangkapNilaiBulanan($pdo, $tahun, $headers, $rows, $fileId);
        } else {
            return importTangkapVolumeBulanan($pdo, $tahun, $headers, $rows, $fileId);
        }
    }

    throw new Exception('Tipe data tidak dikenali untuk Perikanan Tangkap');
}

function importTangkapRingkasan(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tangkap_ringkasan
            (tahun, cabang_usaha, nelayan_orang, rtp_pp, armada_buah, alat_tangkap_unit, volume_ton, nilai_rp_1000, is_total, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())
            ON DUPLICATE KEY UPDATE
            nelayan_orang = VALUES(nelayan_orang),
            rtp_pp = VALUES(rtp_pp),
            armada_buah = VALUES(armada_buah),
            alat_tangkap_unit = VALUES(alat_tangkap_unit),
            volume_ton = VALUES(volume_ton),
            nilai_rp_1000 = VALUES(nilai_rp_1000),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $cabangUsaha = trim($row['A'] ?? '');
            $nelayan = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $rtp = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $armada = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $alatTangkap = isset($row['E']) ? parseNumeric($row['E']) : 0;
            $volume = isset($row['F']) ? parseNumeric($row['F']) : 0;
            $nilai = isset($row['G']) ? parseNumeric($row['G']) : 0;

            $stmt->execute([$tahun, $cabangUsaha, $nelayan, $rtp, $armada, $alatTangkap, $volume, $nilai, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importTangkapWilayah(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tangkap_wilayah
            (tahun, kab_kota, jenis_perairan, nelayan_orang, armada_buah, alat_tangkap_unit, rtp_pp, volume_ton, nilai_rp, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            jenis_perairan = VALUES(jenis_perairan),
            nelayan_orang = VALUES(nelayan_orang),
            armada_buah = VALUES(armada_buah),
            alat_tangkap_unit = VALUES(alat_tangkap_unit),
            rtp_pp = VALUES(rtp_pp),
            volume_ton = VALUES(volume_ton),
            nilai_rp = VALUES(nilai_rp),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $kabKota = trim($row['A'] ?? '');
            $jenisPerairan = isset($row['B']) ? trim($row['B']) : 'Laut';
            $nelayan = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $armada = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $alatTangkap = isset($row['E']) ? parseNumeric($row['E']) : 0;
            $rtp = isset($row['F']) ? parseNumeric($row['F']) : 0;
            $volume = isset($row['G']) ? parseNumeric($row['G']) : 0;
            $nilai = isset($row['H']) ? parseNumeric($row['H']) : 0;

            $stmt->execute([$tahun, $kabKota, $jenisPerairan, $nelayan, $armada, $alatTangkap, $rtp, $volume, $nilai, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importTangkapKomoditas(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tangkap_komoditas
            (tahun, no, komoditas, volume, is_sub, is_note, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            volume = VALUES(volume),
            is_sub = VALUES(is_sub),
            is_note = VALUES(is_note),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $no = isset($row['A']) ? (is_numeric($row['A']) ? (int)$row['A'] : null) : null;
            $komoditas = isset($row['B']) ? trim($row['B']) : '';
            $volume = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $isSub = isset($row['D']) ? (int)$row['D'] : 0;
            $isNote = isset($row['E']) ? (int)$row['E'] : 0;

            $stmt->execute([$tahun, $no, $komoditas, $volume, $isSub, $isNote, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importTangkapProduksiMatrix(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tangkap_produksi_matrix
            (tahun, kab_kota, subsektor, volume_ton, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            volume_ton = VALUES(volume_ton),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $kabKota = trim($row['A'] ?? '');
            $subsektor = isset($row['B']) ? trim($row['B']) : '';
            $volume = isset($row['C']) ? parseNumeric($row['C']) : 0;

            $stmt->execute([$tahun, $kabKota, $subsektor, $volume, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importTangkapVolumeBulanan(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM tangkap_volume_bulanan WHERE tahun = ? AND uploaded_file_id = ?")->execute([$tahun, $fileId]);
        $stmt = $pdo->prepare("
            INSERT INTO tangkap_volume_bulanan
            (tahun, uraian, januari, februari, maret, april, mei, juni,
            juli, agustus, september, oktober, november, desember, jumlah, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $uraian = trim($row['A'] ?? '');
            $jan = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $feb = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $mar = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $apr = isset($row['E']) ? parseNumeric($row['E']) : 0;
            $mei = isset($row['F']) ? parseNumeric($row['F']) : 0;
            $jun = isset($row['G']) ? parseNumeric($row['G']) : 0;
            $jul = isset($row['H']) ? parseNumeric($row['H']) : 0;
            $agu = isset($row['I']) ? parseNumeric($row['I']) : 0;
            $sep = isset($row['J']) ? parseNumeric($row['J']) : 0;
            $okt = isset($row['K']) ? parseNumeric($row['K']) : 0;
            $nov = isset($row['L']) ? parseNumeric($row['L']) : 0;
            $des = isset($row['M']) ? parseNumeric($row['M']) : 0;
            $jumlah = isset($row['N']) ? parseNumeric($row['N']) : 0;

            $stmt->execute([$tahun, $uraian, $jan, $feb, $mar, $apr, $mei, $jun, $jul, $agu, $sep, $okt, $nov, $des, $jumlah, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importTangkapNilaiBulanan(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM tangkap_nilai_bulanan WHERE tahun = ? AND uploaded_file_id = ?")->execute([$tahun, $fileId]);
        $stmt = $pdo->prepare("
            INSERT INTO tangkap_nilai_bulanan
            (tahun, uraian, januari, februari, maret, april, mei, juni,
            juli, agustus, september, oktober, november, desember, jumlah, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $uraian = trim($row['A'] ?? '');
            $jan = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $feb = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $mar = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $apr = isset($row['E']) ? parseNumeric($row['E']) : 0;
            $mei = isset($row['F']) ? parseNumeric($row['F']) : 0;
            $jun = isset($row['G']) ? parseNumeric($row['G']) : 0;
            $jul = isset($row['H']) ? parseNumeric($row['H']) : 0;
            $agu = isset($row['I']) ? parseNumeric($row['I']) : 0;
            $sep = isset($row['J']) ? parseNumeric($row['J']) : 0;
            $okt = isset($row['K']) ? parseNumeric($row['K']) : 0;
            $nov = isset($row['L']) ? parseNumeric($row['L']) : 0;
            $des = isset($row['M']) ? parseNumeric($row['M']) : 0;
            $jumlah = isset($row['N']) ? parseNumeric($row['N']) : 0;

            $stmt->execute([$tahun, $uraian, $jan, $feb, $mar, $apr, $mei, $jun, $jul, $agu, $sep, $okt, $nov, $des, $jumlah, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ========================================
// IMPORT FUNCTIONS - PERIKANAN BUDIDAYA
// ========================================
function importBudidaya(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;

    $hasKabKota = false;
    $hasKomoditas = false;
    $hasSubsektor = false;
    $hasUraian = false;
    $hasJenis = false;
    $hasJenisAir = false;
    $hasJumlah = false;
    $hasLuas = false;
    $hasLaut = false;
    $hasVolumeTon = false;
    $hasNilaiRp = false;
    $hasPeran = false;
    $hasBbi = false;
    $hasPembibit = false;
    $hasNilai = false;
    $hasSatuan = false;

    foreach ($headers as $header) {
        $h = strtolower(trim($header));
        if (strpos($h, 'kab') !== false || strpos($h, 'kota') !== false) $hasKabKota = true;
        if (strpos($h, 'komoditas') !== false) $hasKomoditas = true;
        if (strpos($h, 'subsektor') !== false) $hasSubsektor = true;
        if (strpos($h, 'uraian') !== false) $hasUraian = true;
        if (strpos($h, 'jenis') !== false) $hasJenis = true;
        if (strpos($h, 'jenis_air') !== false || strpos($h, 'jenis air') !== false) $hasJenisAir = true;
        if (strpos($h, 'jumlah') !== false) $hasJumlah = true;
        if (strpos($h, 'luas') !== false) $hasLuas = true;
        if (strpos($h, 'bersih') !== false) $hasLuas = true;
        if (strpos($h, 'laut') !== false) $hasLaut = true;
        if (strpos($h, 'volume') !== false && strpos($h, 'ton') !== false) $hasVolumeTon = true;
        if (strpos($h, 'nilai') !== false && strpos($h, 'rp') !== false) $hasNilaiRp = true;
        if (strpos($h, 'peran') !== false) $hasPeran = true;
        if (strpos($h, 'bbi') !== false) $hasBbi = true;
        if (strpos($h, 'pembibit') !== false) $hasPembibit = true;
        if (strpos($h, 'nilai') !== false && strpos($h, 'rp') === false) $hasNilai = true;
        if (strpos($h, 'satuan') !== false) $hasSatuan = true;
    }

    if ($hasLuas && $hasUraian) {
        return importBudidayaLuasArea($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasUraian && $hasNilai && $hasSatuan) {
        return importBudidayaRingkasan($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasKabKota && $hasSubsektor && $hasPeran) {
        return importBudidayaPembudidayaDetail($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasKabKota && $hasSubsektor && $hasVolumeTon) {
        return importBudidayaMatrixKabKota($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasKabKota && $hasLaut) {
        return importBudidayaProduksiKabKota($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasKomoditas) {
        return importKomoditasBudidaya($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasJenisAir && ($hasBbi || $hasPembibit)) {
        return importBudidayaPembenihanRingkas($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasUraian && $hasJumlah) {
        return importBudidayaPembudidaya($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasUraian) {
        $hasVolume = false;
        foreach ($headers as $header) {
            if (strpos(strtolower($header), 'volume') !== false) {
                $hasVolume = true;
                break;
            }
        }
        return $hasVolume ? importBudidayaVolumeBulanan($pdo, $tahun, $headers, $rows, $fileId) : importBudidayaNilaiBulanan($pdo, $tahun, $headers, $rows, $fileId);
    }

    throw new Exception('Tipe data tidak dikenali untuk Perikanan Budidaya');
}

function importBudidayaLuasArea(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO budidaya_luas
            (tahun, uraian, luas_bersih_ha, uploaded_file_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            luas_bersih_ha = VALUES(luas_bersih_ha),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        $uraianCol = null;
        $luasCol = null;
        foreach ($headers as $col => $header) {
            $h = strtolower(trim($header));
            if (strpos($h, 'uraian') !== false) $uraianCol = $col;
            if (strpos($h, 'luas') !== false || strpos($h, 'bersih') !== false) $luasCol = $col;
        }

        if (!$uraianCol || !$luasCol) {
            throw new Exception('Header "uraian" atau "luas_bersih_ha" tidak ditemukan');
        }

        foreach ($rows as $row) {
            if (empty(trim($row[$uraianCol] ?? ''))) continue;
            $uraian = trim($row[$uraianCol]);
            $luas = isset($row[$luasCol]) ? parseNumeric($row[$luasCol]) : 0;

            $stmt->execute([$tahun, $uraian, $luas, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importBudidayaMatrixKabKota(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO budidaya_matrix_kabkota
            (tahun, kab_kota, subsektor, volume_ton, nilai_rp, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            volume_ton = VALUES(volume_ton),
            nilai_rp = VALUES(nilai_rp),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $kabKota = trim($row['A'] ?? '');
            $subsektor = isset($row['B']) ? trim($row['B']) : '';
            $volume = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $nilai = isset($row['D']) ? parseNumeric($row['D']) : 0;

            $stmt->execute([$tahun, $kabKota, $subsektor, $volume, $nilai, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importKomoditasBudidaya(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO komoditas_budidaya
            (tahun, no, komoditas, laut_volume, tambak_volume, kolam_volume,
            mina_padi_volume, karamba_volume, japung_volume,
            laut_nilai, tambak_nilai, kolam_nilai,
            mina_padi_nilai, karamba_nilai, japung_nilai, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            laut_volume = VALUES(laut_volume),
            tambak_volume = VALUES(tambak_volume),
            kolam_volume = VALUES(kolam_volume),
            mina_padi_volume = VALUES(mina_padi_volume),
            karamba_volume = VALUES(karamba_volume),
            japung_volume = VALUES(japung_volume),
            laut_nilai = VALUES(laut_nilai),
            tambak_nilai = VALUES(tambak_nilai),
            kolam_nilai = VALUES(kolam_nilai),
            mina_padi_nilai = VALUES(mina_padi_nilai),
            karamba_nilai = VALUES(karamba_nilai),
            japung_nilai = VALUES(japung_nilai),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $no = isset($row['A']) ? (is_numeric($row['A']) ? (int)$row['A'] : null) : null;
            $komoditas = isset($row['B']) ? trim($row['B']) : '';
            $lautVol = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $tambakVol = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $kolamVol = isset($row['E']) ? parseNumeric($row['E']) : 0;
            $minaPadiVol = isset($row['F']) ? parseNumeric($row['F']) : 0;
            $karambaVol = isset($row['G']) ? parseNumeric($row['G']) : 0;
            $japungVol = isset($row['H']) ? parseNumeric($row['H']) : 0;
            $lautNilai = isset($row['I']) ? parseNumeric($row['I']) : 0;
            $tambakNilai = isset($row['J']) ? parseNumeric($row['J']) : 0;
            $kolamNilai = isset($row['K']) ? parseNumeric($row['K']) : 0;
            $minaPadiNilai = isset($row['L']) ? parseNumeric($row['L']) : 0;
            $karambaNilai = isset($row['M']) ? parseNumeric($row['M']) : 0;
            $japungNilai = isset($row['N']) ? parseNumeric($row['N']) : 0;

            $stmt->execute([$tahun, $no, $komoditas, $lautVol, $tambakVol, $kolamVol,
                $minaPadiVol, $karambaVol, $japungVol,
                $lautNilai, $tambakNilai, $kolamNilai,
                $minaPadiNilai, $karambaNilai, $japungNilai, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importBudidayaVolumeBulanan(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM budidaya_volume_bulanan WHERE tahun = ? AND uploaded_file_id = ?")->execute([$tahun, $fileId]);
        $stmt = $pdo->prepare("
            INSERT INTO budidaya_volume_bulanan
            (tahun, uraian, jan, feb, mar, apr, mei, jun,
            jul, agu, sep, okt, nov, des, total, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $uraian = trim($row['A'] ?? '');
            $jan = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $feb = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $mar = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $apr = isset($row['E']) ? parseNumeric($row['E']) : 0;
            $mei = isset($row['F']) ? parseNumeric($row['F']) : 0;
            $jun = isset($row['G']) ? parseNumeric($row['G']) : 0;
            $jul = isset($row['H']) ? parseNumeric($row['H']) : 0;
            $agu = isset($row['I']) ? parseNumeric($row['I']) : 0;
            $sep = isset($row['J']) ? parseNumeric($row['J']) : 0;
            $okt = isset($row['K']) ? parseNumeric($row['K']) : 0;
            $nov = isset($row['L']) ? parseNumeric($row['L']) : 0;
            $des = isset($row['M']) ? parseNumeric($row['M']) : 0;
            $total = isset($row['N']) ? parseNumeric($row['N']) : null;

            $stmt->execute([$tahun, $uraian, $jan, $feb, $mar, $apr, $mei, $jun, $jul, $agu, $sep, $okt, $nov, $des, $total, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importBudidayaNilaiBulanan(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM budidaya_nilai_bulanan WHERE tahun = ? AND uploaded_file_id = ?")->execute([$tahun, $fileId]);
        $stmt = $pdo->prepare("
            INSERT INTO budidaya_nilai_bulanan
            (tahun, uraian, jan, feb, mar, apr, mei, jun,
            jul, agu, sep, okt, nov, des, total, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $uraian = trim($row['A'] ?? '');
            $jan = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $feb = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $mar = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $apr = isset($row['E']) ? parseNumeric($row['E']) : 0;
            $mei = isset($row['F']) ? parseNumeric($row['F']) : 0;
            $jun = isset($row['G']) ? parseNumeric($row['G']) : 0;
            $jul = isset($row['H']) ? parseNumeric($row['H']) : 0;
            $agu = isset($row['I']) ? parseNumeric($row['I']) : 0;
            $sep = isset($row['J']) ? parseNumeric($row['J']) : 0;
            $okt = isset($row['K']) ? parseNumeric($row['K']) : 0;
            $nov = isset($row['L']) ? parseNumeric($row['L']) : 0;
            $des = isset($row['M']) ? parseNumeric($row['M']) : 0;
            $total = isset($row['N']) ? parseNumeric($row['N']) : null;

            $stmt->execute([$tahun, $uraian, $jan, $feb, $mar, $apr, $mei, $jun, $jul, $agu, $sep, $okt, $nov, $des, $total, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importBudidayaPembudidaya(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO budidaya_pembudidaya
            (tahun, uraian, jumlah, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            jumlah = VALUES(jumlah),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $uraian = trim($row['A'] ?? '');
            $jumlah = isset($row['B']) ? parseNumeric($row['B']) : 0;

            $stmt->execute([$tahun, $uraian, $jumlah, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importBudidayaPembudidayaDetail(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO budidaya_pembudidaya_detail
            (tahun, kab_kota, subsektor, peran, jumlah, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            jumlah = VALUES(jumlah),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $kabKota = trim($row['A'] ?? '');
            $subsektor = isset($row['B']) ? trim($row['B']) : '';
            $peran = isset($row['C']) ? trim($row['C']) : '';
            $jumlah = isset($row['D']) ? parseNumeric($row['D']) : 0;

            $stmt->execute([$tahun, $kabKota, $subsektor, $peran, $jumlah, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importBudidayaProduksiKabKota(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO budidaya_produksi_kabkota
            (tahun, kabkota, jumlah, laut, tambak, kolam, minapadi, karamba, japung, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            jumlah = VALUES(jumlah),
            laut = VALUES(laut),
            tambak = VALUES(tambak),
            kolam = VALUES(kolam),
            minapadi = VALUES(minapadi),
            karamba = VALUES(karamba),
            japung = VALUES(japung),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $kabKota = trim($row['A'] ?? '');
            $jumlah = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $laut = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $tambak = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $kolam = isset($row['E']) ? parseNumeric($row['E']) : 0;
            $minapadi = isset($row['F']) ? parseNumeric($row['F']) : 0;
            $karamba = isset($row['G']) ? parseNumeric($row['G']) : 0;
            $japung = isset($row['H']) ? parseNumeric($row['H']) : 0;

            $stmt->execute([$tahun, $kabKota, $jumlah, $laut, $tambak, $kolam, $minapadi, $karamba, $japung, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importBudidayaPembenihanRingkas(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO budidaya_pembenihan_ringkas
            (tahun, jenis_air, bbi, upr, hsrt, swasta, pembibit_rula, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            bbi = VALUES(bbi),
            upr = VALUES(upr),
            hsrt = VALUES(hsrt),
            swasta = VALUES(swasta),
            pembibit_rula = VALUES(pembibit_rula),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $jenisAir = trim($row['A'] ?? '');
            $bbi = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $upr = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $hsrt = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $swasta = isset($row['E']) ? parseNumeric($row['E']) : 0;
            $pembibitRula = isset($row['F']) ? parseNumeric($row['F']) : 0;

            $stmt->execute([$tahun, $jenisAir, $bbi, $upr, $hsrt, $swasta, $pembibitRula, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importBudidayaRingkasan(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO budidaya_ringkasan
            (tahun, uraian, nilai, satuan, uploaded_file_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            nilai = VALUES(nilai),
            satuan = VALUES(satuan),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        $uraianCol = null;
        $nilaiCol = null;
        $satuanCol = null;
        foreach ($headers as $col => $header) {
            $h = strtolower(trim($header));
            if (strpos($h, 'uraian') !== false) $uraianCol = $col;
            if (strpos($h, 'nilai') !== false) $nilaiCol = $col;
            if (strpos($h, 'satuan') !== false) $satuanCol = $col;
        }

        if (!$uraianCol || !$nilaiCol || !$satuanCol) {
            throw new Exception('Header "uraian", "nilai", atau "satuan" tidak ditemukan');
        }

        foreach ($rows as $row) {
            if (empty(trim($row[$uraianCol] ?? ''))) continue;
            $uraian = trim($row[$uraianCol]);
            $nilai = isset($row[$nilaiCol]) ? parseNumeric($row[$nilaiCol]) : 0;
            $satuan = isset($row[$satuanCol]) ? trim($row[$satuanCol]) : '';

            $stmt->execute([$tahun, $uraian, $nilai, $satuan, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ========================================
// IMPORT FUNCTIONS - KPP (GARAM)
// ========================================
function importKPP(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;

    $hasKabKota = false;
    foreach ($headers as $header) {
        $h = strtolower(trim($header));
        if (strpos($h, 'kab') !== false || strpos($h, 'kota') !== false) $hasKabKota = true;
    }

    if ($hasKabKota) {
        return importKPPGaram($pdo, $tahun, $headers, $rows, $fileId);
    }

    throw new Exception('Tipe data tidak dikenali untuk KPP');
}

function importKPPGaram(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO kpp_garam
            (tahun, kab_kota, l_total_ha, luas_lahan_ha, jumlah_kelompok,
            sigma_petambak, sigma_prod_ton, jumlah_petambak,
            volume_produksi_ton, uploaded_file_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            l_total_ha = VALUES(l_total_ha),
            luas_lahan_ha = VALUES(luas_lahan_ha),
            jumlah_kelompok = VALUES(jumlah_kelompok),
            sigma_petambak = VALUES(sigma_petambak),
            sigma_prod_ton = VALUES(sigma_prod_ton),
            jumlah_petambak = VALUES(jumlah_petambak),
            volume_produksi_ton = VALUES(volume_produksi_ton),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $kabKota = trim($row['A'] ?? '');
            $lTotal = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $luasLahan = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $jmlKelompok = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $sigmaPetambak = isset($row['E']) ? parseNumeric($row['E']) : 0;
            $sigmaProd = isset($row['F']) ? parseNumeric($row['F']) : 0;
            $jmlPetambak = isset($row['G']) ? parseNumeric($row['G']) : 0;
            $volumeProduksi = isset($row['H']) ? parseNumeric($row['H']) : 0;

            $stmt->execute([$tahun, $kabKota, $lTotal, $luasLahan, $jmlKelompok,
                $sigmaPetambak, $sigmaProd, $jmlPetambak, $volumeProduksi, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ========================================
// IMPORT FUNCTIONS - PENGOLAHAN & PEMASARAN
// ========================================
function importPengolahan(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;

    $hasKabKota = false;
    $hasJenisKegiatan = false;
    $hasKidrt = false;

    foreach ($headers as $header) {
        $h = strtolower(trim($header));
        if (strpos($h, 'kab') !== false || strpos($h, 'kota') !== false) $hasKabKota = true;
        if (strpos($h, 'jenis') !== false && strpos($h, 'kegiatan') !== false) $hasJenisKegiatan = true;
        if (strpos($h, 'kidrt') !== false) $hasKidrt = true;
    }

    if ($hasJenisKegiatan) {
        return importPengolahanOlahJenis($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasKidrt) {
        return importPengolahanAKI($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasKabKota) {
        $hasFermentasi = false;
        foreach ($headers as $header) {
            if (strpos(strtolower($header), 'fermentasi') !== false) {
                $hasFermentasi = true;
                break;
            }
        }
        return $hasFermentasi ?
            importPengolahanOlahanKab($pdo, $tahun, $headers, $rows, $fileId) :
            importPengolahanPemasaran($pdo, $tahun, $headers, $rows, $fileId);
    }

    throw new Exception('Tipe data tidak dikenali untuk Pengolahan & Pemasaran');
}

function importPengolahanOlahJenis(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO pengolahan_pemasaran_olahjenis
            (tahun, jenis_kegiatan_pengolahan, jumlah_upi, uploaded_file_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            jumlah_upi = VALUES(jumlah_upi),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $jenisKegiatan = trim($row['A'] ?? '');
            $jumlahUpi = isset($row['B']) ? parseNumeric($row['B']) : 0;

            $stmt->execute([$tahun, $jenisKegiatan, $jumlahUpi, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importPengolahanOlahanKab(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO pengolahan_pemasaran_olahankab
            (tahun, kab_kota, fermentasi, pelumatan_daging_ikan, pembekuan,
            pemindangan, penanganan_produk_segar, pengalengan,
            pengasapan_pemanggangan, pereduksian_ekstraksi,
            penggaraman_pengeringan, pengolahan_lainnya, jumlah_unit, uploaded_file_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            fermentasi = VALUES(fermentasi),
            pelumatan_daging_ikan = VALUES(pelumatan_daging_ikan),
            pembekuan = VALUES(pembekuan),
            pemindangan = VALUES(pemindangan),
            penanganan_produk_segar = VALUES(penanganan_produk_segar),
            pengalengan = VALUES(pengalengan),
            pengasapan_pemanggangan = VALUES(pengasapan_pemanggangan),
            pereduksian_ekstraksi = VALUES(pereduksian_ekstraksi),
            penggaraman_pengeringan = VALUES(penggaraman_pengeringan),
            pengolahan_lainnya = VALUES(pengolahan_lainnya),
            jumlah_unit = VALUES(jumlah_unit),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $kabKota = trim($row['A'] ?? '');
            $fermentasi = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $pelumatan = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $pembekuan = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $pemindangan = isset($row['E']) ? parseNumeric($row['E']) : 0;
            $penanganan = isset($row['F']) ? parseNumeric($row['F']) : 0;
            $pengalengan = isset($row['G']) ? parseNumeric($row['G']) : 0;
            $pengasapan = isset($row['H']) ? parseNumeric($row['H']) : 0;
            $pereduksian = isset($row['I']) ? parseNumeric($row['I']) : 0;
            $penggaraman = isset($row['J']) ? parseNumeric($row['J']) : 0;
            $pengolahanLain = isset($row['K']) ? parseNumeric($row['K']) : 0;
            $jumlahUnit = isset($row['L']) ? parseNumeric($row['L']) : 0;

            $stmt->execute([$tahun, $kabKota, $fermentasi, $pelumatan, $pembekuan,
                $pemindangan, $penanganan, $pengalengan, $pengasapan,
                $pereduksian, $penggaraman, $pengolahanLain, $jumlahUnit, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importPengolahanPemasaran(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO pengolahan_pemasaran_pemasaran
            (tahun, kab_kota, pengecer, pengumpul, jumlah_unit, uploaded_file_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            pengecer = VALUES(pengecer),
            pengumpul = VALUES(pengumpul),
            jumlah_unit = VALUES(jumlah_unit),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $kabKota = trim($row['A'] ?? '');
            $pengecer = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $pengumpul = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $jumlahUnit = isset($row['D']) ? parseNumeric($row['D']) : 0;

            $stmt->execute([$tahun, $kabKota, $pengecer, $pengumpul, $jumlahUnit, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importPengolahanAKI(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO pengolahan_pemasaran_aki
            (tahun, kab_kota, kidrt, kilrt, ktt, aki, uploaded_file_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            kidrt = VALUES(kidrt),
            kilrt = VALUES(kilrt),
            ktt = VALUES(ktt),
            aki = VALUES(aki),
            uploaded_file_id = VALUES(uploaded_file_id),
            updated_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $kabKota = trim($row['A'] ?? '');
            $kidrt = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $kilrt = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $ktt = isset($row['D']) ? parseNumeric($row['D']) : 0;
            $aki = isset($row['E']) ? parseNumeric($row['E']) : 0;

            $stmt->execute([$tahun, $kabKota, $kidrt, $kilrt, $ktt, $aki, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ========================================
// IMPORT FUNCTIONS - EKSPOR
// ========================================
function importEkspor(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;

    $hasKomoditas = false;
    $hasNegara = false;
    $hasLabel = false;
    $hasSisi = false;
    $hasUrut = false;

    foreach ($headers as $header) {
        $h = strtolower(trim($header));
        if (strpos($h, 'komoditas') !== false) $hasKomoditas = true;
        if (strpos($h, 'negara') !== false) $hasNegara = true;
        if (strpos($h, 'label') !== false) $hasLabel = true;
        if (strpos($h, 'sisi') !== false) $hasSisi = true;
        if (strpos($h, 'urut') !== false) $hasUrut = true;
    }

    if ($hasLabel) {
        return importEksporRekap($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasSisi) {
        return importEksporUtama($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasUrut && $hasNegara) {
        return importEksporPerikananRingkasan($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasKomoditas) {
        return importEksporPerikananTotal($pdo, $tahun, $headers, $rows, $fileId);
    } elseif ($hasNegara) {
        return importEksporPerikananRingkasan($pdo, $tahun, $headers, $rows, $fileId);
    }

    throw new Exception('Tipe data tidak dikenali untuk Ekspor');
}

function importEksporPerikananTotal(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ekspor_perikanan_total
            (tahun, komoditas, volume_ton, nilai_usd, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            volume_ton = VALUES(volume_ton),
            nilai_usd = VALUES(nilai_usd),
            uploaded_file_id = VALUES(uploaded_file_id),
            created_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $komoditas = trim($row['A'] ?? '');
            $volume = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $nilai = isset($row['C']) ? parseNumeric($row['C']) : 0;

            $stmt->execute([$tahun, $komoditas, $volume, $nilai, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importEksporPerikananRingkasan(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ekspor_perikanan_ringkasan
            (tahun, urut, negara, jumlah_ton, nilai_usd, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            jumlah_ton = VALUES(jumlah_ton),
            nilai_usd = VALUES(nilai_usd),
            uploaded_file_id = VALUES(uploaded_file_id),
            created_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $urut = isset($row['A']) ? (int)$row['A'] : 0;
            $negara = isset($row['B']) ? trim($row['B']) : '';
            $jumlahTon = isset($row['C']) ? parseNumeric($row['C']) : 0;
            $nilaiUsd = isset($row['D']) ? parseNumeric($row['D']) : 0;

            $stmt->execute([$tahun, $urut, $negara, $jumlahTon, $nilaiUsd, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importEksporUtama(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ekspor_perikanan_utama
            (tahun, sisi, no_urut, komoditas, angka, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            angka = VALUES(angka),
            uploaded_file_id = VALUES(uploaded_file_id),
            created_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $sisi = isset($row['A']) ? trim($row['A']) : '';
            $noUrut = isset($row['B']) ? (int)$row['B'] : 0;
            $komoditas = isset($row['C']) ? trim($row['C']) : '';
            $angka = isset($row['D']) ? parseNumeric($row['D']) : 0;

            $stmt->execute([$tahun, $sisi, $noUrut, $komoditas, $angka, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importEksporRekap(PDO $pdo, int $tahun, array $headers, array $rows, int $fileId): int {
    $imported = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ekspor_perikanan_rekap
            (tahun, label, jumlah_ton, nilai_usd, uploaded_file_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            jumlah_ton = VALUES(jumlah_ton),
            nilai_usd = VALUES(nilai_usd),
            uploaded_file_id = VALUES(uploaded_file_id),
            created_at = NOW()
        ");

        foreach ($rows as $row) {
            if (empty(trim($row['A'] ?? ''))) continue;
            $label = isset($row['A']) ? trim($row['A']) : '';
            $jumlahTon = isset($row['B']) ? parseNumeric($row['B']) : 0;
            $nilaiUsd = isset($row['C']) ? parseNumeric($row['C']) : 0;

            $stmt->execute([$tahun, $label, $jumlahTon, $nilaiUsd, $fileId]);
            $imported++;
        }

        $pdo->commit();
        return $imported;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ========================================
// FUNGSI SIMPAN METADATA & LOG
// ========================================
function saveFileMetadata(PDO $pdo, array $data): int {
    $stmt = $pdo->prepare("
        INSERT INTO fm_files (
            original_name, stored_name, bidang, tahun,
            size_bytes, mime_type, sha1_hash,
            uploader_name, uploader_email, uploaded_at,
            deleted_at, meta_json
        ) VALUES (
            :original_name, :stored_name, :bidang, :tahun,
            :size_bytes, :mime_type, :sha1_hash,
            :uploader_name, :uploader_email, NOW(),
            :deleted_at, :meta_json
        )
    ");

    $stmt->execute([
        ':original_name' => $data['original_name'],
        ':stored_name' => $data['stored_name'],
        ':bidang' => $data['bidang'],
        ':tahun' => $data['tahun'],
        ':size_bytes' => $data['size_bytes'],
        ':mime_type' => $data['mime_type'],
        ':sha1_hash' => $data['sha1_hash'],
        ':uploader_name' => $data['uploader_name'],
        ':uploader_email' => $data['uploader_email'],
        ':deleted_at' => $data['deleted_at'],
        ':meta_json' => $data['meta_json']
    ]);

    return (int)$pdo->lastInsertId();
}

function saveActionLog(PDO $pdo, int $fileId, string $action, string $actor, array $info): void {
    $stmt = $pdo->prepare("
        INSERT INTO fm_actions
        (file_id, action, actor, acted_at, info)
        VALUES (?, ?, ?, NOW(), ?)
    ");

    $stmt->execute([
        $fileId,
        $action,
        $actor,
        json_encode($info)
    ]);
}
?>