<?php
declare(strict_types=1);

// CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:5173', 'http://127.0.0.1:5173', 'http://localhost:5174'];
$finalOrigin = in_array($origin, $allowedOrigins) ? $origin : 'http://localhost:5173';

header("Access-Control-Allow-Origin: $finalOrigin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
    require_once __DIR__ . '/db.php';
    $pdo = pdo();
    
    $dbname = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!$dbname) {
        throw new Exception("Database connection failed");
    }

    $hasTable = function(string $table) use ($pdo, $dbname): bool {
        $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1");
        $q->execute([$dbname, $table]);
        return (bool)$q->fetchColumn();
    };

    $qAll = function(string $sql, array $p = []) use ($pdo) {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    };

    // ===== 1) GET ALL AVAILABLE YEARS
    $available_years = [];
    if ($hasTable('budidaya_ringkasan')) {
        $years_result = $qAll("SELECT DISTINCT tahun FROM budidaya_ringkasan ORDER BY tahun ASC");
        $available_years = array_column($years_result, 'tahun');
    } elseif ($hasTable('budidaya_matrix_kabkota')) {
        $years_result = $qAll("SELECT DISTINCT tahun FROM budidaya_matrix_kabkota ORDER BY tahun ASC");
        $available_years = array_column($years_result, 'tahun');
    } else {
        out_ok([
            'available_years' => [],
            'yearly_data' => []
        ]);
    }

    // ===== 2) FETCH YEARLY DATA FOR EACH YEAR
    $yearly_data = [];
    
    foreach ($available_years as $tahun) {
        $year_data = [
            'tahun' => (int)$tahun,
            'luas_total' => 0.0,
            'pembudidaya_total' => 0,
            'pembenihan_total' => 0,
            'volume_total' => 0.0,
            'nilai_total' => 0.0
        ];

        // ===== LUAS TOTAL (dari budidaya_luas)
        if ($hasTable('budidaya_luas')) {
            $luas_result = $qAll(
                "SELECT SUM(luas_bersih_ha) AS total_luas 
                 FROM budidaya_luas 
                 WHERE tahun = ? AND uraian != 'TOTAL'",
                [$tahun]
            );
            $year_data['luas_total'] = $luas_result[0]['total_luas'] ? (float)$luas_result[0]['total_luas'] : 0.0;
        }

        // ===== PEMBUDIDAYA TOTAL (dari budidaya_pembudidaya)
        if ($hasTable('budidaya_pembudidaya')) {
            $pembudidaya_result = $qAll(
                "SELECT SUM(jumlah) AS total_pembudidaya 
                 FROM budidaya_pembudidaya 
                 WHERE tahun = ? AND uraian != 'TOTAL'",
                [$tahun]
            );
            $year_data['pembudidaya_total'] = $pembudidaya_result[0]['total_pembudidaya'] ? (int)$pembudidaya_result[0]['total_pembudidaya'] : 0;
        }

        // ===== PEMBENIHAN TOTAL (dari budidaya_pembenihan_ringkas)
        if ($hasTable('budidaya_pembenihan_ringkas')) {
            $pembenihan_result = $qAll(
                "SELECT 
                    SUM(bbi + upr + hsrt + swasta + pembibit_rula) AS total_pembenihan
                 FROM budidaya_pembenihan_ringkas 
                 WHERE tahun = ?",
                [$tahun]
            );
            $year_data['pembenihan_total'] = $pembenihan_result[0]['total_pembenihan'] ? (int)$pembenihan_result[0]['total_pembenihan'] : 0;
        }

        // ===== VOLUME TOTAL (dari budidaya_matrix_kabkota)
        if ($hasTable('budidaya_matrix_kabkota')) {
            $volume_result = $qAll(
                "SELECT SUM(volume_ton) AS total_volume 
                 FROM budidaya_matrix_kabkota 
                 WHERE tahun = ?",
                [$tahun]
            );
            $year_data['volume_total'] = $volume_result[0]['total_volume'] ? (float)$volume_result[0]['total_volume'] : 0.0;
        }

        // ===== NILAI TOTAL (dari budidaya_matrix_kabkota)
        if ($hasTable('budidaya_matrix_kabkota')) {
            $nilai_result = $qAll(
                "SELECT SUM(nilai_rp) AS total_nilai 
                 FROM budidaya_matrix_kabkota 
                 WHERE tahun = ?",
                [$tahun]
            );
            $year_data['nilai_total'] = $nilai_result[0]['total_nilai'] ? (float)$nilai_result[0]['total_nilai'] : 0.0;
        }

        $yearly_data[] = $year_data;
    }

    // ===== 3) CALCULATE YEAR-OVER-YEAR GROWTH
    for ($i = 0; $i < count($yearly_data); $i++) {
        if ($i === 0) {
            // First year has no growth
            $yearly_data[$i]['growth_luas'] = 0.0;
            $yearly_data[$i]['growth_pembudidaya'] = 0.0;
            $yearly_data[$i]['growth_pembenihan'] = 0.0;
            $yearly_data[$i]['growth_volume'] = 0.0;
            $yearly_data[$i]['growth_nilai'] = 0.0;
        } else {
            $prev = $yearly_data[$i - 1];
            $curr = $yearly_data[$i];

            // Calculate growth for each metric
            $yearly_data[$i]['growth_luas'] = $prev['luas_total'] > 0 
                ? round((($curr['luas_total'] - $prev['luas_total']) / $prev['luas_total']) * 100, 2) 
                : 0.0;
            
            $yearly_data[$i]['growth_pembudidaya'] = $prev['pembudidaya_total'] > 0 
                ? round((($curr['pembudidaya_total'] - $prev['pembudidaya_total']) / $prev['pembudidaya_total']) * 100, 2) 
                : 0.0;
            
            $yearly_data[$i]['growth_pembenihan'] = $prev['pembenihan_total'] > 0 
                ? round((($curr['pembenihan_total'] - $prev['pembenihan_total']) / $prev['pembenihan_total']) * 100, 2) 
                : 0.0;
            
            $yearly_data[$i]['growth_volume'] = $prev['volume_total'] > 0 
                ? round((($curr['volume_total'] - $prev['volume_total']) / $prev['volume_total']) * 100, 2) 
                : 0.0;
            
            $yearly_data[$i]['growth_nilai'] = $prev['nilai_total'] > 0 
                ? round((($curr['nilai_total'] - $prev['nilai_total']) / $prev['nilai_total']) * 100, 2) 
                : 0.0;
        }
    }

    // ===== OUTPUT - Ensure valid JSON response
    $response = [
        'ok' => true,
        'available_years' => array_map('intval', $available_years),
        'yearly_data' => $yearly_data
    ];
    
    // Set proper content type header
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    exit;

} catch (Throwable $e) {
    // Log error for debugging
    error_log('Backend error: ' . $e->getMessage());
    
    // Ensure valid JSON response even in error case
    $errorResponse = [
        'ok' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ];
    
    header('Content-Type: application/json');
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}