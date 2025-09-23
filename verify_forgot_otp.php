<?php
session_start();
header('Content-Type: application/json');

$otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
if (!$otp || !isset($_SESSION['forgot_otp'], $_SESSION['forgot_otp_expires'], $_SESSION['forgot_otp_email'])) {
    echo json_encode(['success' => false, 'message' => 'OTP session expired. Please request a new code.']);
    exit;
}
if (time() > $_SESSION['forgot_otp_expires']) {
    unset($_SESSION['forgot_otp'], $_SESSION['forgot_otp_expires']);
    echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new code.']);
    exit;
}
if ($otp != $_SESSION['forgot_otp']) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP.']);
    exit;
}
// Mark OTP as verified
$_SESSION['forgot_otp_verified'] = true;
echo json_encode(['success' => true]);
