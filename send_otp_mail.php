<?php
require_once __DIR__ . '/class.phpmailer.php';

function send_otp_mail($to, $otp, $subject = 'ASRP Registration OTP') {
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'ahmadpaguta2005@gmail.com';
    $mail->Password = 'unwr kdad ejcd rysq'; // App password, NOT your Gmail password!
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom($mail->Username, 'ASRP Registration');
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = "Your OTP code for ASRP registration is: <b>" . htmlspecialchars($otp) . "</b><br>This code will expire in 10 minutes.";
    $mail->AltBody = "Your OTP code for ASRP registration is: $otp. This code will expire in 10 minutes.";

    // Set debug to error_log for troubleshooting (remove or set to 0 in production)
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        error_log("PHPMailer [$level]: $str");
    };

    if (!$mail->send()) {
        error_log('OTP email failed: '.$mail->ErrorInfo);
        return false;
    }
    return true;
}