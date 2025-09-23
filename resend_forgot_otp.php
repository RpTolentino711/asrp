
<?php
session_start();
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// --- Check if session has pending forgot password request ---
if (!isset($_SESSION['forgot_otp']) || !isset($_SESSION['forgot_otp_email'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired or invalid. Please request OTP again.'
    ]);
    exit;
}

if (empty($_POST['otp'])) {
    echo json_encode(['success' => false, 'message' => 'OTP is required.']);
    exit;
}

$otp = trim($_POST['otp']);

// --- Check if OTP is locked due to too many attempts ---
if (isset($_SESSION['forgot_otp_locked_until']) && time() < $_SESSION['forgot_otp_locked_until']) {
    $wait = $_SESSION['forgot_otp_locked_until'] - time();
    echo json_encode([
        'success' => false,
        'message' => "Too many failed attempts. Please wait {$wait} seconds before trying again."
    ]);
    exit;
}

// --- Check if OTP has expired ---
if (time() > $_SESSION['forgot_otp_expires']) {
    unset($_SESSION['forgot_otp']);
    unset($_SESSION['forgot_otp_expires']);
    unset($_SESSION['forgot_otp_email']);
    echo json_encode([
        'success' => false,
        'message' => 'OTP has expired. Please request a new one.'
    ]);
    exit;
}

// --- Validate OTP format ---
if (!preg_match('/^\d{6}$/', $otp)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid 6-digit OTP.'
    ]);
    exit;
}

// --- Check OTP attempts ---
if (!isset($_SESSION['forgot_otp_attempts'])) {
    $_SESSION['forgot_otp_attempts'] = 0;
}

// --- Verify OTP ---
if ($_SESSION['forgot_otp'] !== $otp) {
    $_SESSION['forgot_otp_attempts']++;
    
    // Lock after 5 failed attempts for 10 minutes
    if ($_SESSION['forgot_otp_attempts'] >= 5) {
        $_SESSION['forgot_otp_locked_until'] = time() + (10 * 60); // 10 minutes
        echo json_encode([
            'success' => false,
            'message' => 'Too many failed attempts. Please wait 10 minutes before trying again.'
        ]);
        exit;
    }
    
    $remaining = 5 - $_SESSION['forgot_otp_attempts'];
    echo json_encode([
        'success' => false,
        'message' => "Invalid OTP. You have {$remaining} attempts remaining."
    ]);
    exit;
}

// --- OTP is correct, mark as verified ---
$_SESSION['forgot_otp_verified'] = true;

// Reset attempts counter
unset($_SESSION['forgot_otp_attempts']);
unset($_SESSION['forgot_otp_locked_until']);

echo json_encode([
    'success' => true,
    'message' => 'OTP verified successfully. You can now reset your password.'
]);
exit;
?>