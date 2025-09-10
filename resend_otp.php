<?php
session_start();
header('Content-Type: application/json');

// Check if session contains required data (pending registration and email)
if (!isset($_SESSION['otp_email']) || !isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid. Please register again.']);
    exit;
}

// Prevent spamming: allow resend only once per 60 seconds
if (isset($_SESSION['last_otp_sent']) && (time() - $_SESSION['last_otp_sent']) < 60) {
    echo json_encode(['success' => false, 'message' => 'Please wait before requesting a new OTP.']);
    exit;
}
$_SESSION['last_otp_sent'] = time();

$email = $_SESSION['otp_email'];
$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); // Always 6 digits
$_SESSION['otp'] = $otp;
$_SESSION['otp_expires'] = time() + 5 * 60; // 5 minutes from now
$_SESSION['otp_attempts'] = 0;
unset($_SESSION['otp_locked_until']);

// Send OTP email
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
