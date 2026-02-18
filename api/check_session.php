<?php
// PERPANJANG SESSION LIFETIME
ini_set('session.gc_maxlifetime', 86400 * 30); // 30 hari
session_set_cookie_params([
    'lifetime' => 86400 * 30, // 30 hari
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');

$response = [
    'ok' => false,
    'user' => null,
    'message' => 'Session tidak valid'
];

// Cek apakah session ada dan valid
if (isset($_SESSION['isLoggedIn']) && 
    $_SESSION['isLoggedIn'] === true && 
    isset($_SESSION['user_id']) && 
    isset($_SESSION['username']) && 
    isset($_SESSION['email']) && 
    isset($_SESSION['userType'])) {
    
    $response['ok'] = true;
    $response['user'] = [
        'role' => $_SESSION['userType'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email']
    ];
    $response['message'] = 'Session valid';
} else {
    // Clear session jika tidak valid
    session_unset();
    session_destroy();
}

echo json_encode($response);
?>