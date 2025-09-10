<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database/database.php';

$db = new Database();
$pdo = $db->pdo ?? null;
if (!$pdo && method_exists($db, 'opencon')) {
    $pdo = $db->opencon();
}

// 1. Ensure we have a pending registration and OTP
if (!isset($_SESSION['pending_registration'], $_SESSION['otp'], $_SESSION['otp_expires'])) {
    echo json_encode(['success' => false, 'message' => 'No pending registration. Please register again.']);
    exit;
}

// 2. Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

    // Lockout system
    if (isset($_SESSION['otp_locked_until']) && time() < $_SESSION['otp_locked_until']) {
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please wait before retrying.']);
        exit;
    }

    // Expiry check
    if (time() > $_SESSION['otp_expires']) {
        unset($_SESSION['otp'], $_SESSION['otp_expires'], $_SESSION['pending_registration']);
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please register again.']);
        exit;
    }

    // Validate OTP
    if ($input_otp !== $_SESSION['otp']) {
        $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;

        // Lock after 5 failed attempts for 2 minutes
        if ($_SESSION['otp_attempts'] >= 5) {
            $_SESSION['otp_locked_until'] = time() + 120;
            echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Locked for 2 minutes.']);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        exit;
    }

    // ✅ OTP valid
    $user = $_SESSION['pending_registration'];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO client (C_FName, C_LName, Client_Email, C_phone, C_username, C_password, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['fname'],
            $user['lname'],
            $user['email'],
            $user['phone'],
            $user['username'],
            $user['password'],
            $user['created_at']
        ]);

        // Clear OTP and session data
        unset($_SESSION['otp'], $_SESSION['otp_expires'], $_SESSION['otp_attempts'], $_SESSION['otp_locked_until'], $_SESSION['pending_registration'], $_SESSION['otp_email']);

        echo json_encode(['success' => true, 'message' => 'Registration successful! You can now log in.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to complete registration.', 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
