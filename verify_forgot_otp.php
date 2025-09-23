<?php
session_start();
header('Content-Type: application/json');

// Disable error display to prevent breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // Validate OTP input
    if (empty($_POST['otp'])) {
        throw new Exception('OTP is required.');
    }

    $inputOtp = trim($_POST['otp']);

    // Validate OTP format (6 digits)
    if (!preg_match('/^\d{6}$/', $inputOtp)) {
        throw new Exception('OTP must be exactly 6 digits.');
    }

    // Check if forgot password session exists
    if (!isset($_SESSION['forgot_otp']) || 
        !isset($_SESSION['forgot_otp_email']) || 
        !isset($_SESSION['forgot_otp_expires'])) {
        throw new Exception('No active password reset session. Please request a new OTP.');
    }

    // Check if OTP is locked due to too many attempts
    if (isset($_SESSION['forgot_otp_locked_until']) && 
        time() < $_SESSION['forgot_otp_locked_until']) {
        $remainingTime = $_SESSION['forgot_otp_locked_until'] - time();
        throw new Exception("Too many failed attempts. Please try again in " . ceil($remainingTime / 60) . " minutes.");
    }

    // Check if OTP has expired
    if (time() > $_SESSION['forgot_otp_expires']) {
        // Clear expired session data
        unset($_SESSION['forgot_otp']);
        unset($_SESSION['forgot_otp_email']);
        unset($_SESSION['forgot_otp_expires']);
        unset($_SESSION['forgot_otp_attempts']);
        throw new Exception('OTP has expired. Please request a new one.');
    }

    // Initialize attempt counter
    if (!isset($_SESSION['forgot_otp_attempts'])) {
        $_SESSION['forgot_otp_attempts'] = 0;
    }

    // Check if OTP matches
    if ($inputOtp !== $_SESSION['forgot_otp']) {
        $_SESSION['forgot_otp_attempts']++;
        
        // Lock after 3 failed attempts
        if ($_SESSION['forgot_otp_attempts'] >= 3) {
            $_SESSION['forgot_otp_locked_until'] = time() + (15 * 60); // 15 minutes lockout
            
            // Clear OTP data but keep email for potential retry
            unset($_SESSION['forgot_otp']);
            unset($_SESSION['forgot_otp_expires']);
            
            throw new Exception('Too many failed attempts. Account locked for 15 minutes.');
        }
        
        $remainingAttempts = 3 - $_SESSION['forgot_otp_attempts'];
        throw new Exception("Invalid OTP. {$remainingAttempts} attempts remaining.");
    }

    // OTP is valid - mark as verified and prepare for password reset
    $_SESSION['forgot_otp_verified'] = true;
    $_SESSION['forgot_password_reset_allowed'] = true;
    
    // Clear OTP data (no longer needed)
    unset($_SESSION['forgot_otp']);
    unset($_SESSION['forgot_otp_expires']);
    unset($_SESSION['forgot_otp_attempts']);
    unset($_SESSION['forgot_otp_locked_until']);

    // Log successful verification
    error_log("Password reset OTP verified for email: " . $_SESSION['forgot_otp_email']);

    echo json_encode([
        'success' => true,
        'message' => 'OTP verified successfully. You can now reset your password.'
    ]);

} catch (Exception $e) {
    error_log("Error in verify_forgot_otp.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>