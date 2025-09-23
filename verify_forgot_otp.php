<?php
session_start();
header('Content-Type: application/json');

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // Check if session has pending forgot password request
    if (!isset($_SESSION['forgot_otp']) || !isset($_SESSION['forgot_otp_email'])) {
        throw new Exception('Session expired or invalid. Please request OTP again.');
    }

    if (empty($_POST['otp'])) {
        throw new Exception('OTP is required.');
    }

    $otp = trim($_POST['otp']);

    // Check if OTP is locked due to too many attempts
    if (isset($_SESSION['forgot_otp_locked_until']) && time() < $_SESSION['forgot_otp_locked_until']) {
        $wait = $_SESSION['forgot_otp_locked_until'] - time();
        throw new Exception("Too many failed attempts. Please wait {$wait} seconds before trying again.");
    }

    // Check if OTP has expired
    if (time() > $_SESSION['forgot_otp_expires']) {
        unset($_SESSION['forgot_otp']);
        unset($_SESSION['forgot_otp_expires']);
        unset($_SESSION['forgot_otp_email']);
        throw new Exception('OTP has expired. Please request a new one.');
    }

    // Validate OTP format
    if (!preg_match('/^\d{6}$/', $otp)) {
        throw new Exception('Please enter a valid 6-digit OTP.');
    }

    // Check OTP attempts
    if (!isset($_SESSION['forgot_otp_attempts'])) {
        $_SESSION['forgot_otp_attempts'] = 0;
    }

    // Verify OTP
    if ($_SESSION['forgot_otp'] !== $otp) {
        $_SESSION['forgot_otp_attempts']++;
        
        // Lock after 5 failed attempts for 10 minutes
        if ($_SESSION['forgot_otp_attempts'] >= 5) {
            $_SESSION['forgot_otp_locked_until'] = time() + (10 * 60); // 10 minutes
            throw new Exception('Too many failed attempts. Please wait 10 minutes before trying again.');
        }
        
        $remaining = 5 - $_SESSION['forgot_otp_attempts'];
        throw new Exception("Invalid OTP. You have {$remaining} attempts remaining.");
    }

    // OTP is correct, mark as verified
    $_SESSION['forgot_otp_verified'] = true;

    // Reset attempts counter
    unset($_SESSION['forgot_otp_attempts']);
    unset($_SESSION['forgot_otp_locked_until']);

    echo json_encode([
        'success' => true,
        'message' => 'OTP verified successfully. You can now reset your password.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
?>