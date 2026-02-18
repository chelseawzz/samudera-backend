<?php
declare(strict_types=1);
require_once __DIR__.'/../api/db.php';

header('Access-Control-Allow-Origin: http://localhost');
header('Content-Type: application/json; charset=utf-8');

try {
  // Ambil daftar wilayah dari tabel kpp_garam (bisa juga dari tabel lain)
  $stmt = pdo()->query("SELECT DISTINCT kab_kota FROM kpp_garam ORDER BY kab_kota");
  $regions = $stmt->fetchAll(PDO::FETCH_COLUMN);

  $result = [];

  foreach ($regions as $region) {
    // Bersihkan nama wilayah (hapus "Kabupaten ", "Kota ", dll)
    $cleanRegion = preg_replace('/^(Kabupaten|Kota)\s+/i', '', $region);
    
    $data = [
      'region' => $cleanRegion,
      'tangkap' => 0,
      'budidaya' => 0,
      'kpp' => 0,
      'pengolahan' => 0,
      'ekspor' => 0,
      'investasi' => 0
    ];

    // Hitung data berdasarkan tabel yang ada
    // 1. Tangkap → dari tangkap_ringkasan atau tangkap_produksi_matrix
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM tangkap_produksi_matrix WHERE kab_kota LIKE ?");
    $stmt->execute(["%$region%"]);
    $data['tangkap'] = (int)$stmt->fetchColumn();

    // 2. Budidaya → dari budidaya_produksi_kabkota
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM budidaya_produksi_kabkota WHERE kabkota LIKE ?");
    $stmt->execute(["%$region%"]);
    $data['budidaya'] = (int)$stmt->fetchColumn();

    // 3. KPP → dari kpp_garam
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM kpp_garam WHERE kab_kota = ?");
    $stmt->execute([$region]);
    $data['kpp'] = (int)$stmt->fetchColumn();

    // 4. Pengolahan → dari pengolahan_pemasaran_aki
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM pengolahan_pemasaran_aki WHERE kab_kota LIKE ?");
    $stmt->execute(["%$region%"]);
    $data['pengolahan'] = (int)$stmt->fetchColumn();

    // 5. Ekspor → dari ekspor_perikanan_ringkasan
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM ekspor_perikanan_ringkasan WHERE created_at IS NOT NULL");
    $stmt->execute();
    $data['ekspor'] = (int)$stmt->fetchColumn(); // Atau hitung per wilayah jika ada kolom wilayah

    // 6. Investasi → dari investasi_rekap_kota
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM investasi_rekap_kota WHERE kab_kota LIKE ?");
    $stmt->execute(["%$region%"]);
    $data['investasi'] = (int)$stmt->fetchColumn();

    $result[] = $data;
  }

  echo json_encode($result);

} catch (Throwable $e) {
  error_log("Regional data error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Gagal mengambil data']);
}