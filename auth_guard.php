<?php
// auth_guard.php
// Taruh file ini di root (/) atau folder mana pun—yang penting require_once path-nya benar.

if (session_status() !== PHP_SESSION_ACTIVE) {
  // cookie session aman (opsional)
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

/** Paksa login sebelum akses halaman */
function require_login(): void {
  if (empty($_SESSION['isLoggedIn'])) {
    $next = $_SERVER['REQUEST_URI'] ?? '/dashboard.php';
    header('Location: /login.php?next=' . rawurlencode($next));
    exit;
  }
}

/** Cek sesi admin (mendukung elevasi sementara 30 menit) */
function is_admin_session(): bool {
  if (!empty($_SESSION['userType']) && $_SESSION['userType'] === 'admin') return true;

  if (!empty($_SESSION['admin_elevated'])) {
    $t = (int)($_SESSION['admin_elevated_at'] ?? 0);
    if (time() - $t < 30 * 60) return true;     // masih valid
    // kalau kedaluwarsa, bersihkan flag
    unset($_SESSION['admin_elevated'], $_SESSION['admin_elevated_at']);
  }
  return false;
}

/**
 * Batasi akses khusus admin.
 * @param string $mode 'page' = tampilkan halaman 403 yang rapi, 'redirect' = lempar ke dashboard dengan pesan.
 */
function require_admin(string $mode = 'page'): void {
  if (is_admin_session()) return;

  if ($mode === 'redirect') {
    header('Location: /dashboard.php?err=not_admin');
    exit;
  }

  // Halaman 403 yang rapi (Tailwind CDN ringan)
  http_response_code(403);
  echo '<!doctype html><html lang="id"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Akses Dibatasi</title>
    <link rel="icon" href="/images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  </head><body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
    <div class="max-w-lg w-full bg-white rounded-2xl shadow-xl p-8">
      <div class="flex items-center gap-3 mb-4">
        <img src="/images/logo.png" class="h-8" alt="SAMUDERA">
        <h1 class="text-xl font-semibold">Akses Dibatasi</h1>
      </div>
      <p class="text-gray-600">Halaman ini khusus <b>Administrator</b>. Akun kamu saat ini tidak memiliki izin tersebut.</p>
      <div class="mt-6 flex gap-2">
        <a href="/dashboard.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">Kembali ke Dashboard</a>
        <a href="/login.php" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg">Ganti Akun</a>
      </div>
      <p class="text-xs text-gray-400 mt-6">Kode: 403 • Tidak memiliki peran admin</p>
    </div>
  </body></html>';
  exit;
}
