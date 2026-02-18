<?php

// api/login_audit_list.php — riwayat login user saat ini (JSON)


declare(strict_types=1);
ini_set('display_errors','0'); error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/db.php';

if (empty($_SESSION['isLoggedIn']) || empty($_SESSION['user_id'])) {
  http_response_code(401);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}

$userId = (int)$_SESSION['user_id'];
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = min(25, max(5, (int)($_GET['limit'] ?? 10)));
$offset = ($page-1) * $limit;

try {
  $total = (int)pdo()->query("SELECT COUNT(*) FROM login_audit WHERE user_id={$userId}")->fetchColumn();

  $st = pdo()->prepare("
    SELECT id, event_time, ip, device, COALESCE(location,'') AS location, status, session_id
    FROM login_audit
    WHERE user_id=?
    ORDER BY id DESC
    LIMIT ? OFFSET ?
  ");
  $st->bindValue(1, $userId, PDO::PARAM_INT);
  $st->bindValue(2, $limit,  PDO::PARAM_INT);
  $st->bindValue(3, $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll() ?: [];

  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'data' => array_map(function($r){
      return [
        'id'         => (int)$r['id'],
        'time'       => $r['event_time'],
        'ip'         => $r['ip'],
        'device'     => $r['device'],
        'location'   => $r['location'] ?: null,
        'status'     => $r['status'],
        'session_id' => $r['session_id'],
      ];
    }, $rows)
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'error'=>'db_error']);
}
