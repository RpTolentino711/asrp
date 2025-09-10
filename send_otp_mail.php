<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php'; // if using Composer
require_once __DIR__ . '/config.php'; // your SMTP_USER / SMTP_PASS

function send_otp_mail($to, $otp, $subject = 'ASRP Registration OTP') {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender & Recipient
        $mail->setFrom($mail->Username, 'ASRP Registration');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = "Your OTP code for ASRP registration is: <b>" . htmlspecialchars($otp) . "</b><br>This code will expire in 10 minutes.";
        $mail->AltBody = "Your OTP code for ASRP registration is: $otp. This code will expire in 10 minutes.";

        // Optional headers
        $mail->addCustomHeader('X-Mailer', 'PHPMailer ASRP System');

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('OTP email failed: ' . $mail->ErrorInfo);
        return false;
    }
}
