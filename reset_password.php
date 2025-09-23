<?php
session_start();
header('Content-Type: application/json');

require_once 'database/database.php';

function validateResetSession() {
    if (!isset($_SESSION['forgot_otp_verified']) || $_SESSION['forgot_otp_verified'] !== true) {
        return ['success' => false, 'message' => 'Unauthorized. Please verify your email first.'];
    }
    
    if (!isset($_SESSION['forgot_email'])) {
        return ['success' => false, 'message' => 'Session expired. Please start the password reset process again.'];
    }
    
    if (!isset($_SESSION['reset_token'])) {
        return ['success' => false, 'message' => 'Invalid session. Please verify your email again.'];
    }
    
    return ['success' => true];
}

function validatePasswordInput($password, $confirmPassword) {
    if (empty($password) || empty($confirmPassword)) {
        return ['success' => false, 'message' => 'Please fill in both password fields.'];
    }
    
    if ($password !== $confirmPassword) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
    }
    
    // Enhanced password validation
    if (!preg_match('/[A-Z]/', $password)) {
        return ['success' => false, 'message' => 'Password must contain at least one uppercase letter.'];
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        return ['success' => false, 'message' => 'Password must contain at least one special character.'];
    }
    
    return ['success' => true];
}

function updateClientPassword($email, $newPassword) {
    try {
        $db = new Database();
        
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password using your PDO method
        if ($db->updatePasswordByEmail($email, $hashedPassword)) {
            return ['success' => true, 'message' => 'Password updated successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update password. Please try again.'];
        }
        
    } catch (Exception $e) {
        error_log("Password update error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error. Please try again later.'];
    }
}

function clearForgotPasswordSession() {
    // Clear all forgot password related session data
    unset($_SESSION['forgot_otp_verified']);
    unset($_SESSION['forgot_email']);
    unset($_SESSION['reset_token']);
    unset($_SESSION['last_forgot_otp_sent']);
}

try {
    // Validate session
    $sessionValidation = validateResetSession();
    if (!$sessionValidation['success']) {
        echo json_encode($sessionValidation);
        exit;
    }
    
    // Get password inputs
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate password
    $passwordValidation = validatePasswordInput($password, $confirmPassword);
    if (!$passwordValidation['success']) {
        echo json_encode($passwordValidation);
        exit;
    }
    
    // Get email from session
    $email = $_SESSION['forgot_email'];
    
    // Update password in database
    $updateResult = updateClientPassword($email, $password);
    
    if ($updateResult['success']) {
        // Log successful password reset
        error_log("Password successfully reset for email: {$email}");
        
        // Clear session data
        clearForgotPasswordSession();
        
        echo json_encode([
            'success' => true,
            'message' => 'Your password has been successfully reset. You can now log in with your new password.'
        ]);
    } else {
        // Log failed password reset
        error_log("Failed password reset for email: {$email}");
        
        echo json_encode($updateResult);
    }
    
} catch (Exception $e) {
    error_log("Password Reset Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}

exit;