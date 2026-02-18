<?php
// test_simple.php - Uji Koneksi Database & Tampilkan Data Sederhana

// 1. Mulai sesi
session_start();

// 2. Include file db.php untuk koneksi PDO
require_once __DIR__ . '/api/db.php';

// 3. Ambil koneksi PDO
$pdo = pdo(); // Fungsi ini sudah didefinisikan di db.php

// 4. Query sederhana: Ambil semua data dari tabel budidaya_ringkasan untuk tahun 2024
$tahun = 2024;
$stmt = $pdo->prepare("SELECT id, uraian, nilai, satuan FROM budidaya_ringkasan WHERE tahun = ? ORDER BY id ASC");
$stmt->execute([$tahun]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Tampilkan hasil dalam format HTML sederhana
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Test Sederhana SAMUDATA</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>✅ Test Sederhana: Data Budidaya Ringkasan (Tahun <?= htmlspecialchars($tahun) ?>)</h1>

    <?php if (empty($rows)): ?>
        <p><strong>❌ Tidak ada data ditemukan untuk tahun <?= htmlspecialchars($tahun) ?>.</strong></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Uraian</th>
                    <th>Nilai</th>
                    <th>Satuan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['uraian']) ?></td>
                        <td><?= htmlspecialchars($row['nilai']) ?></td>
                        <td><?= htmlspecialchars($row['satuan']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr>
    <p><strong>Database:</strong> <?= htmlspecialchars($pdo->query('SELECT DATABASE()')->fetchColumn()) ?></p>
    <p><strong>Server:</strong> <?= $_SERVER['SERVER_NAME'] ?? 'localhost' ?></p>
    <p><strong>Waktu Server:</strong> <?= date('Y-m-d H:i:s') ?></p>
</body>
</html>