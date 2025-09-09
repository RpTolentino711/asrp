<?php
// send_otp_mail.php

require_once __DIR__ . '/class.phpmailer.php';

function send_otp_mail($to, $otp, $subject = 'ASRT Registration OTP') {
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = 'smtp.example.com'; // TODO: Change to your SMTP server
    $mail->SMTPAuth = true;
    $mail->Username = 'romeopaolotolentino@gmail.com'; // TODO: Change to your SMTP username
    $mail->Password = 'Pogilameg@10'; // TODO: Change to your SMTP password
    $mail->SMTPSecure = 'tls'; // Or 'ssl' if required
    $mail->Port = 587; // Or 465 for SSL

    $mail->setFrom('no-reply@asrt.com', 'ASRT Commercial Spaces');
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = "Your OTP code for ASRT registration is: <b>" . htmlspecialchars($otp) . "</b><br>This code will expire in 10 minutes.";

    // Optional: Add a plain text version for non-HTML clients
    $mail->AltBody = "Your OTP code for ASRT registration is: $otp\nThis code will expire in 10 minutes.";

    return $mail->send();
}