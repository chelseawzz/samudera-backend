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

require_once __DIR__ . '/db.php';


function out_ok(array $data) {
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function out_err(string $msg, int $code = 500) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // ===== Param tahun
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
    if ($tahun < 2000 || $tahun > 2100) {
        out_err('Param tahun tidak valid', 400);
    }

    $pdo = pdo();
    $dbname = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

    // ===== Helpers: schema introspection
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

    // ===== 1) Ringkasan (dari budidaya_ringkasan)
    $ringkasan = [];
    if ($hasTable('budidaya_ringkasan')) {
        $ringkasan = $qAll(
            "SELECT uraian, nilai, satuan 
             FROM budidaya_ringkasan 
             WHERE tahun = ? 
             ORDER BY 
               CASE uraian
                 WHEN 'Volume total produksi budidaya' THEN 1
                 WHEN 'Nilai produksi total budidaya' THEN 2
                 WHEN 'Jumlah unit pembenihan' THEN 3
                 WHEN 'Jumlah total pembudidaya' THEN 4
                 WHEN 'Jumlah total luasan budidaya' THEN 5
                 ELSE 99
               END",
            [$tahun]
        );
    }

    // ===== 2) Matrix Kab/Kota (dari budidaya_matrix_kabkota)
    $matrix = ['rows' => []];
    if ($hasTable('budidaya_matrix_kabkota')) {
        $matrix['rows'] = $qAll(
            "SELECT 
                kab_kota AS Wilayah,
                SUM(CASE WHEN subsektor = 'Laut' THEN volume_ton ELSE 0 END) AS Volume_Laut,
                SUM(CASE WHEN subsektor = 'Tambak' THEN volume_ton ELSE 0 END) AS Volume_Tambak,
                SUM(CASE WHEN subsektor = 'Kolam' THEN volume_ton ELSE 0 END) AS Volume_Kolam,
                SUM(CASE WHEN subsektor = 'Mina Padi' THEN volume_ton ELSE 0 END) AS Volume_MinaPadi,
                SUM(CASE WHEN subsektor = 'Karamba' THEN volume_ton ELSE 0 END) AS Volume_Karamba,
                SUM(CASE WHEN subsektor = 'Jaring Apung' THEN volume_ton ELSE 0 END) AS Volume_JaringApung,
                SUM(CASE WHEN subsektor = 'Ikan Hias' THEN volume_ton ELSE 0 END) AS Volume_IkanHias,
                SUM(CASE WHEN subsektor = 'Pembenihan' THEN volume_ton ELSE 0 END) AS Volume_Pembenihan,
                
                SUM(CASE WHEN subsektor = 'Laut' THEN nilai_rp ELSE 0 END) AS Nilai_Laut,
                SUM(CASE WHEN subsektor = 'Tambak' THEN nilai_rp ELSE 0 END) AS Nilai_Tambak,
                SUM(CASE WHEN subsektor = 'Kolam' THEN nilai_rp ELSE 0 END) AS Nilai_Kolam,
                SUM(CASE WHEN subsektor = 'Mina Padi' THEN nilai_rp ELSE 0 END) AS Nilai_MinaPadi,
                SUM(CASE WHEN subsektor = 'Karamba' THEN nilai_rp ELSE 0 END) AS Nilai_Karamba,
                SUM(CASE WHEN subsektor = 'Jaring Apung' THEN nilai_rp ELSE 0 END) AS Nilai_JaringApung,
                SUM(CASE WHEN subsektor = 'Ikan Hias' THEN nilai_rp ELSE 0 END) AS Nilai_IkanHias,
                SUM(CASE WHEN subsektor = 'Pembenihan' THEN nilai_rp ELSE 0 END) AS Nilai_Pembenihan
             FROM budidaya_matrix_kabkota 
             WHERE tahun = ? 
             GROUP BY kab_kota 
             ORDER BY kab_kota",
            [$tahun]
        );
    }

    // ===== 3) Pembudidaya Detail (dari budidaya_pembudidaya_detail)
    $pembudidaya = [];
    if ($hasTable('budidaya_pembudidaya_detail')) {
        $pembudidaya = $qAll(
            "SELECT kab_kota, subsektor, peran, jumlah 
             FROM budidaya_pembudidaya_detail 
             WHERE tahun = ? 
             ORDER BY kab_kota, subsektor, peran",
            [$tahun]
        );
    }

    // ===== 4) Pembenihan Unit (dari budidaya_pembenihan_ringkas)
    $pembenihan = [];
    if ($hasTable('budidaya_pembenihan_ringkas')) {
        $pembenihan = $qAll(
            "SELECT jenis_air, bbi, upr, hsrt, swasta, pembibit_rula 
             FROM budidaya_pembenihan_ringkas 
             WHERE tahun = ? 
             ORDER BY jenis_air",
            [$tahun]
        );
    }

    // ===== 5) Komoditas Wilayah (dari komoditas_wilayah) - DATA LAMA (TIDAK DIPAKAI)
    $komoditas = [];
    if ($hasTable('komoditas_wilayah')) {
       $komoditas = $qAll(
            "SELECT no, komoditas, volume, is_sub, is_note, tahun
            FROM komoditas_wilayah
            WHERE tahun = ?
            ORDER BY no, is_sub, komoditas",
            [$tahun]
        );
    }

    // ===== 6) KOMODITAS BUDIDAYA BARU (dari komoditas_budidaya) - 65 KOMODITAS
    $komoditas_budidaya = [];
    if ($hasTable('komoditas_budidaya')) {
        $komoditas_budidaya = $qAll(
            "SELECT 
                no,
                komoditas,
                laut_volume,
                laut_nilai,
                tambak_volume,
                tambak_nilai,
                kolam_volume,
                kolam_nilai,
                mina_padi_volume,
                mina_padi_nilai,
                karamba_volume,
                karamba_nilai,
                japung_volume,
                japung_nilai
             FROM komoditas_budidaya 
             WHERE tahun = ?
             ORDER BY no",
            [$tahun]
        );
        
        // Convert numeric values to proper types
        foreach ($komoditas_budidaya as &$item) {
            $item['no'] = (int)$item['no'];
            $item['laut_volume'] = (float)$item['laut_volume'];
            $item['laut_nilai'] = (float)$item['laut_nilai'];
            $item['tambak_volume'] = (float)$item['tambak_volume'];
            $item['tambak_nilai'] = (float)$item['tambak_nilai'];
            $item['kolam_volume'] = (float)$item['kolam_volume'];
            $item['kolam_nilai'] = (float)$item['kolam_nilai'];
            $item['mina_padi_volume'] = (float)$item['mina_padi_volume'];
            $item['mina_padi_nilai'] = (float)$item['mina_padi_nilai'];
            $item['karamba_volume'] = (float)$item['karamba_volume'];
            $item['karamba_nilai'] = (float)$item['karamba_nilai'];
            $item['japung_volume'] = (float)$item['japung_volume'];
            $item['japung_nilai'] = (float)$item['japung_nilai'];
        }
    }

    // ===== 7) LUAS PER KOMPONEN (dari budidaya_luas)
    $luas_per_komponen = [];
    if ($hasTable('budidaya_luas')) {
        $luas_per_komponen = $qAll(
            "SELECT uraian AS komponen, luas_bersih_ha AS luas 
             FROM budidaya_luas 
             WHERE tahun = ? AND uraian != 'TOTAL'
             ORDER BY 
               CASE uraian
                 WHEN 'Laut' THEN 1
                 WHEN 'Tambak' THEN 2
                 WHEN 'Kolam' THEN 3
                 WHEN 'Karamba' THEN 4
                 WHEN 'Jaring Apung' THEN 5
                 WHEN 'Mina Padi' THEN 6
                 WHEN 'Ikan Hias' THEN 7
                 ELSE 99
               END",
            [$tahun]
        );
    }

    // ===== 8) PEMBUDIDAYA PER KOMPONEN (dari budidaya_pembudidaya)
    $pembudidaya_per_komponen = [];
    if ($hasTable('budidaya_pembudidaya')) {
        $pembudidaya_per_komponen = $qAll(
            "SELECT uraian AS komponen, jumlah 
             FROM budidaya_pembudidaya 
             WHERE tahun = ? AND uraian != 'TOTAL'
             ORDER BY 
               CASE uraian
                 WHEN 'Laut' THEN 1
                 WHEN 'Tambak' THEN 2
                 WHEN 'Kolam' THEN 3
                 WHEN 'Karamba' THEN 4
                 WHEN 'Jaring Apung' THEN 5
                 WHEN 'Mina Padi' THEN 6
                 WHEN 'Ikan Hias' THEN 7
                 WHEN 'Pembenihan' THEN 8
                 ELSE 99
               END",
            [$tahun]
        );
    }

    // ===== 9) VOLUME BULANAN PER KOMPONEN (dari budidaya_volume_bulanan)
    $volume_bulanan = [];
    if ($hasTable('budidaya_volume_bulanan')) {
        $volume_bulanan = $qAll(
            "SELECT uraian AS komponen, jan, feb, mar, apr, mei, jun, jul, agu, sep, okt, nov, des 
             FROM budidaya_volume_bulanan 
             WHERE tahun = ?
             ORDER BY 
               CASE uraian
                 WHEN 'Laut' THEN 1
                 WHEN 'Tambak' THEN 2
                 WHEN 'Kolam' THEN 3
                 WHEN 'Mina Padi' THEN 4
                 WHEN 'Karamba' THEN 5
                 WHEN 'Jaring Apung' THEN 6
                 ELSE 99
               END",
            [$tahun]
        );
    }

    // ===== 10) NILAI BULANAN PER KOMPONEN (dari budidaya_nilai_bulanan)
    $nilai_bulanan = [];
    if ($hasTable('budidaya_nilai_bulanan')) {
        $nilai_bulanan = $qAll(
            "SELECT uraian AS komponen, jan, feb, mar, apr, mei, jun, jul, agu, sep, okt, nov, des 
             FROM budidaya_nilai_bulanan 
             WHERE tahun = ?
             ORDER BY 
               CASE uraian
                 WHEN 'Laut' THEN 1
                 WHEN 'Tambak' THEN 2
                 WHEN 'Kolam' THEN 3
                 WHEN 'Mina Padi' THEN 4
                 WHEN 'Karamba' THEN 5
                 WHEN 'Jaring Apung' THEN 6
                 ELSE 99
               END",
            [$tahun]
        );
    }

    // ===== 11) PRODUKSI IKAN HIAS - VOLUME (dari produksi_budidaya_ikan_hias)
    $produksi_ikan_hias = [];
    if ($hasTable('produksi_budidaya_ikan_hias')) {
        $produksi_ikan_hias = $qAll(
            "SELECT 
                kabupaten_kota,
                total_volume,
                arwana, koi, grasscarp, mas, mas_koki, mutiara, akara, barbir, gapi, cupang,
                lalia, manvis, black_molly, oskar, platy, rainbow, louhan, sumatra, lele_blorok,
                komet, blackghost, kar_tetra, marble, golden, discus, zebra, cawang, balasak,
                red_fin, lemon, niasa, lobster, silver, juani, lainnya
            FROM produksi_budidaya_ikan_hias 
            WHERE tahun = ?
            ORDER BY kabupaten_kota",
            [$tahun]
        );
    }

    // ===== 12) NILAI PRODUKSI IKAN HIAS (dari nilai_produksi_budidaya_ikan_hias)
    $nilai_ikan_hias = [];
    if ($hasTable('nilai_produksi_budidaya_ikan_hias')) {
        $nilai_ikan_hias = $qAll(
            "SELECT 
                kabupaten_kota,
                total_value,
                arwana, koi, grasscarp, mas, mas_koki, mutiara, akara, barbir, gapi, cupang,
                lalia, manvis, black_molly, oskar, platy, rainbow, louhan, sumatra, lele_blorok,
                komet, blackghost, kar_tetra, marble, golden, discus, zebra, cawang, balasak,
                red_fin, lemon, niasa, lobster, silver, juani, lainnya
             FROM nilai_produksi_budidaya_ikan_hias 
             WHERE tahun = ?
             ORDER BY kabupaten_kota",
            [$tahun]
        );
    }

    // ===== Output
    out_ok([
        'ringkasan' => $ringkasan,
        'matrix' => $matrix,
        'pembudidaya' => $pembudidaya,
        'pembenihan' => $pembenihan,
        'komoditas' => $komoditas, // Data lama (tidak dipakai di frontend)
        'komoditas_budidaya' => $komoditas_budidaya, // Data baru (65 komoditas)
        'luas_per_komponen' => $luas_per_komponen,
        'pembudidaya_per_komponen' => $pembudidaya_per_komponen,
        'volume_bulanan' => $volume_bulanan,
        'nilai_bulanan' => $nilai_bulanan,
        'produksi_ikan_hias' => $produksi_ikan_hias,
        'nilai_ikan_hias' => $nilai_ikan_hias,
    ]);

} catch (Throwable $e) {
    out_err('Server error: ' . $e->getMessage(), 500);
}