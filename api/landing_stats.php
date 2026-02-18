<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:5173");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    $pdo = pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stats = [];

    // 1. PERIKANAN TANGKAP - Total Volume (ton)
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(volume_ton), 0) as total 
        FROM tangkap_produksi_matrix 
        WHERE tahun = (SELECT MAX(tahun) FROM tangkap_produksi_matrix)
    ");
    $tangkap = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['tangkap'] = [
        'value' => (float)$tangkap['total'],
        'unit' => 'ton',
        'tahun' => date('Y')
    ];

    // 2. PERIKANAN BUDIDAYA - Total Volume (ton)
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(
            volume_ton
        ), 0) as total 
        FROM budidaya_matrix_kabkota 
        WHERE tahun = (SELECT MAX(tahun) FROM budidaya_matrix_kabkota)
    ");
    $budidaya = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['budidaya'] = [
        'value' => (float)$budidaya['total'],
        'unit' => 'ton',
        'tahun' => date('Y')
    ];

    // 3. KPP (GARAM) - Total Volume (ton)
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(volume_produksi_ton), 0) as total 
        FROM kpp_garam 
        WHERE tahun = (SELECT MAX(tahun) FROM kpp_garam)
    ");
    $kpp = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['kpp'] = [
        'value' => (float)$kpp['total'],
        'unit' => 'ton',
        'tahun' => date('Y')
    ];

    // 4. PENGOLAHAN & PEMASARAN - Total Unit
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(jumlah_unit), 0) as total 
        FROM pengolahan_pemasaran_olahankab 
        WHERE tahun = (SELECT MAX(tahun) FROM pengolahan_pemasaran_olahankab)
    ");
    $pengolahan = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pengolahan'] = [
        'value' => (float)$pengolahan['total'],
        'unit' => 'unit',
        'tahun' => date('Y')
    ];

    // 5. EKSPOR - Total Volume (ton)
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(jumlah_ton), 0) as total 
        FROM ekspor_perikanan_ringkasan 
        WHERE tahun = (SELECT MAX(tahun) FROM ekspor_perikanan_ringkasan)
    ");
    $ekspor = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['ekspor'] = [
        'value' => (float)$ekspor['total'],
        'unit' => 'ton',
        'tahun' => date('Y')
    ];

    // 6. INVESTASI - Total Value (Rp) - Optional, sesuaikan dengan tabel Anda
    $stats['investasi'] = [
        'value' => 0,
        'unit' => 'Rp',
        'tahun' => date('Y')
    ];

    echo json_encode([
        'ok' => true,
        'data' => $stats,
        'last_updated' => date('Y-m-d H:i:s')
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>