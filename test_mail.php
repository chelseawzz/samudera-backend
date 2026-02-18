<?php
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'novitachelsea11@gmail.com';
    $mail->Password = 'jhotfvulhcwxhdcw';  // ⚠️ GANTI DENGAN APP PASSWORD ANDA (16 digit TANPA spasi!)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('novitachelsea11@gmail.com', 'SAMUDERA Test');
    $mail->addAddress('novitachelsea11@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'Test Email PHPMailer';
    $mail->Body = 'Ini adalah test email dari PHPMailer!';

    $mail->send();
    echo '✅ Email berhasil dikirim!' . PHP_EOL;
} catch (Exception $e) {
    echo "❌ Email gagal dikirim. Error: {$mail->ErrorInfo}" . PHP_EOL;
}
?>