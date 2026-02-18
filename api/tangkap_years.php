<?php
// /api/tangkap_years.php — list tahun yang tersedia di data Perikanan Tangkap
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db.php';
if (!function_exists('pdo')) {
    function pdo(): PDO { global $pdo; return $pdo; }
}

try {
    $pdo = pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil tahun unik dari tabel tangkap_ringkasan
    $stmt = $pdo->query("SELECT DISTINCT tahun FROM tangkap_ringkasan ORDER BY tahun ASC");
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['ok' => true, 'years' => $years], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
