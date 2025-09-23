<?php
session_start();
header('Content-Type: application/json');

// Disable error display to prevent breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'class.phpmailer.php';
require_once 'database/database.php';

try {
    // Check if session has pending forgot password request
    if (!isset($_SESSION['forgot_otp_email'])) {
        throw new Exception('Session expired or invalid. Please request password reset again.');
    }

    $email = $_SESSION['forgot_otp_email'];

    // Prevent spamming: 60s cooldown
    if (isset($_SESSION['last_forgot_otp_sent']) && (time() - $_SESSION['last_forgot_otp_sent']) < 60) {
        $wait = 60 - (time() - $_SESSION['last_forgot_otp_sent']);
        throw new Exception("Please wait {$wait} seconds before requesting a new OTP.");
    }

    $db = new Database();
    
    // Get user info for personalized email
    $stmt = $db->getConnection()->prepare("SELECT Client_ID, Client_Email, Client_fn FROM client WHERE Client_Email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User account not found.');
    }

    $_SESSION['last_forgot_otp_sent'] = time();

    // Generate new OTP
    $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = time() + (5 * 60); // 5 minutes

    // Update session with new OTP
    $_SESSION['forgot_otp'] = $otp;
    $_SESSION['forgot_otp_expires'] = $expires_at;
    $_SESSION['forgot_otp_attempts'] = 0;
    unset($_SESSION['forgot_otp_locked_until']);

    // Send OTP via PHPMailer (compatible with version 5.x)
    $mail = new PHPMailer();
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Username = 'management@asrt.space';
    $mail->Password = '@Pogilameg10';
    
    // Set timeout and options
    $mail->Timeout = 20;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ),
    );

    $mail->setFrom('management@asrt.space', 'ASRT Spaces Password Reset');
    $mail->addReplyTo('management@asrt.space', 'ASRT Spaces');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'ASRT Spaces - Password Reset Code (Resent)';
    
    $safeName = htmlspecialchars($user['Client_fn'] ? $user['Client_fn'] : 'User', ENT_QUOTES, 'UTF-8');
    
    $mail->Body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
            <h1 style='color: white; margin: 0; font-size: 24px;'>ASRT Spaces</h1>
            <p style='color: white; margin: 5px 0 0 0; font-size: 16px;'>Password Reset Request (Resent)</p>
        </div>
        <div style='padding: 30px; background: #f8fafc; border-radius: 0 0 8px 8px;'>
            <p style='font-size: 16px; margin-bottom: 20px;'>Hi {$safeName},</p>
            <p style='font-size: 16px; margin-bottom: 20px;'>Here's your new password reset verification code:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <span style='font-size: 32px; font-weight: bold; color: #1e40af; background: white; padding: 15px 30px; border-radius: 8px; border: 2px solid #e2e8f0; letter-spacing: 3px; display: inline-block;'>
                    {$otp}
                </span>
            </div>
            <p style='color: #ef4444; font-weight: 600; text-align: center; margin: 20px 0;'>
                <strong>This code expires in 5 minutes.</strong>
            </p>
            <p style='font-size: 14px; color: #64748b; margin-top: 30px;'>
                If you did not request this password reset, please ignore this email.
            </p>
            <p style='font-size: 14px; color: #64748b; margin-top: 10px;'>
                Regards,<br>ASRT Spaces Team
            </p>
        </div>
    </div>";
    
    $mail->AltBody = "Hi {$safeName}, Your new password reset OTP code is {$otp}. This code expires in 5 minutes. If you did not request this, please ignore this email.";

    if (!$mail->send()) {
        throw new Exception('Failed to resend OTP: ' . $mail->ErrorInfo);
    }

    echo json_encode([
        'success' => true,
        'message' => 'A new password reset OTP has been sent to your email.',
        'expires_at' => $expires_at
    ]);

} catch (Exception $e) {
    error_log("Error in resend_forgot_otp.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
?>