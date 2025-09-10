<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// --- Check session data ---
if (empty($_SESSION['otp_email']) || empty($_SESSION['pending_registration'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired or invalid. Please register again.'
    ]);
    exit;
}

$email = $_SESSION['otp_email'];

// --- Prevent OTP spamming (60s cooldown) ---
if (isset($_SESSION['last_otp_sent']) && (time() - $_SESSION['last_otp_sent']) < 60) {
    $wait = 60 - (time() - $_SESSION['last_otp_sent']);
    echo json_encode([
        'success' => false,
        'message' => "Please wait {$wait} seconds before requesting a new OTP."
    ]);
    exit;
}
$_SESSION['last_otp_sent'] = time();

// --- Generate fresh OTP ---
$otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$_SESSION['otp'] = $otp;
$_SESSION['otp_expires'] = time() + 300; // 5 minutes
$_SESSION['otp_attempts'] = 0;
unset($_SESSION['otp_locked_until']);

// --- Send OTP email ---
require_once __DIR__ . '/send_otp_mail.php';
$sent = send_otp_mail($email, $otp, 'ASRP Registration OTP (Resent)');

if ($sent) {
    echo json_encode([
        'success'    => true,
        'message'    => "A new OTP has been sent to {$email}.",
        'expires_at' => $_SESSION['otp_expires']
    ]);
} else {
    error_log("Failed to resend OTP to {$email}");
    echo json_encode([
        'success' => false,
        'message' => 'Failed to resend OTP. Please try again later.'
    ]);
}
exit;
