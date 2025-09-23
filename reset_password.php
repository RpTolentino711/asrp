<?php
// reset_password.php: Handles password reset after OTP verification
session_start();
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

require_once 'database/database.php';

try {
    $db = new Database();
    
    // Verify OTP session exists and is verified
    $email = isset($_SESSION['forgot_otp_email']) ? $_SESSION['forgot_otp_email'] : null;
    if (!isset($_SESSION['forgot_otp_verified']) || !$_SESSION['forgot_otp_verified'] || !$email) {
        echo json_encode(['success' => false, 'message' => 'OTP verification required. Please verify your OTP first.']);
        exit;
    }
    
    // Get and validate password inputs
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Password is required.']);
        exit;
    }
    
    if (empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'Password confirmation is required.']);
        exit;
    }
    
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }
    
    // Password strength validation
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
        exit;
    }
    
    // Hash the new password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Update password in database using correct column names
    $stmt = $db->getConnection()->prepare('UPDATE client SET C_password = :password WHERE Client_Email = :email');
    $result = $stmt->execute([':password' => $hashedPassword, ':email' => $email]);
    
    if ($result && $stmt->rowCount() > 0) {
        // Clear all forgot password session data
        unset($_SESSION['forgot_otp']);
        unset($_SESSION['forgot_otp_expires']);
        unset($_SESSION['forgot_otp_email']);
        unset($_SESSION['forgot_otp_verified']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Password has been reset successfully. You can now log in with your new password.'
        ]);
    } else {
        // Check if user exists
        $checkStmt = $db->getConnection()->prepare('SELECT Client_Email FROM client WHERE Client_Email = :email');
        $checkStmt->execute([':email' => $email]);
        
        if ($checkStmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'User account not found.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
        }
    }
    
} catch (PDOException $e) {
    // Log the actual error for debugging
    error_log("Database error in reset_password.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
} catch (Exception $e) {
    // Log any other errors
    error_log("Error in reset_password.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
}
?>