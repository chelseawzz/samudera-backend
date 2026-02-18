<?php
declare(strict_types=1);

// HANDLE PREFLIGHT & SET CORS DI AWAL

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ob_start(); 

require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// STRUKTUR TEMPLATE LENGKAP

$templateHeaders = [
    'tangkap' => [
        'ringkasan' => ['cabang_usaha', 'nelayan_orang', 'rtp_pp', 'armada_buah', 'alat_tangkap_unit', 'volume_ton', 'nilai_rp_1000'],
        'wilayah' => ['kab_kota', 'jenis_perairan', 'nelayan_orang', 'armada_buah', 'alat_tangkap_unit', 'rtp_pp', 'volume_ton', 'nilai_rp'],
        'produksi_matrix' => ['kab_kota', 'subsektor', 'volume_ton'],
        'volume_bulanan' => ['uraian', 'januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember', 'jumlah'],
        'nilai_bulanan' => ['uraian', 'januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember', 'jumlah'],
        'komoditas' => ['no', 'komoditas', 'volume']
    ],
    'budidaya' => [
        'ringkasan' => ['uraian', 'nilai', 'satuan'],
        'matrix_kab_kota' => ['kab_kota', 'subsektor', 'volume_ton', 'nilai_rp'],
        'pembudidaya_detail' => ['kab_kota', 'subsektor', 'peran', 'jumlah'],
        'pembenihan_ringkas' => ['jenis_air', 'bbi', 'upr', 'hsrt', 'swasta', 'pembibit_rula'],
        'komoditas_budidaya' => ['no', 'komoditas', 'laut_volume', 'laut_nilai', 'tambak_volume', 'tambak_nilai', 'kolam_volume', 'kolam_nilai', 'mina_padi_volume', 'mina_padi_nilai', 'karamba_volume', 'karamba_nilai', 'japung_volume', 'japung_nilai'],
        'luas_area' => ['uraian', 'luas_bersih_ha'],
        'pembudidaya' => ['uraian', 'jumlah'],
        'volume_bulanan' => ['uraian', 'jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agu', 'sep', 'okt', 'nov', 'des'],
        'nilai_bulanan' => ['uraian', 'jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agu', 'sep', 'okt', 'nov', 'des'],
        'produksi_ikan_hias_volume' => ['kabupaten_kota', 'total_volume', 'arwana', 'koi', 'grasscarp', 'mas', 'mas_koki', 'mutiara', 'akara', 'barbir', 'gapi', 'cupang', 'lalia', 'manvis', 'black_molly', 'oskar', 'platy', 'rainbow', 'louhan', 'sumatra', 'lele_blorok', 'komet', 'blackghost', 'kar_tetra', 'marble', 'golden', 'discus', 'zebra', 'cawang', 'balasak', 'red_fin', 'lemon', 'niasa', 'lobster', 'silver', 'juani', 'lainnya'],
        'nilai_ikan_hias' => ['kabupaten_kota', 'total_value', 'arwana', 'koi', 'grasscarp', 'mas', 'mas_koki', 'mutiara', 'akara', 'barbir', 'gapi', 'cupang', 'lalia', 'manvis', 'black_molly', 'oskar', 'platy', 'rainbow', 'louhan', 'sumatra', 'lele_blorok', 'komet', 'blackghost', 'kar_tetra', 'marble', 'golden', 'discus', 'zebra', 'cawang', 'balasak', 'red_fin', 'lemon', 'niasa', 'lobster', 'silver', 'juani', 'lainnya']
    ],
    'kpp' => [
        'data_garam' => ['kab_kota', 'l_total_ha', 'luas_lahan_ha', 'jumlah_kelompok', 'sigma_petambak', 'sigma_prod_ton', 'jumlah_petambak', 'volume_produksi_ton']
    ],
    'pengolahan' => [
        'aki' => ['kab_kota', 'kidrt', 'kilrt', 'ktt', 'aki'],
        'pemasaran' => ['kab_kota', 'pengecer', 'pengumpul', 'jumlah_unit'],
        'olahan_per_kab_kota' => ['kab_kota', 'fermentasi', 'pelumatan_daging_ikan', 'pembekuan', 'pemindangan', 'penanganan_produk_segar', 'pengalengan', 'pengasapan_pemanggangan', 'pereduksian_ekstraksi', 'penggaraman_pengeringan', 'pengolahan_lainnya', 'jumlah_unit'],
        'olahan_per_jenis' => ['jenis_kegiatan_pengolahan', 'jumlah_upi']
    ],
    'ekspor' => [
        'total_ekspor' => ['komoditas', 'volume_ton', 'nilai_usd'],
        'komoditas_utama' => ['sisi', 'no_urut', 'komoditas', 'angka'],
        'ringkasan_negara' => ['urut', 'negara', 'jumlah_ton', 'nilai_usd']
    ]
];

// MAIN LOGIC

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $bidang = $_GET['bidang'] ?? '';
    $templateSlug = $_GET['template'] ?? '';
    
    $validBidang = ['tangkap', 'budidaya', 'kpp', 'pengolahan', 'ekspor'];
    
    // Validasi parameter
    if (empty($bidang) || !in_array($bidang, $validBidang)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Bidang tidak valid']);
        exit;
    }
    
    if (empty($templateSlug) || !isset($templateHeaders[$bidang][$templateSlug])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Template '{$templateSlug}' tidak ditemukan untuk bidang '{$bidang}'"]);
        exit;
    }
    
    $headers = $templateHeaders[$bidang][$templateSlug];
    $templateDir = __DIR__ . '/../templates';
    
    if (!file_exists($templateDir)) {
        mkdir($templateDir, 0755, true);
    }
    
    $templateFile = $templateDir . "/template_{$bidang}_{$templateSlug}.xlsx";
    
    // Generate template jika belum ada
    if (!file_exists($templateFile)) {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'PhpSpreadsheet tidak terinstall']);
            exit;
        }
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set header row
        $headerRow = 1;
        foreach ($headers as $colIndex => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($colLetter . $headerRow, $header);
        }
        
        // Style header
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1e40af']
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]
            ]
        ]);
        
        // Set column width
        for ($i = 1; $i <= count($headers); $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setWidth(18);
        }
        
        // Simpan file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($templateFile);
    }
    
    // Kirim file ke client
    ob_end_clean();
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template_' . $bidang . '_' . $templateSlug . '_' . date('Y-m-d') . '.xlsx"');
    header('Content-Length: ' . filesize($templateFile));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    readfile($templateFile);
    exit;
    
} catch (Exception $e) {
    ob_end_clean();
    
    header("Access-Control-Allow-Origin: http://localhost:5173");
    header("Access-Control-Allow-Credentials: true");
    header('Content-Type: application/json');
    
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal generate template',
        'debug' => defined('DEBUG') ? $e->getMessage() : null
    ]);
    exit;
}
?>