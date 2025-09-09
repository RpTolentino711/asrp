<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['otp']) || !isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid. Please register again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    if (!preg_match('/^\d{6}$/', $entered_otp)) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP format.']);
        exit;
    }
    if ($entered_otp === $_SESSION['otp']) {
        require_once 'database/database.php';
        $db = new Database();
        $data = $_SESSION['pending_registration'];
        $success = $db->registerClient(
            $data['fname'],
            $data['lname'],
            $data['email'],
            $data['phone'],
            $data['username'],
            $data['password']
        );
        unset($_SESSION['otp']);
        unset($_SESSION['pending_registration']);
        unset($_SESSION['otp_email']);
        unset($_SESSION['otp_expires']);
        unset($_SESSION['otp_attempts']);
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Registration successful! You can now log in.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit;