<?php
header('Content-Type: application/json; charset=utf-8');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:5173', 'http://127.0.0.1:5173', 'http://localhost:5174'];

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit();
    }
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/db.php';

function out_ok(array $data): void {
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    exit;
}

function out_err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'delete') {
            $id = $_POST['id'] ?? null;
            
            if (!$id || !is_numeric($id) || $id <= 0) {
                out_err('ID file tidak valid', 400);
            }
            
            $stmt = $pdo->prepare("SELECT bidang, tahun, original_name, stored_name FROM fm_files WHERE id = ?");
            $stmt->execute([$id]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                out_err('File tidak ditemukan', 404);
            }
            
            $bidang = $file['bidang'];
            $storedName = $file['stored_name'];
           
            $deletedRows = 0;
            try {
                switch ($bidang) {
                    case 'kpp':
                        $stmt = $pdo->prepare("DELETE FROM kpp_garam WHERE uploaded_file_id = ?");
                        $stmt->execute([$id]);
                        $deletedRows += $stmt->rowCount();
                        error_log("Deleted kpp_garam: {$stmt->rowCount()} rows for file_id {$id}");
                        break;
                        
                    case 'tangkap':
                        $tables = [
                            'tangkap_ringkasan', 'tangkap_wilayah', 'tangkap_komoditas',
                            'tangkap_produksi_matrix', 'tangkap_volume_bulanan', 'tangkap_nilai_bulanan'
                        ];
                        foreach ($tables as $table) {
                            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE uploaded_file_id = ?");
                            $stmt->execute([$id]);
                            $deletedRows += $stmt->rowCount();
                            error_log("Deleted {$table}: {$stmt->rowCount()} rows for file_id {$id}");
                        }
                        break;
                        
                    case 'budidaya':
                        $tables = [
                            'budidaya_ringkasan', 'budidaya_matrix_kabkota', 'budidaya_pembudidaya_detail',
                            'budidaya_pembenihan_ringkas', 'komoditas_budidaya', 'budidaya_luas',
                            'budidaya_pembudidaya', 'budidaya_volume_bulanan', 'budidaya_nilai_bulanan',
                            'produksi_ikan_hias_volume', 'nilai_ikan_hias'
                        ];
                        foreach ($tables as $table) {
                            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE uploaded_file_id = ?");
                            $stmt->execute([$id]);
                            $deletedRows += $stmt->rowCount();
                            error_log("Deleted {$table}: {$stmt->rowCount()} rows for file_id {$id}");
                        }
                        break;
                        
                    case 'pengolahan':
                        $tables = [
                            'pengolahan_pemasaran_aki', 'pengolahan_pemasaran_pemasaran',
                            'pengolahan_pemasaran_olahankab', 'pengolahan_pemasaran_olahjenis'
                        ];
                        foreach ($tables as $table) {
                            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE uploaded_file_id = ?");
                            $stmt->execute([$id]);
                            $deletedRows += $stmt->rowCount();
                            error_log("Deleted {$table}: {$stmt->rowCount()} rows for file_id {$id}");
                        }
                        break;
                        
                    case 'ekspor':
                        $tables = [
                            'ekspor_perikanan_total', 'ekspor_perikanan_utama',
                            'ekspor_perikanan_ringkasan', 'ekspor_perikanan_rekap'
                        ];
                        foreach ($tables as $table) {
                            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE uploaded_file_id = ?");
                            $stmt->execute([$id]);
                            $deletedRows += $stmt->rowCount();
                            error_log("Deleted {$table}: {$stmt->rowCount()} rows for file_id {$id}");
                        }
                        break;
                }
                
                error_log("Total deleted rows: {$deletedRows} for file_id {$id}");
                
            } catch (PDOException $e) {
                error_log("Error deleting data: " . $e->getMessage());
                // Jangan stop proses, lanjutkan hapus file metadata
            }
            
            $stmt = $pdo->prepare("DELETE FROM fm_files WHERE id = ?");
            $stmt->execute([$id]);
            
            $filePath = __DIR__ . '/../uploads/' . $storedName;
            if (file_exists($filePath)) {
                unlink($filePath);
                error_log("Deleted physical file: {$storedName}");
            }
            
            out_ok([
                'message' => 'File "' . htmlspecialchars($file['original_name']) . '" dan ' . $deletedRows . ' baris data terkait berhasil dihapus permanen',
                'file_id' => (int)$id,
                'rows_deleted' => $deletedRows
            ]);
        }
        
        out_err('Action tidak dikenali', 400);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $bidang = $_GET['bidang'] ?? null;
        $tahun = $_GET['tahun'] ?? null;
        
        $sql = "SELECT 
                    id,
                    bidang,
                    tahun,
                    original_name AS file_name,
                    CONCAT('uploads/', stored_name) AS file_path,
                    uploaded_at,
                    size_bytes AS file_size,
                    deleted_at
                FROM fm_files 
                WHERE deleted_at IS NULL";
        $params = [];
        
        if ($bidang) {
            $sql .= " AND bidang = ?";
            $params[] = $bidang;
        }
        
        if ($tahun) {
            $sql .= " AND tahun = ?";
            $params[] = (int)$tahun;
        }
        
        $sql .= " ORDER BY uploaded_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        out_ok(['files' => $files]);
    }
    
    out_err('Method tidak dikenali. Hanya GET dan POST yang diperbolehkan.', 405);
    
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    out_err('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
    out_err('Terjadi kesalahan: ' . $e->getMessage(), 500);
}
?>