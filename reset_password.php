<?php
// reset_password.php: Handles password reset after OTP verification
session_start();
header('Content-Type: application/json');

require_once 'database/database.php';
$db = new Database();

$email = isset($_SESSION['forgot_otp_email']) ? $_SESSION['forgot_otp_email'] : null;
if (!isset($_SESSION['forgot_otp_verified'], $email) || !$_SESSION['forgot_otp_verified']) {
    echo json_encode(['success' => false, 'message' => 'OTP verification required.']);
    exit;
}
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if (!$password) {
    echo json_encode(['success' => false, 'message' => 'Password is required.']);
    exit;
}

// Password strength: at least 6 chars
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $db->getConnection()->prepare('UPDATE client SET Password = :password WHERE Email = :email');
    $stmt->execute([':password' => $hash, ':email' => $email]);
    unset($_SESSION['forgot_otp']);
    unset($_SESSION['forgot_otp_expires']);
    unset($_SESSION['forgot_otp_email']);
    unset($_SESSION['forgot_otp_verified']);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
