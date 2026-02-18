<?php
// /download_template.php
// Downloader aman untuk file template Excel (whitelist + header yang benar)
declare(strict_types=1);

$BASE_DIR = __DIR__ . '/templates';

$MAP = [
  // Budidaya (7)
  'ringkasan' => 'Ringkasan Budidaya.xlsx',
  'volume'    => 'Volume per Bulan per Wadah.xlsx',
  'nilai'     => 'Nilai per Bulan per Wadah.xlsx',
  'luas'      => 'Luas Lahan Budidaya.xlsx',
  'prodkk'    => 'Produksi Budidaya KabKota.xlsx',
  'pemb'      => 'Pembudidaya.xlsx',
  'kom'       => 'Komoditas Unggulan.xlsx',
];

// ---- ambil key
$key = isset($_GET['m']) ? trim((string)$_GET['m']) : '';
if (!isset($MAP[$key])) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Template tidak ditemukan.";
  exit;
}

// ---- pastikan file ada & terbaca
$path = realpath($BASE_DIR . DIRECTORY_SEPARATOR . $MAP[$key]);
if (!$path || !is_readable($path)) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  echo "File template tidak dapat dibaca.";
  exit;
}

// ---- paksa download
$fname = basename($path);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'. $fname .'"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
readfile($path);
exit;
