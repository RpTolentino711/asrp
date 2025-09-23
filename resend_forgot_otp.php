<?php
session_start();
header('Content-Type: application/json');

// Disable error display to prevent breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'class.phpmailer.php';
require_once 'database/database.php';

try {
    // Check if there's an active forgot password session
    if (!isset($_SESSION['forgot_otp_email'])) {
        throw new Exception('No active password reset session. Please start the process again.');
    }

    $email = $_SESSION['forgot_otp_email'];

    // Rate limiting - 60 second cooldown
    if (isset($_SESSION['last_forgot_otp_sent']) && (time() - $_SESSION['last_forgot_otp_sent']) < 60) {
        $wait = 60 - (time() - $_SESSION['last_forgot_otp_sent']);
        throw new Exception("Please wait {$wait} seconds before requesting a new OTP.");
    }

    // Additional rate limiting by IP
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip_key = "forgot_otp_resend_ip_{$client_ip}";
    
    if (!isset($_SESSION[$ip_key])) {
        $_SESSION[$ip_key] = [];
    }
    
    // Clean old IP attempts
    $_SESSION[$ip_key] = array_filter($_SESSION[$ip_key], function($timestamp) {
        return (time() - $timestamp) < 3600; // 1 hour
    });
    
    // Check IP rate limit (max 3 resends per hour)
    if (count($_SESSION[$ip_key]) >= 3) {
        throw new Exception('Too many resend attempts. Please try again later.');
    }

    // Check if user still exists
    $db = new Database();
    $user = $db->getUserByEmail($email);
    
    if (!$user) {
        throw new Exception('Account no longer exists.');
    }

    // Update rate limiting
    $_SESSION['last_forgot_otp_sent'] = time();
    $_SESSION[$ip_key][] = time();

    // Generate new OTP
    try {
        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        $otp = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    $expires_at = time() + (5 * 60); // 5 minutes

    // Update session data
    $_SESSION['forgot_otp'] = $otp;
    $_SESSION['forgot_otp_expires'] = $expires_at;
    $_SESSION['forgot_otp_attempts'] = 0; // Reset attempts
    unset($_SESSION['forgot_otp_locked_until']); // Remove any lockout

    // Send new OTP via email
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Username = 'management@asrt.space';
    $mail->Password = '@Pogilameg10';
    
    $mail->Timeout = 30;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ),
    );

    $mail->setFrom('management@asrt.space', 'ASRT Spaces');
    $mail->addReplyTo('management@asrt.space', 'ASRT Spaces');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'ASRT Spaces - New Password Reset Code';
    
    $safeName = isset($user['Client_fn']) && !empty($user['Client_fn']) 
        ? htmlspecialchars($user['Client_fn'], ENT_QUOTES, 'UTF-8') 
        : 'User';

    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>New Password Reset Code</title>
    </head>
    <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: white;'>
            <div style='background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); padding: 30px 20px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 28px; font-weight: bold;'>ASRT Spaces</h1>
                <p style='color: white; margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>New Password Reset Code</p>
            </div>
            
            <div style='padding: 40px 30px; background: white;'>
                <p style='font-size: 16px; margin-bottom: 20px; color: #333;'>Hi {$safeName},</p>
                
                <p style='font-size: 16px; margin-bottom: 30px; color: #333; line-height: 1.5;'>
                    You requested a new password reset code. Here's your fresh verification code:
                </p>
                
                <div style='text-align: center; margin: 40px 0;'>
                    <div style='display: inline-block; background: #f8fafc; border: 2px dashed #059669; border-radius: 8px; padding: 20px 30px;'>
                        <span style='font-size: 36px; font-weight: bold; color: #059669; letter-spacing: 4px; font-family: monospace;'>
                            {$otp}
                        </span>
                    </div>
                </div>
                
                <div style='background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 15px; margin: 30px 0; text-align: center;'>
                    <p style='color: #dc2626; font-weight: 600; margin: 0; font-size: 14px;'>
                        ⚠️ This new code expires in 5 minutes
                    </p>
                </div>
                
                <div style='background: #fffbeb; border: 1px solid #fed7aa; border-radius: 6px; padding: 15px; margin: 20px 0;'>
                    <p style='color: #d97706; font-weight: 500; margin: 0; font-size: 14px;'>
                        📝 Note: This replaces your previous code, which is no longer valid.
                    </p>
                </div>
                
                <p style='font-size: 14px; color: #666; margin-top: 30px; line-height: 1.5;'>
                    If you did not request this new code, you can safely ignore this email.
                </p>
                
                <p style='font-size: 14px; color: #666; margin-top: 20px;'>
                    Best regards,<br>
                    <strong>ASRT Spaces Team</strong>
                </p>
            </div>
            
            <div style='background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #666;'>
                <p style='margin: 0;'>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>";

    $mail->AltBody = "Hi {$safeName},\n\nYou requested a new password reset code for your ASRT Spaces account.\n\nYour new verification code is: {$otp}\n\nThis code expires in 5 minutes and replaces your previous code.\n\nBest regards,\nASRT Spaces Team";

    if ($mail->send()) {
        error_log("Forgot password OTP resent successfully to: " . $email);
        echo json_encode([
            'success' => true,
            'message' => 'A new password reset code has been sent to your email.',
            'expires_at' => $expires_at
        ]);
    } else {
        throw new Exception('Failed to send new OTP email: ' . $mail->ErrorInfo);
    }

} catch (Exception $e) {
    error_log("Error in resend_forgot_otp.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>