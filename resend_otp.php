<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['otp_email']) || !isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid. Please register again.']);
    exit;
}
$email = $_SESSION['otp_email'];
$otp = random_int(100000, 999999);
$_SESSION['otp'] = (string)$otp;
$_SESSION['otp_expires'] = time() + 5 * 60; // 5 minutes
$_SESSION['otp_attempts'] = 0;
unset($_SESSION['otp_locked_until']);
// Send OTP email again
require_once __DIR__ . '/send_otp_mail.php';
$sent = send_otp_mail($email, $otp, 'ASRP Registration OTP (Resent)');
if ($sent) {
    echo json_encode([
        'success' => true,
        'message' => 'A new OTP has been sent to your email.',
        'expires_at' => $_SESSION['otp_expires']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to resend OTP. Please try again later.'
    ]);
}
exit;
