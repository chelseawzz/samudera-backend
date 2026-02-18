<?php
// api/files.php — List/Download/Delete untuk fm_files (kompatibel front-end lama/baru)
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

try {
  /* =========================
   * LIST
   * ========================= */
  if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $bidang = trim($_GET['bidang'] ?? '');
    $tahun  = trim($_GET['tahun']  ?? '');

    $pdo = pdo();
    $sql = "SELECT id, original_name, stored_name, bidang, tahun,
                   size_bytes, mime_type, uploaded_at
            FROM fm_files
            WHERE deleted_at IS NULL";
    $p = [];
    if ($search !== '') { $sql .= " AND original_name LIKE ?"; $p[] = "%$search%"; }
    if ($bidang !== '') { $sql .= " AND bidang = ?";            $p[] = $bidang; }
    if ($tahun  !== '') { $sql .= " AND tahun  = ?";            $p[] = $tahun;  }
    $sql .= " ORDER BY uploaded_at DESC";

    $st = $pdo->prepare($sql);
    $st->execute($p);

    $rows = [];
    while ($r = $st->fetch()) {
      $uploadedAt = strtotime($r['uploaded_at']);
      $rows[] = [
        // ===== skema BARU
        'id'       => (int)$r['id'],
        'name'     => $r['original_name'],
        'bidang'   => $r['bidang'],
        'tahun'    => $r['tahun'],
        'size'     => (int)$r['size_bytes'],
        'mime'     => $r['mime_type'],
        'modified' => date('c', $uploadedAt),

        // ===== alias skema LAMA (untuk files.js varian lama)
        'title'       => $r['original_name'],
        'filename'    => $r['stored_name'],                       // nama file fisik
        'file_size'   => (int)$r['size_bytes'],
        'category'    => $r['bidang'],
        'upload_date' => date('Y-m-d H:i:s', $uploadedAt),
        'created_at'  => date('Y-m-d H:i:s', $uploadedAt),
        'description' => '',
        'region'      => '',
        'is_favorite' => 0
      ];
    }

    echo json_encode([
      'success' => true,
      'count'   => count($rows),
      'data'    => $rows
    ]);
    exit;
  }

  /* =========================
   * DOWNLOAD
   * ========================= */
  if ($action === 'download') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_err('ID kosong');

    $pdo = pdo();
    $st = $pdo->prepare("SELECT stored_name, original_name
                         FROM fm_files
                         WHERE id=? AND deleted_at IS NULL");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) json_err('File tidak ditemukan', [], 404);

    $rel = 'storage/uploads/'.$row['stored_name'];
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // /api
    $url  = $scheme.'://'.$host.$base.'/../'.$rel;

    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $actor = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
    $pdo->prepare("INSERT INTO fm_actions(file_id,action,actor,info)
                   VALUES (?,?,?,?)")
        ->execute([$id,'download',$actor,json_encode(['ip'=>$_SERVER['REMOTE_ADDR'] ?? ''])]);

    echo json_encode(['success'=>true,'url'=>$url,'filename'=>$row['original_name']]);
    exit;
  }

  /* =========================
   * DELETE (soft default, hard jika permanent=1)
   * ========================= */
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $permanent = (int)($_POST['permanent'] ?? 0);
    if (!$id) json_err('ID kosong');

    $pdo = pdo();

    if ($permanent === 1) {
      // hard delete: hapus file fisik + row
      $st = $pdo->prepare("SELECT stored_name FROM fm_files WHERE id=?");
      $st->execute([$id]);
      if ($row = $st->fetch()) {
        @unlink(dirname(__DIR__).'/storage/uploads/'.$row['stored_name']);
      }
      $pdo->prepare("DELETE FROM fm_files WHERE id=?")->execute([$id]);
    } else {
      // soft delete
      $pdo->prepare("UPDATE fm_files SET deleted_at = NOW() WHERE id=?")->execute([$id]);
    }

    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $actor = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
    $pdo->prepare("INSERT INTO fm_actions(file_id,action,actor) VALUES (?,?,?)")
        ->execute([$id,'delete',$actor]);

    echo json_encode(['success'=>true]);
    exit;
  }

  json_err('Action tidak dikenal');

} catch (Throwable $e) {
  json_err('API error: '.$e->getMessage());
}
