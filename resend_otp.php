<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/class.phpmailer.php';
require_once __DIR__ . '/class.smtp.php';

// --- Check session data ---
if (!isset($_SESSION['otp_email']) || !isset($_SESSION['pending_registration'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired or invalid. Please register again.'
    ]);
    exit;
}

$email = $_SESSION['otp_email'];

// --- Prevent OTP spamming (60s cooldown) ---
if (isset($_SESSION['last_otp_sent']) && (time() - $_SESSION['last_otp_sent']) < 60) {
    $wait = 60 - (time() - $_SESSION['last_otp_sent']);
    echo json_encode([
        'success' => false,
        'message' => "Please wait {$wait} seconds before requesting a new OTP."
    ]);
    exit;
}
$_SESSION['last_otp_sent'] = time();

// --- Generate fresh OTP (same method as registration) ---
$otp = random_int(100000, 999999);
$_SESSION['otp'] = (string)$otp;
$_SESSION['otp_expires'] = time() + (5 * 60); // valid for 5 minutes
$_SESSION['otp_attempts'] = 0;
unset($_SESSION['otp_locked_until']);

// --- Send OTP email (same configuration as registration) ---
$mail = new PHPMailer;
$mail->CharSet    = 'UTF-8';
$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->Port       = 587;
$mail->SMTPAuth   = true;
$mail->SMTPSecure = 'tls';

// Log SMTP debug to Apache error.log
$mail->SMTPDebug = 2;
$mail->Debugoutput = function ($str, $level) {
    error_log("PHPMailer [$level]: $str");
};
$mail->Timeout = 20;

// Force TLS 1.2
$mail->SMTPOptions = [
    'ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
        'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
    ],
];

// Gmail credentials (same as registration)
$mail->Username = 'romeotolentino804@gmail.com';
$mail->Password = 'hhrp moci tugu helk';
$mail->setFrom($mail->Username, 'ASRP Registration');
$mail->addReplyTo('no-reply@asrp.local', 'ASRP Registration');
$mail->addAddress($email);

$mail->isHTML(true);
$mail->Subject = "Your verification code (Resent)";
$safeName = htmlspecialchars($_SESSION['pending_registration']['fname'] ?? '', ENT_QUOTES, 'UTF-8');
$mail->Body    = "<p>Hi {$safeName},</p>
                  <p>Your new OTP code is <b>{$otp}</b>.</p>
                  <p>This code expires in 5 minutes.</p>
                  <p>If you did not request this email, please ignore it.</p>
                  <p>See you soon!</p>
                  <p>Regards,<br>ASRP Registration</p>";
$mail->AltBody = "Your new OTP code is {$otp}. It expires in 5 minutes.";

if (!$mail->send()) {
    error_log("Failed to resend OTP to {$email}. Error: " . $mail->ErrorInfo);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to resend OTP. Please try again later.',
        'error'   => $mail->ErrorInfo,
    ]);
} else {
    error_log("OTP successfully resent to {$email}");
    echo json_encode([
        'success'    => true,
        'message'    => 'A new OTP has been sent to your email.',
        'expires_at' => $_SESSION['otp_expires']
    ]);
}
exit;