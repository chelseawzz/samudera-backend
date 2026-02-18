<?php
// START OUTPUT BUFFERING - TANGKAP SEMUA OUTPUT SEBELUM FILE
ob_start();

// SET ERROR HANDLING - JANGAN TAMPILKAN ERROR KE BROWSER
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// HANDLE PREFLIGHT OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: http://localhost:5173');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code(200);
    ob_end_clean(); 
    exit();
}

// SET CORS HEADERS
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $path = $_GET['path'] ?? '';
    
    if (empty($path)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Path tidak valid']);
        exit;
    }
    
    $path = preg_replace('/[^a-zA-Z0-9._\-\/]/', '', $path);
    $filePath = __DIR__ . '/../uploads/' . basename($path);
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'File tidak ditemukan']);
        exit;
    }
    
    ob_clean();
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Gagal download file: ' . $e->getMessage()]);
    exit;
}

if (ob_get_length() > 0) {
    $unexpected = ob_get_contents();
    if (!empty(trim($unexpected))) {
        error_log("Output buffer tidak kosong: " . substr($unexpected, 0, 500));
    }
    ob_end_clean();
}
?>