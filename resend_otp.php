<?php
session_start();
if (!isset($_SESSION['otp_email']) || !isset($_SESSION['otp_user_data'])) {
    header('Location: index.php');
    exit();
}
$email = $_SESSION['otp_email'];
$otp = rand(100000, 999999);
$_SESSION['otp'] = $otp;
// Send OTP email again
require_once __DIR__ . '/send_otp_mail.php';
send_otp_mail($email, $otp, 'ASRT Registration OTP (Resent)');
$_SESSION['register_success'] = 'A new OTP has been sent to your email.';
header('Location: verify_otp.php');
exit();
