<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database/database.php';

// ==== CONFIGURATION ====
$MAX_ATTEMPTS = 5;       // Max allowed OTP attempts before lockout
$LOCKOUT_MIN = 15;       // Lockout time in minutes

// ==== SESSION CHECKS ====
if (!isset($_SESSION['pending_registration']) || !isset($_SESSION['otp'])) {
    echo json_encode(['success' => false, 'message' => 'No pending registration found.']);
    exit;
}

// ==== READ & VALIDATE REQUEST ====
$otp = isset($_POST['otp']) ? preg_replace('/\D+/', '', $_POST['otp']) : '';
$now = time();

// ==== LOCKOUT CHECK ====
if (!empty($_SESSION['otp_locked_until']) && $now < $_SESSION['otp_locked_until']) {
    $remaining = $_SESSION['otp_locked_until'] - $now;
    echo json_encode([
        'success' => false,
        'message' => 'Too many attempts. Try again in ' . ceil($remaining / 60) . ' minute(s).'
    ]);
    exit;
}

// ==== OTP FORMAT CHECK ====
if ($otp === '' || strlen($otp) !== 6) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid 6-digit code.']);
    exit;
}

// ==== EXPIRY CHECK ====
if (empty($_SESSION['otp_expires'])) {
    echo json_encode(['success' => false, 'message' => 'No active code. Please request a new one.']);
    exit;
} elseif ($now > (int)$_SESSION['otp_expires']) {
    echo json_encode(['success' => false, 'message' => 'The code has expired. Please request a new one.']);
    exit;
}

// ==== ATTEMPT COUNTER ====
$_SESSION['otp_attempts'] = $_SESSION['otp_attempts'] ?? 0;

// ==== OTP VERIFICATION ====
if (hash_equals((string)$_SESSION['otp'], $otp)) {
    // ---- OTP Correct: Register the user ----
    try {
        $db = new Database();
        $pdo = $db->pdo ?? (method_exists($db, 'opencon') ? $db->opencon() : null);
        $stmt = $pdo->prepare("INSERT INTO client (Client_Fname, Client_Lname, Client_Email, Client_Contact, C_username, C_password, Created_At) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $inserted = $stmt->execute([
            $_SESSION['pending_registration']['fname'],
            $_SESSION['pending_registration']['lname'],
            $_SESSION['pending_registration']['email'],
            $_SESSION['pending_registration']['phone'],
            $_SESSION['pending_registration']['username'],
            $_SESSION['pending_registration']['password'],
            $_SESSION['pending_registration']['created_at'],
        ]);
        if ($inserted) {
            unset(
                $_SESSION['otp'],
                $_SESSION['otp_expires'],
                $_SESSION['otp_attempts'],
                $_SESSION['otp_locked_until'],
                $_SESSION['pending_registration']
            );
            echo json_encode(['success' => true, 'message' => 'Registration successful! You can now log in.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create account. Please try again.']);
        }
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred during registration.']);
    }
} else {
    // ---- OTP Incorrect: Track attempts and possibly lock out ----
    $_SESSION['otp_attempts']++;
    if ($_SESSION['otp_attempts'] >= $MAX_ATTEMPTS) {
        $_SESSION['otp_locked_until'] = $now + ($LOCKOUT_MIN * 60);
        echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts. Try again later.']);
    } else {
        $remaining = $MAX_ATTEMPTS - $_SESSION['otp_attempts'];
        echo json_encode(['success' => false, 'message' => 'Incorrect code. Attempts left: ' . $remaining . '.']);
    }
}