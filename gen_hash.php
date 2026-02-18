<?php
// Hapus file ini setelah dipakai!
$pass = 'Admin@2025';                      // ganti kalau mau
$hash = password_hash($pass, PASSWORD_DEFAULT);
echo "<pre>Password: $pass\nHash: $hash</pre>";
