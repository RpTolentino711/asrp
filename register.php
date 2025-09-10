<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/database/database.php';
require_once __DIR__ . '/send_otp_mail.php'; // uses PHPMailer with config.php

$db = new Database();
$pdo = $db->pdo ?? null;
if (!$pdo && method_exists($db, 'opencon')) {
    $pdo = $db->opencon();
}

// --- Handle registration request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- Basic validations ---
    if (!$fname || !$lname || !$email || !$phone || !$username || !$password || !$confirm_password) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }
    if (!preg_match('/^\d{11}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number must be exactly 11 digits.']);
        exit;
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[\W_]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter and one special character.']);
        exit;
    }

    // --- Check for duplicates ---
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT Client_ID FROM client WHERE C_username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT Client_ID FROM client WHERE Client_Email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email address already registered.']);
            exit;
        }
    }

    // --- Store pending registration in session ---
    $_SESSION['pending_registration'] = [
        'fname' => $fname,
        'lname' => $lname,
        'email' => $email,
        'phone' => $phone,
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s')
    ];
    $_SESSION['otp_email'] = $email;

    // --- Generate OTP ---
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_expires'] = time() + (5 * 60);
    $_SESSION['otp_attempts'] = 0;
    unset($_SESSION['otp_locked_until']);

    // --- Send OTP email ---
    $sent = send_otp_mail($email, $otp, 'ASRP Registration OTP');
    if ($sent) {
        echo json_encode([
            'success' => true,
            'message' => 'Registration started. OTP sent to your email.',
            'pending_verification' => true,
            'email' => $email,
            'expires_at' => $_SESSION['otp_expires']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send OTP email. Please try again later.'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}