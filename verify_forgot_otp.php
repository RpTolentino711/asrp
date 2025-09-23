<?php
session_start();
header('Content-Type: application/json');

// Configuration
define('MAX_FORGOT_OTP_ATTEMPTS', 3);
define('LOCKOUT_DURATION_MINUTES', 15);

function validateForgotOTP($otp) {
    if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
        return ['success' => false, 'message' => 'Please enter a valid 6-digit code.'];
    }
    return ['success' => true];
}

function checkForgotOTPSession() {
    if (!isset($_SESSION['forgot_otp']) || !isset($_SESSION['forgot_email'])) {
        return ['success' => false, 'message' => 'Session expired. Please request a new password reset code.'];
    }
    return ['success' => true];
}

function checkForgotOTPExpiry() {
    if (!isset($_SESSION['forgot_otp_expires']) || time() > $_SESSION['forgot_otp_expires']) {
        return ['success' => false, 'message' => 'Code has expired. Please request a new password reset code.'];
    }
    return ['success' => true];
}

function checkForgotOTPLockout() {
    if (isset($_SESSION['forgot_otp_locked_until']) && time() < $_SESSION['forgot_otp_locked_until']) {
        $remainingMinutes = ceil(($_SESSION['forgot_otp_locked_until'] - time()) / 60);
        return [
            'success' => false, 
            'message' => "Too many failed attempts. Please try again in {$remainingMinutes} minutes."
        ];
    }
    return ['success' => true];
}

function incrementForgotOTPAttempts() {
    $_SESSION['forgot_otp_attempts'] = ($_SESSION['forgot_otp_attempts'] ?? 0) + 1;
    
    if ($_SESSION['forgot_otp_attempts'] >= MAX_FORGOT_OTP_ATTEMPTS) {
        $_SESSION['forgot_otp_locked_until'] = time() + (LOCKOUT_DURATION_MINUTES * 60);
        
        // Clear OTP data to prevent further attempts
        unset($_SESSION['forgot_otp']);
        unset($_SESSION['forgot_otp_expires']);
        
        return [
            'success' => false, 
            'message' => 'Too many failed attempts. Account temporarily locked. Please try again in ' . LOCKOUT_DURATION_MINUTES . ' minutes.'
        ];
    }
    
    $attemptsLeft = MAX_FORGOT_OTP_ATTEMPTS - $_SESSION['forgot_otp_attempts'];
    return [
        'success' => false, 
        'message' => "Invalid code. {$attemptsLeft} attempts remaining."
    ];
}

try {
    // Get OTP from request
    $inputOTP = trim($_POST['otp'] ?? '');
    
    // Validate input format
    $otpValidation = validateForgotOTP($inputOTP);
    if (!$otpValidation['success']) {
        echo json_encode($otpValidation);
        exit;
    }
    
    // Check session
    $sessionCheck = checkForgotOTPSession();
    if (!$sessionCheck['success']) {
        echo json_encode($sessionCheck);
        exit;
    }
    
    // Check if locked out
    $lockoutCheck = checkForgotOTPLockout();
    if (!$lockoutCheck['success']) {
        echo json_encode($lockoutCheck);
        exit;
    }
    
    // Check expiry
    $expiryCheck = checkForgotOTPExpiry();
    if (!$expiryCheck['success']) {
        echo json_encode($expiryCheck);
        exit;
    }
    
    // Verify OTP
    if ($inputOTP === $_SESSION['forgot_otp']) {
        // OTP is correct
        $_SESSION['forgot_otp_verified'] = true;
        $_SESSION['reset_token'] = bin2hex(random_bytes(32)); // Generate secure reset token
        
        // Clear OTP data but keep verification status
        unset($_SESSION['forgot_otp']);
        unset($_SESSION['forgot_otp_expires']);
        unset($_SESSION['forgot_otp_attempts']);
        unset($_SESSION['forgot_otp_locked_until']);
        
        error_log("Forgot password OTP verified for: " . $_SESSION['forgot_email']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Code verified successfully. You can now reset your password.'
        ]);
    } else {
        // OTP is incorrect
        error_log("Invalid forgot password OTP attempt for: " . ($_SESSION['forgot_email'] ?? 'unknown'));
        
        $attemptResult = incrementForgotOTPAttempts();
        echo json_encode($attemptResult);
    }
    
} catch (Exception $e) {
    error_log("Forgot OTP Verification Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}

exit;