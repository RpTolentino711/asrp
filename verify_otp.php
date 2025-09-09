<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['otp']) || !isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid. Please register again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp']);
    if (!preg_match('/^\d{6}$/', $entered_otp)) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP format.']);
        exit;
    }
    if (isset($_SESSION['otp_expires']) && time() > $_SESSION['otp_expires']) {
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please resend the code.']);
        exit;
    }
    if ($entered_otp === $_SESSION['otp']) {
        // Register the user, clean up session, return JSON success
        // (your DB insert code here)
        unset($_SESSION['otp'], $_SESSION['pending_registration'], $_SESSION['otp_email'], $_SESSION['otp_expires'], $_SESSION['otp_attempts']);
        echo json_encode(['success' => true, 'message' => 'Registration successful! You can now log in.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
    }
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit;