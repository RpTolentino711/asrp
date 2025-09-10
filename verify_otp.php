<?php
session_start();
header('Content-Type: application/json');

// --- Check session data ---
if (!isset($_SESSION['otp_email']) || !isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid. Please register again.']);
    exit;
}

if (!isset($_POST['otp'])) {
    echo json_encode(['success' => false, 'message' => 'No OTP provided.']);
    exit;
}

$inputOtp = trim($_POST['otp']);

// --- Validate OTP format ---
if (!preg_match('/^\d{6}$/', $inputOtp)) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP format.']);
    exit;
}

// --- Check if account is locked due to too many attempts ---
if (isset($_SESSION['otp_locked_until']) && time() < $_SESSION['otp_locked_until']) {
    $wait = $_SESSION['otp_locked_until'] - time();
    echo json_encode(['success' => false, 'message' => "Too many attempts. Try again in {$wait} seconds."]);
    exit;
}

// --- Ensure OTP exists in session ---
if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_expires'])) {
    echo json_encode(['success' => false, 'message' => 'No OTP found. Please request a new one.']);
    exit;
}

// --- Check expiry ---
if (time() > $_SESSION['otp_expires']) {
    echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
    exit;
}

// --- Verify OTP ---
if (hash_equals($_SESSION['otp'], $inputOtp)) {
    // ✅ OTP is correct
    require_once __DIR__ . '/database/database.php';
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

    unset($_SESSION['otp'], $_SESSION['otp_expires'], $_SESSION['otp_attempts'], $_SESSION['otp_locked_until'], $_SESSION['pending_registration']);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'OTP verified successfully. Registration complete.']);
    } else {
        error_log('DB insert failed: ' . $errorMsg);
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.', 'error' => $errorMsg]);
    }
    exit;
} else {
    // ❌ Wrong OTP
    if (!isset($_SESSION['otp_attempts'])) {
        $_SESSION['otp_attempts'] = 0;
    }
    $_SESSION['otp_attempts']++;

    // Lock after 5 failed attempts
    if ($_SESSION['otp_attempts'] >= 5) {
        $_SESSION['otp_locked_until'] = time() + 300; // 5 minutes
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please wait 5 minutes before retrying.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Incorrect OTP. Please try again.']);
    exit;
}
