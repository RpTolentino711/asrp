<?php
// reset_password.php: Handles password reset after OTP verification
session_start();
header('Content-Type: application/json');

require_once 'database/database.php';
$db = new Database();

// Only allow if OTP was verified for forgot password
if (!isset($_SESSION['forgot_otp_verified_email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or session expired.']);
    exit;
}

$email = $_SESSION['forgot_otp_verified_email'];
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if (!$password) {
    echo json_encode(['success' => false, 'message' => 'Password is required.']);
    exit;
}

// Password strength: at least 8 chars, 1 uppercase, 1 special char
if (!preg_match('/^(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters, include an uppercase letter and a special character.']);
    exit;
}

// Hash password
$hash = password_hash($password, PASSWORD_DEFAULT);

// Update password in DB
try {
    $stmt = $db->getConnection()->prepare('UPDATE client SET C_password = :password WHERE C_email = :email');
    $stmt->execute([':password' => $hash, ':email' => $email]);
    // Clean up session
    unset($_SESSION['forgot_otp_verified_email']);
    unset($_SESSION['forgot_otp']);
    unset($_SESSION['forgot_otp_expires']);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
