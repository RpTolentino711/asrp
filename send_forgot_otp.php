<?php
require_once 'database/database.php';
require_once 'class.phpmailer.php';

header('Content-Type: application/json');

if (empty($_POST['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

$email = trim($_POST['email']);
$db = new Database();
$user = $db->getUserByEmail($email);
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No account found with that email.']);
    exit;
}

$otp = random_int(100000, 999999);
$expires_at = time() + 300; // 5 minutes

// Store OTP and expiry in session
session_start();
$_SESSION['forgot_otp'] = $otp;
$_SESSION['forgot_otp_email'] = $email;
$_SESSION['forgot_otp_expires'] = $expires_at;

// Send OTP email

$mail = new PHPMailer();
$mail->CharSet    = 'UTF-8';
$mail->isSMTP();
$mail->Host       = 'smtp.hostinger.com';
$mail->Port       = 587;
$mail->SMTPAuth   = true;
$mail->SMTPSecure = 'tls';
$mail->Username   = 'management@asrt.space';
$mail->Password   = '@Pogilameg10';
$mail->setFrom($mail->Username, 'ASRP Password Reset');
$mail->addReplyTo('no-reply@asrp.local', 'ASRP Password Reset');
$mail->addAddress($email);
$mail->isHTML(true);
$mail->Subject = "Your ASRP Password Reset OTP";
$mail->Body    = "<p>Your OTP for password reset is <b>$otp</b>.</p><p>This code will expire in 5 minutes.</p><p>If you did not request this, please ignore this email.</p>";
$mail->AltBody = "Your OTP for password reset is $otp. This code will expire in 5 minutes.";

if ($mail->send()) {
    echo json_encode(['success' => true, 'expires_at' => $expires_at]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP email.', 'error' => $mail->ErrorInfo]);
}
