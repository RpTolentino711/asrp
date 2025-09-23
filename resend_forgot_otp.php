<?php
session_start();
header('Content-Type: application/json');

require_once 'database/database.php';
require_once __DIR__ . '/class.phpmailer.php';
require_once __DIR__ . '/class.smtp.php';

// Configuration
define('FORGOT_OTP_EXPIRY_MINUTES', 10);
define('FORGOT_OTP_COOLDOWN_SECONDS', 60);

function checkForgotResendSession() {
    if (!isset($_SESSION['forgot_email'])) {
        return ['success' => false, 'message' => 'Session expired. Please start the password reset process again.'];
    }
    return ['success' => true];
}

function checkForgotResendRateLimit() {
    if (isset($_SESSION['last_forgot_otp_sent'])) {
        $timeSinceLastSent = time() - $_SESSION['last_forgot_otp_sent'];
        if ($timeSinceLastSent < FORGOT_OTP_COOLDOWN_SECONDS) {
            $wait = FORGOT_OTP_COOLDOWN_SECONDS - $timeSinceLastSent;
            return [
                'success' => false, 
                'message' => "Please wait {$wait} seconds before requesting another code."
            ];
        }
    }
    return ['success' => true];
}

function generateForgotOTP() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function setupForgotResendMailer() {
    $mail = new PHPMailer;
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    
    $mail->Username = 'management@asrt.space';
    $mail->Password = '@Pogilameg10'; // Move to environment variable
    
    $mail->Timeout = 30;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ],
    ];
    
    return $mail;
}

function sendForgotResendOTPEmail($email, $firstName, $otp) {
    $mail = setupForgotResendMailer();
    
    $mail->setFrom($mail->Username, 'ASRT Spaces Security');
    $mail->addReplyTo('no-reply@asrt.space', 'ASRT Spaces Security');
    $mail->addAddress($email);
    
    $mail->isHTML(true);
    $mail->Subject = "New Password Reset Code - ASRT Spaces";
    
    $safeName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $expiryMinutes = FORGOT_OTP_EXPIRY_MINUTES;
    
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
            .header { background: #ef4444; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px; background: #f8fafc; }
            .otp-code { 
                font-size: 32px; 
                font-weight: bold; 
                color: #ef4444; 
                text-align: center; 
                padding: 20px; 
                background: white; 
                border: 2px dashed #ef4444;
                margin: 20px 0;
                letter-spacing: 5px;
            }
            .warning { 
                background: #fef2f2; 
                border-left: 4px solid #ef4444; 
                padding: 15px; 
                margin: 20px 0;
                color: #991b1b;
            }
            .footer { padding: 20px; text-align: center; color: #64748b; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>ASRT Spaces</h1>
                <p>New Password Reset Code</p>
            </div>
            <div class='content'>
                <p>Hi {$safeName},</p>
                <p>Here's your new password reset verification code:</p>
                <div class='otp-code'>{$otp}</div>
                <div class='warning'>
                    <strong>Security Notice:</strong>
                    <ul>
                        <li>This is a <strong>new code</strong> - previous codes are now invalid</li>
                        <li>Code expires in <strong>{$expiryMinutes} minutes</strong></li>
                        <li>Never share this code with anyone</li>
                        <li>Use this code to reset your ASRT Spaces password</li>
                    </ul>
                </div>
                <p>If you didn't request this code, please ignore this email and ensure your account is secure.</p>
            </div>
            <div class='footer'>
                <p>This is an automated security message from ASRT Spaces</p>
            </div>
        </div>
    </body>
    </html>";
    
    $mail->AltBody = "Hi {$safeName},\n\nHere's your new password reset code: {$otp}\n\nThis code expires in {$expiryMinutes} minutes.\n\nPrevious codes are now invalid.\n\nIf you didn't request this, please ignore this email.\n\nRegards,\nASRT Spaces Security Team";
    
    return $mail->send();
}

try {
    // Check session
    $sessionCheck = checkForgotResendSession();
    if (!$sessionCheck['success']) {
        echo json_encode($sessionCheck);
        exit;
    }
    
    // Check rate limiting
    $rateLimitCheck = checkForgotResendRateLimit();
    if (!$rateLimitCheck['success']) {
        echo json_encode($rateLimitCheck);
        exit;
    }
    
    // Get user data from database using your PDO method
    $email = $_SESSION['forgot_email'];
    $db = new Database();
    $user = $db->getUserByEmail($email);
    
    if (!$user || $user['Status'] !== 'Active') {
        echo json_encode([
            'success' => false,
            'message' => 'Session invalid. Please start the password reset process again.'
        ]);
        exit;
    }
    
    // Generate new OTP
    $otp = generateForgotOTP();
    
    // Update session with new OTP
    $_SESSION['forgot_otp'] = $otp;
    $_SESSION['forgot_otp_expires'] = time() + (FORGOT_OTP_EXPIRY_MINUTES * 60);
    $_SESSION['forgot_otp_attempts'] = 0;
    $_SESSION['last_forgot_otp_sent'] = time();
    
    // Clear previous verification status and lockout
    unset($_SESSION['forgot_otp_verified']);
    unset($_SESSION['forgot_otp_locked_until']);
    
    // Send email
    $firstName = $user['Client_fn'] ?: 'User';
    
    if (sendForgotResendOTPEmail($email, $firstName, $otp)) {
        error_log("Forgot password OTP resent to: " . $email);
        
        echo json_encode([
            'success' => true,
            'message' => 'A new password reset code has been sent to your email.',
            'expires_at' => $_SESSION['forgot_otp_expires']
        ]);
    } else {
        error_log("Failed to resend forgot password OTP to: " . $email);
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send new password reset code. Please try again later.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Forgot Password Resend OTP Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}

exit;