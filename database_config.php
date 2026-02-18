<?php
/**
 * Database Configuration for Samudata Application
 * Compatible with Laragon MySQL setup
 */

class DatabaseConfig {
    private static $connection = null;

    public static function getConnection() {
        if (self::$connection === null) {
            try {
                $host    = getenv("MYSQLHOST");
                $port    = getenv("MYSQLPORT") ?: "3306";
                $dbname  = getenv("MYSQLDATABASE");
                $user    = getenv("MYSQLUSER");   
                $pass    = getenv("MYSQLPASSWORD");
                $charset = "utf8mb4";

                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

                self::$connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $charset
                ]);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please check Railway DB config.");
            }
        }
        return self::$connection;
    }
}
    
    /**
     * Initialize database (create tables if not exist)
     */

    public static function initializeDatabase() {
        try {
            $pdo = self::getConnection();
            
            // Read and execute schema file
            $schemaFile = __DIR__ . '/database_schema.sql';
            if (file_exists($schemaFile)) {
                $schema = file_get_contents($schemaFile);
                
                // Split by semicolon and execute each statement
                $statements = array_filter(array_map('trim', explode(';', $schema)));
                
                foreach ($statements as $statement) {
                    if (!empty($statement) && !preg_match('/^--/', $statement)) {
                        $pdo->exec($statement);
                    }
                }
                
                return true;
            } else {
                throw new Exception("Schema file not found: " . $schemaFile);
            }
        } catch (Exception $e) {
            error_log("Database initialization failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test database connection
     */
    public static function testConnection() {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query("SELECT 1");
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get database statistics
     */
    public static function getStats() {
        try {
            $pdo = self::getConnection();
            
            $stats = [];
            
            // Get file counts by category
            $stmt = $pdo->query("
                SELECT c.name, c.display_name, COUNT(f.id) as count 
                FROM categories c 
                LEFT JOIN files f ON c.id = f.category_id AND f.status = 'active'
                GROUP BY c.id
            ");
            $stats['categories'] = $stmt->fetchAll();
            
            // Get total files
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM files WHERE status = 'active'");
            $stats['total_files'] = $stmt->fetch()['total'];
            
            // Get total size
            $stmt = $pdo->query("SELECT SUM(file_size) as total_size FROM files WHERE status = 'active'");
            $stats['total_size'] = $stmt->fetch()['total_size'] ?? 0;
            
            // Get recent uploads
            $stmt = $pdo->query("
                SELECT f.title, f.original_filename, c.display_name as category, 
                       r.name as region, f.created_at
                FROM files f
                JOIN categories c ON f.category_id = c.id
                JOIN regions r ON f.region_id = r.id
                WHERE f.status = 'active'
                ORDER BY f.created_at DESC
                LIMIT 10
            ");
            $stats['recent_uploads'] = $stmt->fetchAll();
            
            return $stats;
        } catch (Exception $e) {
            error_log("Failed to get database stats: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * File Manager Class
 */
class FileManager {
    private $pdo;
    private $uploadDir;
    
    public function __construct() {
        $this->pdo = DatabaseConfig::getConnection();
        $this->uploadDir = __DIR__ . '/uploads/';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Upload a file
     */
    public function uploadFile($fileData, $metadata) {
        try {
            // Validate file
            if (!$this->validateFile($fileData)) {
                throw new Exception("File validation failed");
            }
            
            // Generate unique filename
            $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $filePath = $this->uploadDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
                throw new Exception("Failed to move uploaded file");
            }
            
            // Calculate file hash
            $fileHash = hash_file('sha256', $filePath);
            
            // Check for duplicates
            $stmt = $this->pdo->prepare("SELECT id FROM files WHERE file_hash = ? AND status = 'active'");
            $stmt->execute([$fileHash]);
            if ($stmt->fetch()) {
                unlink($filePath); // Delete duplicate file
                throw new Exception("File already exists in the system");
            }
            
            // Insert file record
            $stmt = $this->pdo->prepare("
                INSERT INTO files (
                    title, description, filename, original_filename, file_path, 
                    file_size, mime_type, file_hash, category_id, region_id,
                    uploader_name, uploader_email, upload_date, tags, metadata
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $metadata['title'],
                $metadata['description'] ?? '',
                $filename,
                $fileData['name'],
                $filePath,
                $fileData['size'],
                $fileData['type'],
                $fileHash,
                $metadata['category_id'],
                $metadata['region_id'],
                $metadata['uploader_name'],
                $metadata['uploader_email'] ?? '',
                $metadata['upload_date'] ?? date('Y-m-d'),
                json_encode($metadata['tags'] ?? []),
                json_encode($metadata['additional_metadata'] ?? [])
            ]);
            
            if ($result) {
                $fileId = $this->pdo->lastInsertId();
                
                // Log the upload
                $this->logFileAccess($fileId, 'upload');
                
                return [
                    'success' => true,
                    'file_id' => $fileId,
                    'message' => 'File uploaded successfully'
                ];
            } else {
                unlink($filePath);
                throw new Exception("Failed to save file record");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($fileData) {
        // Get allowed extensions from settings
        $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'allowed_extensions'");
        $stmt->execute();
        $allowedExtensions = json_decode($stmt->fetch()['setting_value'] ?? '[]', true);
        
        // Get max file size from settings
        $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'max_file_size'");
        $stmt->execute();
        $maxFileSize = (int)($stmt->fetch()['setting_value'] ?? 52428800); // 50MB default
        
        // Check file size
        if ($fileData['size'] > $maxFileSize) {
            throw new Exception("File size exceeds maximum allowed size");
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception("File type not allowed");
        }
        
        // Check for upload errors
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $fileData['error']);
        }
        
        return true;
    }
    
    /**
     * Get files with filters
     */
    public function getFiles($filters = []) {
        try {
            $sql = "
                SELECT f.*, c.display_name as category_name, r.name as region_name
                FROM files f
                JOIN categories c ON f.category_id = c.id
                JOIN regions r ON f.region_id = r.id
                WHERE f.status = 'active'
            ";
            
            $params = [];
            
            if (!empty($filters['category'])) {
                $sql .= " AND c.name = ?";
                $params[] = $filters['category'];
            }
            
            if (!empty($filters['region'])) {
                $sql .= " AND r.id = ?";
                $params[] = $filters['region'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (f.title LIKE ? OR f.description LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY f.created_at DESC";
            
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT ?";
                $params[] = (int)$filters['limit'];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get files: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Download file
     */
    public function downloadFile($fileId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM files 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch();
            
            if (!$file) {
                return ['success' => false, 'message' => 'File not found'];
            }
            
            if (!file_exists($file['file_path'])) {
                return ['success' => false, 'message' => 'Physical file not found'];
            }
            
            // Update download count
            $stmt = $this->pdo->prepare("UPDATE files SET download_count = download_count + 1 WHERE id = ?");
            $stmt->execute([$fileId]);
            
            // Log the download
            $this->logFileAccess($fileId, 'download');
            
            return [
                'success' => true,
                'file_path' => $file['file_path'],
                'original_filename' => $file['original_filename'],
                'mime_type' => $file['mime_type'],
                'file_size' => $file['file_size']
            ];
        } catch (Exception $e) {
            error_log("Download failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Log file access
     */
    private function logFileAccess($fileId, $action) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO file_access_logs (file_id, action, ip_address, user_agent)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $fileId,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Failed to log file access: " . $e->getMessage());
        }
    }
    
    /**
     * Get categories
     */
    public function getCategories() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY name");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get regions
     */
    public function getRegions() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM regions ORDER BY name");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Toggle favorite status
     */
    public function toggleFavorite($fileId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE files SET is_favorite = NOT is_favorite WHERE id = ? AND status = 'active'");
            $result = $stmt->execute([$fileId]);
            
            if ($result) {
                $this->logFileAccess($fileId, 'update');
                return ['success' => true, 'message' => 'Favorite status updated'];
            } else {
                throw new Exception("Failed to update favorite status");
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Archive file
     */
    public function archiveFile($fileId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE files SET is_archived = TRUE WHERE id = ? AND status = 'active'");
            $result = $stmt->execute([$fileId]);
            
            if ($result) {
                $this->logFileAccess($fileId, 'update');
                return ['success' => true, 'message' => 'File archived successfully'];
            } else {
                throw new Exception("Failed to archive file");
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Delete file
     */
    public function deleteFile($fileId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE files SET status = 'deleted' WHERE id = ? AND status = 'active'");
            $result = $stmt->execute([$fileId]);
            
            if ($result) {
                $this->logFileAccess($fileId, 'delete');
                return ['success' => true, 'message' => 'File deleted successfully'];
            } else {
                throw new Exception("Failed to delete file");
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Edit file metadata
     */
    public function editFile($fileId, $metadata) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE files 
                SET title = ?, description = ?, tags = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND status = 'active'
            ");
            $result = $stmt->execute([
                $metadata['title'],
                $metadata['description'],
                json_encode($metadata['tags']),
                $fileId
            ]);
            
            if ($result) {
                $this->logFileAccess($fileId, 'update');
                return ['success' => true, 'message' => 'File updated successfully'];
            } else {
                throw new Exception("Failed to update file");
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get activity logs
     */
    public function getActivityLogs($startDate, $endDate, $actionFilter = '') {
        try {
            $sql = "
                SELECT l.*, f.title as file_title, f.original_filename, f.uploader_name
                FROM file_access_logs l
                LEFT JOIN files f ON l.file_id = f.id
                WHERE DATE(l.accessed_at) BETWEEN ? AND ?
            ";
            
            $params = [$startDate, $endDate];
            
            if (!empty($actionFilter)) {
                $sql .= " AND l.action = ?";
                $params[] = $actionFilter;
            }
            
            $sql .= " ORDER BY l.accessed_at DESC LIMIT 1000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get activity logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get log statistics
     */
    public function getLogStatistics() {
        try {
            $stats = [];
            
            // Total uploads
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM file_access_logs WHERE action = 'upload'");
            $stats['total_uploads'] = $stmt->fetch()['count'];
            
            // Total downloads
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM file_access_logs WHERE action = 'download'");
            $stats['total_downloads'] = $stmt->fetch()['count'];
            
            // Total views
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM file_access_logs WHERE action = 'view'");
            $stats['total_views'] = $stmt->fetch()['count'];
            
            // Today's activities
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM file_access_logs WHERE DATE(accessed_at) = CURDATE()");
            $stats['today_activities'] = $stmt->fetch()['count'];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Failed to get log statistics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Export logs as CSV
     */
    public function exportLogs($startDate, $endDate, $actionFilter = '') {
        try {
            $logs = $this->getActivityLogs($startDate, $endDate, $actionFilter);
            
            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="log_aktivitas_' . $startDate . '_' . $endDate . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($output, ['Waktu', 'Aktivitas', 'File', 'User', 'IP Address']);
            
            // CSV data
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['accessed_at'],
                    $log['action'],
                    $log['file_title'] ?: 'File tidak ditemukan',
                    $log['uploader_name'] ?: 'System',
                    $log['ip_address']
                ]);
            }
            
            fclose($output);
        } catch (Exception $e) {
            error_log("Failed to export logs: " . $e->getMessage());
        }
    }
    
    /**
     * Get file requests
     */
    public function getFileRequests($statusFilter = '', $priorityFilter = '', $categoryFilter = '') {
        try {
            $sql = "SELECT * FROM file_requests WHERE 1=1";
            $params = [];
            
            if (!empty($statusFilter)) {
                $sql .= " AND status = ?";
                $params[] = $statusFilter;
            }
            
            if (!empty($priorityFilter)) {
                $sql .= " AND priority = ?";
                $params[] = $priorityFilter;
            }
            
            if (!empty($categoryFilter)) {
                $sql .= " AND category = ?";
                $params[] = $categoryFilter;
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get file requests: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get request statistics
     */
    public function getRequestStatistics() {
        try {
            $stats = [];
            
            $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM file_requests GROUP BY status");
            $results = $stmt->fetchAll();
            
            // Initialize all statuses to 0
            $stats = [
                'pending' => 0,
                'approved' => 0,
                'completed' => 0,
                'rejected' => 0
            ];
            
            // Fill in actual counts
            foreach ($results as $result) {
                $stats[$result['status']] = $result['count'];
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Failed to get request statistics: " . $e->getMessage());
            return [
                'pending' => 0,
                'approved' => 0,
                'completed' => 0,
                'rejected' => 0
            ];
        }
    }
    
    /**
     * Create file request
     */
    public function createFileRequest($requestData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO file_requests (title, description, category, priority, deadline, requester_name)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $requestData['title'],
                $requestData['description'],
                $requestData['category'],
                $requestData['priority'],
                $requestData['deadline'],
                $requestData['requester_name']
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Permintaan berhasil dibuat'];
            } else {
                throw new Exception("Failed to create request");
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update request status
     */
    public function updateRequestStatus($requestId, $status) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE file_requests 
                SET status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$status, $requestId]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Status permintaan berhasil diperbarui'];
            } else {
                throw new Exception("Failed to update request status");
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Make logFileAccess public
     */
    public function logFileAccess($fileId, $action) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO file_access_logs (file_id, action, ip_address, user_agent)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $fileId,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Failed to log file access: " . $e->getMessage());
        }
    }
}
?>

