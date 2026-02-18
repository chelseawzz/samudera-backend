<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        echo json_encode(['ok' => false, 'message' => 'Email tidak boleh kosong']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'message' => 'Format email tidak valid']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['ok' => true, 'message' => 'Jika email terdaftar, link reset akan dikirim']);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $pdo->prepare("
        INSERT INTO password_resets (user_id, token, expires_at) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE token = ?, expires_at = ?
    ");
    $stmt->execute([
        $user['id'], 
        $token, 
        $expires,
        $token,
        $expires
    ]);

    $resetLink = "http://localhost:5173/reset-password?token=$token&email=" . urlencode($email);

    $subject = "Reset Password SAMUDERA";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1A5276, #3498DB); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #1A5276; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Reset Password SAMUDERA</h1>
            </div>
            <div class='content'>
                <p>Halo <strong>{$user['username']}</strong>,</p>
                <p>Kami menerima permintaan untuk reset password akun SAMUDERA Anda.</p>
                <p>Klik tombol di bawah ini untuk membuat password baru:</p>
                <center><a href='$resetLink' class='button'>Reset Password Sekarang</a></center>
                <p>Atau salin link berikut ke browser Anda:</p>
                <p style='word-break: break-all; background: #fff; padding: 10px; border-radius: 5px;'>$resetLink</p>
                <p><strong>Catatan:</strong></p>
                <ul>
                    <li>Link ini hanya berlaku selama 1 jam</li>
                    <li>Jangan bagikan link ini kepada siapapun</li>
                    <li>Jika Anda tidak meminta reset password, abaikan email ini</li>
                </ul>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Dinas Kelautan dan Perikanan Provinsi Jawa Timur</p>
                <p>SAMUDERA - Sistem Manajemen Data Perikanan Terpadu</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'novitachelsea11@gmail.com';  
        $mail->Password = 'wltdgnuhazkwha';           
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('noreply@samudera-dkpjatim.go.id', 'SAMUDERA DKP Jatim');
        $mail->addAddress($email, $user['username']);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
        
        echo json_encode([
            'ok' => true, 
            'message' => 'Link reset password telah dikirim ke email Anda'
        ]);
    } catch (Exception $e) {
        error_log("Email gagal dikirim: " . $mail->ErrorInfo);
        echo json_encode([
            'ok' => true, 
            'message' => 'Jika email terdaftar, link reset akan dikirim'
        ]);
    }

} catch (Exception $e) {
    error_log("Error forgot password: " . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Terjadi kesalahan sistem']);
}
?>