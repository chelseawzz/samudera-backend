<?php
// api/upload.php — upload multi-file ke fm_files
declare(strict_types=1);

require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('json_ok')) {
  function json_ok($data = null) {
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
  }
}
if (!function_exists('json_err')) {
  function json_err(string $message, int $code = 400) {
    http_response_code(($code >= 400 && $code < 600) ? $code : 400);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

try {
  $bidang = trim($_POST['bidang'] ?? '');
  $tahun  = trim($_POST['tahun'] ?? '');
  if ($bidang === '' || $tahun === '' || !preg_match('/^\d{4}$/', $tahun)) {
    json_err('Bidang & Tahun wajib diisi (tahun 4 digit).');
  }

  if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
  $uploader_name  = trim($_SESSION['display_name'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? '') ?: 'SAMUDERA';
  $uploader_email = trim($_SESSION['email'] ?? '');

  $filesBag = $_FILES['files'] ?? ($_FILES['file'] ?? null);
  if (!$filesBag) json_err('Tidak ada file yang dikirim.');

  $files = [];
  if (is_array($filesBag['name'])) {
    $n = count($filesBag['name']);
    for ($i=0;$i<$n;$i++){
      $files[] = [
        'name'     => $filesBag['name'][$i],
        'type'     => $filesBag['type'][$i],
        'tmp_name' => $filesBag['tmp_name'][$i],
        'error'    => $filesBag['error'][$i],
        'size'     => $filesBag['size'][$i],
      ];
    }
  } else {
    $files[] = $filesBag;
  }
  if (!$files) json_err('Tidak ada file yang valid.');

  $baseDir = dirname(__DIR__) . '/storage/uploads';
  if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
    json_err('Gagal menyiapkan folder upload.');
  }

  $pdo = pdo();
  $pdo->beginTransaction();
  $finfo = new finfo(FILEINFO_MIME_TYPE);

  $stmt = $pdo->prepare("
    INSERT INTO fm_files
      (original_name, stored_name, bidang, tahun, size_bytes, mime_type, sha1_hash,
       uploader_name, uploader_email, uploaded_at, meta_json)
    VALUES (?,?,?,?,?,?,?,?,?,NOW(),?)
  ");

  $savedIds = [];

  foreach ($files as $f) {
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) {
      $errMap = [
        UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas form.',
        UPLOAD_ERR_PARTIAL    => 'File hanya ter-upload sebagian.',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang diunggah.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara tidak tersedia.',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload dibatalkan ekstensi PHP.',
      ];
      throw new RuntimeException(($errMap[$err] ?? 'Kesalahan upload')." (code $err)");
    }

    $orig = $f['name'] ?? 'file';
    $orig = preg_replace('/[^\p{L}\p{N}\.\-\_\s]/u', '_', $orig);
    $ext  = pathinfo($orig, PATHINFO_EXTENSION);

    $rand  = bin2hex(random_bytes(8));
    $store = $rand . ($ext ? '.'.$ext : '');
    $abs   = dirname(__DIR__) . '/storage/uploads/'.$store;

    if (!is_uploaded_file($f['tmp_name'] ?? '')) {
      throw new RuntimeException('Sumber file tidak valid.');
    }
    if (!move_uploaded_file($f['tmp_name'], $abs)) {
      throw new RuntimeException('Gagal menyimpan file.');
    }
    @chmod($abs, 0644);

    $size = @filesize($abs) ?: (int)($f['size'] ?? 0);
    $mime = $finfo->file($abs) ?: ($f['type'] ?? 'application/octet-stream');
    $sha1 = @sha1_file($abs) ?: '';

    $meta = json_encode(['bidang'=>$bidang,'tahun'=>$tahun], JSON_UNESCAPED_UNICODE);

    $stmt->execute([
      $orig, $store, $bidang, $tahun, $size, $mime, $sha1,
      $uploader_name, $uploader_email, $meta
    ]);

    $fileId = (int)$pdo->lastInsertId();
    $savedIds[] = $fileId;

    $pdo->prepare("INSERT INTO fm_actions(file_id,action,actor,info) VALUES (?,?,?,?)")
        ->execute([$fileId,'upload',$uploader_name,json_encode(['ip'=>$_SERVER['REMOTE_ADDR'] ?? ''], JSON_UNESCAPED_UNICODE)]);
  }

  $pdo->commit();
  json_ok(['count'=>count($savedIds), 'ids'=>$savedIds]);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
  json_err('Upload error: '.$e->getMessage(), 500);
}
