<?php
session_start();
header('Content-Type: application/json');

require_once 'database/database.php';
require_once __DIR__ . '/class.phpmailer.php';
require_once __DIR__ . '/class.smtp.php';

// Configuration
define('FORGOT_OTP_EXPIRY_MINUTES', 10);
define('FORGOT_OTP_COOLDOWN_SECONDS', 60);

function validateEmail($email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }
    return ['success' => true];
}

function checkForgotRateLimit() {
    if (isset($_SESSION['last_forgot_otp_sent'])) {
        $timeSinceLastSent = time() - $_SESSION['last_forgot_otp_sent'];
        if ($timeSinceLastSent < FORGOT_OTP_COOLDOWN_SECONDS) {
            $wait = FORGOT_OTP_COOLDOWN_SECONDS - $timeSinceLastSent;
            return [
                'success' => false, 
                'message' => "Please wait {$wait} seconds before requesting another password reset."
            ];
        }
    }
    return ['success' => true];
}

function generateForgotOTP() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function setupForgotMailer() {
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

function sendForgotOTPEmail($email, $firstName, $otp) {
    $mail = setupForgotMailer();
    
    $mail->setFrom($mail->Username, 'ASRT Spaces Security');
    $mail->addReplyTo('no-reply@asrt.space', 'ASRT Spaces Security');
    $mail->addAddress($email);
    
    $mail->isHTML(true);
    $mail->Subject = "Password Reset Code - ASRT Spaces";
    
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
                <p>Password Reset Request</p>
            </div>
            <div class='content'>
                <p>Hi {$safeName},</p>
                <p>We received a request to reset your password. Here's your verification code:</p>
                <div class='otp-code'>{$otp}</div>
                <div class='warning'>
                    <strong>Security Notice:</strong>
                    <ul>
                        <li>This code expires in <strong>{$expiryMinutes} minutes</strong></li>
                        <li>Only use this code if you requested a password reset</li>
                        <li><strong>Never share this code</strong> with anyone</li>
                        <li>ASRT Spaces will never ask for this code via phone or email</li>
                    </ul>
                </div>
                <p><strong>Didn't request this?</strong> If you didn't request a password reset, please ignore this email. Your account remains secure.</p>
            </div>
            <div class='footer'>
                <p>This is an automated security message from ASRT Spaces</p>
                <p>If you need help, contact support at management@asrt.space</p>
            </div>
        </div>
    </body>
    </html>";
    
    $mail->AltBody = "Hi {$safeName},\n\nWe received a request to reset your ASRT Spaces password.\n\nYour verification code is: {$otp}\n\nThis code expires in {$expiryMinutes} minutes.\n\nIf you didn't request this, please ignore this email.\n\nRegards,\nASRT Spaces Security Team";
    
    return $mail->send();
}

try {
    // Get and validate email
    $email = trim($_POST['email'] ?? '');
    
    $emailValidation = validateEmail($email);
    if (!$emailValidation['success']) {
        echo json_encode($emailValidation);
        exit;
    }
    
    // Check rate limiting
    $rateLimitCheck = checkForgotRateLimit();
    if (!$rateLimitCheck['success']) {
        echo json_encode($rateLimitCheck);
        exit;
    }
    
    // Check if email exists in database using your existing method
    $db = new Database();
    $user = $db->getUserByEmail($email);
    
    if (!$user) {
        // Email not found in database - return clear error message
        echo json_encode([
            'success' => false,
            'message' => 'Email address not found. Please check your email or register a new account.'
        ]);
        exit;
    }
    
    if ($user['Status'] !== 'Active') {
        // Account inactive
        echo json_encode([
            'success' => false,
            'message' => 'Account is inactive. Please contact support.'
        ]);
        exit;
    }
    
    // Email exists and account is active - proceed with OTP generation
    $otp = generateForgotOTP();
    
    // Store in session
    $_SESSION['forgot_otp'] = $otp;
    $_SESSION['forgot_otp_expires'] = time() + (FORGOT_OTP_EXPIRY_MINUTES * 60);
    $_SESSION['forgot_otp_attempts'] = 0;
    $_SESSION['forgot_email'] = $email;
    $_SESSION['forgot_client_id'] = $user['Client_ID'];
    $_SESSION['last_forgot_otp_sent'] = time();
    unset($_SESSION['forgot_otp_verified']);
    
    // Send email
    $firstName = $user['Client_fn'] ?: 'User';
    
    if (sendForgotOTPEmail($email, $firstName, $otp)) {
        error_log("Forgot password OTP sent to: " . $email);
        
        echo json_encode([
            'success' => true,
            'message' => 'A password reset code has been sent to your email.',
            'expires_at' => $_SESSION['forgot_otp_expires']
        ]);
    } else {
        error_log("Failed to send forgot password OTP to: " . $email);
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send password reset code. Please try again later.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Forgot Password OTP Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}

exit;