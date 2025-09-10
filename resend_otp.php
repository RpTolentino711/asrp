<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/send_otp_mail.php';

if (!isset($_SESSION['otp_email']) || !isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please register again.']);
    exit;
}

$email = $_SESSION['otp_email'];

// --- Cooldown: 60 seconds ---
if (isset($_SESSION['last_otp_sent']) && (time() - $_SESSION['last_otp_sent']) < 60) {
    $wait = 60 - (time() - $_SESSION['last_otp_sent']);
    echo json_encode(['success' => false, 'message' => "Wait {$wait} seconds before requesting again."]);
    exit;
}
$_SESSION['last_otp_sent'] = time();

// --- Generate new OTP ---
$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$_SESSION['otp'] = $otp;
$_SESSION['otp_expires'] = time() + 300;
$_SESSION['otp_attempts'] = 0;
unset($_SESSION['otp_locked_until']);

$sent = send_otp_mail($email, $otp, 'ASRP Registration OTP (Resent)');

if ($sent) {
    echo json_encode([
        'success'    => true,
        'message'    => "A new OTP has been sent to {$email}.",
        'expires_at' => $_SESSION['otp_expires']
    ]);
} else {
    error_log("Failed to resend OTP to {$email}");
    echo json_encode(['success' => false, 'message' => 'Failed to resend OTP. Try again later.']);
}
