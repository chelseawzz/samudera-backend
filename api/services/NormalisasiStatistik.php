<?php

class NormalisasiStatistik {

  public static function ringkasan(array $data) {
    return array_map(fn($i) => [
      "cabang" => $i["CABANG USAHA"],
      "nelayan" => (int)$i["Nelayan (Orang)"],
      "rtp" => (int)$i["RTP/PP (Orang/Unit)"],
      "armada" => (int)$i["Armada Perikanan (Buah)"],
      "alat_tangkap" => (int)$i["Alat Tangkap (Unit)"],
      "volume" => (int)$i["Volume (Ton)"],
      "nilai" => (int)$i["Nilai (Rp 1.000)"],
    ], $data);
  }

  public static function matrix(array $rows) {
    return array_map(fn($r) => [
      "wilayah" => $r["Wilayah"],
      "total" => (int)$r["JUMLAH - Total"],
      "laut" => (int)$r["Laut - Non Pelabuhan"],
      "perairan_umum" => (int)$r["Perairan Umum - Open Water"],
    ], $rows);
  }

  public static function bulanan(array $data) {
    return array_map(fn($i) => [
      "uraian" => $i["Uraian"],
      "bulan" => [
        (int)$i["Januari"], (int)$i["Februari"], (int)$i["Maret"],
        (int)$i["April"], (int)$i["Mei"], (int)$i["Juni"],
        (int)$i["Juli"], (int)$i["Agustus"], (int)$i["September"],
        (int)$i["Oktober"], (int)$i["November"], (int)$i["Desember"]
      ],
      "total" => (int)$i["Jumlah"]
    ], $data);
  }

  public static function komoditas(array $rows) {
    $out = [];
    foreach ($rows as $r) {
      if ($r["is_sub"] == 0) {
        $out[] = [
          "nama" => $r["komoditas"],
          "total" => (float)$r["volume"],
          "daerah" => []
        ];
      } else {
        $out[count($out) - 1]["daerah"][] = [
          "nama" => $r["komoditas"],
          "volume" => (float)$r["volume"]
        ];
      }
    }
    return $out;
  }
}
