<?php
// send_forgot_otp.php
session_start();
require_once 'database/database.php';
require_once 'class.phpmailer.php';

header('Content-Type: application/json');

if (empty($_POST['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

$email = trim($_POST['email']);
$db = new Database();

// Check if email exists in client table
$client = $db->getClientByEmail($email); // You may need to implement this method
if (!$client) {
    echo json_encode(['success' => false, 'message' => 'No account found with that email.']);
    exit;
}

// Generate OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = time() + 300; // 5 minutes

// Store OTP and expiry in session (or DB if preferred)
$_SESSION['forgot_otp'] = [
    'email' => $email,
    'otp' => $otp,
    'expires_at' => $expires_at
];

// Send OTP via email
$mail = new PHPMailer();
$mail->isMail(); // Use mail() or configure SMTP if needed
$mail->setFrom('no-reply@asrp.com', 'ASRP Spaces');
$mail->addAddress($email, $client['Client_fn'] . ' ' . $client['Client_ln']);
$mail->Subject = 'ASRP Password Reset OTP';
$mail->isHTML(true);
$mail->Body = '<p>Your password reset code is:</p><h2>' . htmlspecialchars($otp) . '</h2><p>This code will expire in 5 minutes.</p>';

if ($mail->send()) {
    echo json_encode(['success' => true, 'expires_at' => $expires_at]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
}
