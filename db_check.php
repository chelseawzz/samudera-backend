<?php
require __DIR__.'/api/db.php';
try {
  $db = pdo();
  echo "<h3 style='color:green'>Koneksi OK ✅</h3>";
  echo "<p>Database: <b>".DB_NAME."</b></p>";
} catch (Throwable $e) {
  echo "<h3 style='color:red'>Koneksi gagal ❌</h3>";
  echo htmlspecialchars($e->getMessage());
}
