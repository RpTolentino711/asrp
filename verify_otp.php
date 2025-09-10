<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/database/database.php';

if (!isset($_SESSION['otp_email']) || !isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please register again.']);
    exit;
}

if (!isset($_POST['otp'])) {
    echo json_encode(['success' => false, 'message' => 'No OTP provided.']);
    exit;
}

$inputOtp = trim($_POST['otp']);

// --- Format check ---
if (!preg_match('/^\d{6}$/', $inputOtp)) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP format.']);
    exit;
}

// --- Check lockout ---
if (isset($_SESSION['otp_locked_until']) && time() < $_SESSION['otp_locked_until']) {
    $wait = $_SESSION['otp_locked_until'] - time();
    echo json_encode(['success' => false, 'message' => "Too many attempts. Try again in {$wait} seconds."]);
    exit;
}

// --- Validate existence & expiry ---
if (!isset($_SESSION['otp'], $_SESSION['otp_expires'])) {
    echo json_encode(['success' => false, 'message' => 'No OTP found. Please request again.']);
    exit;
}
if (time() > $_SESSION['otp_expires']) {
    echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
    exit;
}

// --- Compare ---
if (hash_equals($_SESSION['otp'], $inputOtp)) {
    $db = new Database();
    $userData = $_SESSION['pending_registration'];

    $success = false;
    $errorMsg = '';
    try {
        $success = $db->registerClient(
            $userData['fname'],
            $userData['lname'],
            $userData['email'],
            $userData['phone'],
            $userData['username'],
            $userData['password']
        );
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    unset($_SESSION['otp'], $_SESSION['otp_expires'], $_SESSION['otp_attempts'], $_SESSION['otp_locked_until'], $_SESSION['pending_registration'], $_SESSION['otp_email']);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'OTP verified. Registration complete.']);
    } else {
        error_log("DB insert failed: {$errorMsg}");
        echo json_encode(['success' => false, 'message' => 'Registration failed. Try again.', 'error' => $errorMsg]);
    }

} else {
    $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
    if ($_SESSION['otp_attempts'] >= 5) {
        $_SESSION['otp_locked_until'] = time() + 300;
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Locked for 5 minutes.']);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Incorrect OTP. Try again.']);
}
