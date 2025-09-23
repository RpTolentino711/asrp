<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/class.phpmailer.php';
require_once __DIR__ . '/class.smtp.php';

// Configuration
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_COOLDOWN_SECONDS', 60);

function validateRegistrationSession() {
    if (!isset($_SESSION['otp_email']) || !isset($_SESSION['pending_registration'])) {
        return ['success' => false, 'message' => 'Session expired or invalid. Please register again.'];
    }
    return ['success' => true];
}

function checkResendRateLimit() {
    if (isset($_SESSION['last_otp_sent'])) {
        $timeSinceLastSent = time() - $_SESSION['last_otp_sent'];
        if ($timeSinceLastSent < OTP_COOLDOWN_SECONDS) {
            $wait = OTP_COOLDOWN_SECONDS - $timeSinceLastSent;
            return [
                'success' => false, 
                'message' => "Please wait {$wait} seconds before requesting a new verification code."
            ];
        }
    }
    return ['success' => true];
}

function generateRegistrationOTP() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function setupRegistrationMailer() {
    $mail = new PHPMailer;
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    
    // Use environment variables in production
    $mail->Username = 'management@asrt.space';
    $mail->Password = '@Pogilameg10'; // Move to environment variable
    
    // Enhanced debugging (disable in production)
    if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level) {
            error_log("PHPMailer [$level]: $str");
        };
    }
    
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

function sendResendRegistrationOTPEmail($email, $firstName, $otp) {
    $mail = setupRegistrationMailer();
    
    $mail->setFrom($mail->Username, 'ASRT Spaces Registration');
    $mail->addReplyTo('no-reply@asrt.space', 'ASRT Spaces Registration');
    $mail->addAddress($email);
    
    $mail->isHTML(true);
    $mail->Subject = "New Verification Code - ASRT Spaces Registration";
    
    $safeName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $expiryMinutes = OTP_EXPIRY_MINUTES;
    $currentTime = date('F j, Y \a\t g:i A T');
    
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; line-height: 1.6; }
            .header { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; padding: 30px 20px; text-align: center; }
            .logo { font-size: 28px; font-weight: bold; margin-bottom: 10px; }
            .subtitle { font-size: 16px; opacity: 0.9; }
            .content { padding: 40px 30px; background: #f8fafc; }
            .greeting { font-size: 18px; margin-bottom: 20px; color: #1e293b; }
            .otp-container { text-align: center; margin: 30px 0; }
            .otp-label { font-size: 14px; color: #64748b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
            .otp-code { 
                font-size: 36px; 
                font-weight: bold; 
                color: #1e40af; 
                padding: 20px 30px; 
                background: white; 
                border: 3px dashed #3b82f6;
                border-radius: 12px;
                letter-spacing: 8px;
                display: inline-block;
                box-shadow: 0 4px 15px rgba(30, 64, 175, 0.1);
            }
            .info-box { 
                background: #e0f2fe; 
                border-left: 4px solid #0ea5e9; 
                padding: 20px; 
                margin: 25px 0;
                border-radius: 0 8px 8px 0;
            }
            .info-title { font-weight: bold; color: #0c4a6e; margin-bottom: 10px; }
            .info-list { margin: 0; padding-left: 20px; color: #0c4a6e; }
            .info-list li { margin-bottom: 5px; }
            .security-notice { 
                background: #fef3c7; 
                border: 1px solid #f59e0b; 
                padding: 15px; 
                margin: 25px 0;
                border-radius: 8px;
                color: #92400e;
                font-size: 14px;
            }
            .footer { 
                padding: 30px 20px; 
                text-align: center; 
                background: #1e293b; 
                color: #94a3b8; 
            }
            .footer h3 { color: white; margin-bottom: 15px; font-size: 20px; }
            .footer p { margin: 8px 0; font-size: 14px; }
            .footer .support { color: #60a5fa; }
            .timestamp { font-size: 12px; color: #64748b; margin-top: 20px; font-style: italic; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>🏠 ASRT Spaces</div>
                <div class='subtitle'>New Verification Code</div>
            </div>
            
            <div class='content'>
                <div class='greeting'>Hi {$safeName},</div>
                
                <p>You requested a new verification code for your ASRT Spaces registration. Here's your updated code:</p>
                
                <div class='otp-container'>
                    <div class='otp-label'>Your New Verification Code</div>
                    <div class='otp-code'>{$otp}</div>
                </div>
                
                <div class='info-box'>
                    <div class='info-title'>Important Information:</div>
                    <ul class='info-list'>
                        <li>This is a <strong>new code</strong> - previous codes are now invalid</li>
                        <li>Code expires in <strong>{$expiryMinutes} minutes</strong></li>
                        <li>Enter this code in your registration window to complete your account setup</li>
                        <li>This code can only be used once</li>
                    </ul>
                </div>
                
                <div class='security-notice'>
                    <strong>Security Note:</strong> If you didn't request this verification code, please ignore this email. Your registration will remain incomplete and no account will be created.
                </div>
                
                <p>Once you enter this code, you'll have full access to your ASRT Spaces account and can start exploring our services.</p>
                
                <div class='timestamp'>Code generated on: {$currentTime}</div>
            </div>
            
            <div class='footer'>
                <h3>ASRT Spaces</h3>
                <p>This is an automated message from our registration system</p>
                <p>Need help? Contact us at <span class='support'>management@asrt.space</span></p>
                <p>© 2025 ASRT Spaces. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    $mail->AltBody = "Hi {$safeName},\n\nYou requested a new verification code for your ASRT Spaces registration.\n\nYour new verification code is: {$otp}\n\nImportant:\n- This is a new code - previous codes are invalid\n- Code expires in {$expiryMinutes} minutes\n- Enter this code to complete your registration\n- Code generated on: {$currentTime}\n\nIf you didn't request this code, please ignore this email.\n\nRegards,\nASRT Spaces Registration Team\n\nNeed help? Contact: management@asrt.space";
    
    return $mail->send();
}

try {
    // Validate registration session
    $sessionValidation = validateRegistrationSession();
    if (!$sessionValidation['success']) {
        echo json_encode($sessionValidation);
        exit;
    }
    
    // Check rate limiting
    $rateLimitCheck = checkResendRateLimit();
    if (!$rateLimitCheck['success']) {
        echo json_encode($rateLimitCheck);
        exit;
    }
    
    // Update rate limiting timestamp
    $_SESSION['last_otp_sent'] = time();
    
    // Generate new OTP
    $otp = generateRegistrationOTP();
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_expires'] = time() + (OTP_EXPIRY_MINUTES * 60);
    $_SESSION['otp_attempts'] = 0;
    unset($_SESSION['otp_locked_until']);
    
    // Get user details
    $email = $_SESSION['otp_email'];
    $firstName = $_SESSION['pending_registration']['fname'] ?? 'User';
    
    // Send email
    if (sendResendRegistrationOTPEmail($email, $firstName, $otp)) {
        error_log("Registration OTP resent successfully to: " . $email);
        
        echo json_encode([
            'success' => true,
            'message' => 'A new verification code has been sent to your email address.',
            'expires_at' => $_SESSION['otp_expires']
        ]);
    } else {
        error_log("Failed to resend registration OTP to: " . $email);
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send verification code. Please try again later.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Registration OTP Resend Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}

exit;