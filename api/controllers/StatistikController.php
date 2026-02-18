<?php

require_once __DIR__ . '/../services/NormalisasiStatistik.php';

$data = json_decode(
  file_get_contents(__DIR__ . '/../data/statistik_2022.json'),
  true
);

$response = [
  "ringkasan" => NormalisasiStatistik::ringkasan($data["ringkasan"]),
  "matrix" => NormalisasiStatistik::matrix($data["matrix"]["rows"]),
  "volume_bulanan" => NormalisasiStatistik::bulanan($data["volume_bulanan"]),
  "nilai_bulanan" => NormalisasiStatistik::bulanan($data["nilai_bulanan"]),
  "komoditas" => NormalisasiStatistik::komoditas($data["komoditas"])
];

header('Content-Type: application/json');
echo json_encode($response);
