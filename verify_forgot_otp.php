<?php
// verify_forgot_otp.php
session_start();
header('Content-Type: application/json');

if (empty($_POST['otp'])) {
    echo json_encode(['success' => false, 'message' => 'OTP is required.']);
    exit;
}

$otp = trim($_POST['otp']);
if (!isset($_SESSION['forgot_otp'])) {
    echo json_encode(['success' => false, 'message' => 'No OTP session found. Please request a new code.']);
    exit;
}

$otp_data = $_SESSION['forgot_otp'];
if (time() > $otp_data['expires_at']) {
    unset($_SESSION['forgot_otp']);
    echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new code.']);
    exit;
}

if ($otp !== $otp_data['otp']) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP.']);
    exit;
}

// OTP is valid
$_SESSION['forgot_otp_verified'] = true;
echo json_encode(['success' => true, 'message' => 'OTP verified. You may now reset your password.']);
