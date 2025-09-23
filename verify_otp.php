<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/class.phpmailer.php';
require_once __DIR__ . '/class.smtp.php';

// Configuration
define('MAX_OTP_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 300); // 5 minutes

function validateOTPSession() {
    if (!isset($_SESSION['otp_email']) || !isset($_SESSION['pending_registration'])) {
        return ['success' => false, 'message' => 'Session expired or invalid. Please register again.'];
    }
    return ['success' => true];
}

function validateOTPInput($otp) {
    if (!isset($otp)) {
        return ['success' => false, 'message' => 'No OTP provided.'];
    }
    
    if (!preg_match('/^\d{6}$/', trim($otp))) {
        return ['success' => false, 'message' => 'Invalid OTP format. Please enter 6 digits.'];
    }
    
    return ['success' => true];
}

function checkOTPLockout() {
    if (isset($_SESSION['otp_locked_until']) && time() < $_SESSION['otp_locked_until']) {
        $wait = $_SESSION['otp_locked_until'] - time();
        return ['success' => false, 'message' => "Too many attempts. Try again in {$wait} seconds."];
    }
    return ['success' => true];
}

function validateOTPExists() {
    if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_expires'])) {
        return ['success' => false, 'message' => 'No OTP found. Please request a new one.'];
    }
    return ['success' => true];
}

function checkOTPExpiry() {
    if (time() > $_SESSION['otp_expires']) {
        return ['success' => false, 'message' => 'OTP expired. Please request a new one.'];
    }
    return ['success' => true];
}

function setupWelcomeMailer() {
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

function sendWelcomeEmail($email, $firstName, $lastName, $username) {
    $mail = setupWelcomeMailer();
    
    $mail->setFrom($mail->Username, 'ASRT Spaces Welcome Team');
    $mail->addReplyTo('no-reply@asrt.space', 'ASRT Spaces');
    $mail->addAddress($email);
    
    $mail->isHTML(true);
    $mail->Subject = "Welcome to ASRT Spaces - Your Account is Ready!";
    
    $safeName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $safeLastName = htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8');
    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $registrationDate = date('F j, Y \a\t g:i A T');
    
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .container { max-width: 650px; margin: 0 auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; }
            .header { 
                background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%); 
                color: white; 
                padding: 40px 30px; 
                text-align: center; 
                border-radius: 16px 16px 0 0;
            }
            .logo { font-size: 32px; font-weight: bold; margin-bottom: 10px; }
            .welcome-title { font-size: 24px; margin-bottom: 10px; }
            .welcome-subtitle { font-size: 16px; opacity: 0.9; }
            .content { padding: 40px 30px; background: #ffffff; }
            .greeting { font-size: 20px; margin-bottom: 25px; color: #1e293b; font-weight: 600; }
            .welcome-message { font-size: 16px; color: #475569; margin-bottom: 30px; }
            .account-details {
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
                border-left: 4px solid #0ea5e9;
                padding: 25px;
                margin: 30px 0;
                border-radius: 0 12px 12px 0;
                box-shadow: 0 2px 10px rgba(14, 165, 233, 0.1);
            }
            .details-title { font-weight: bold; color: #0c4a6e; margin-bottom: 15px; font-size: 18px; }
            .detail-item { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                margin-bottom: 10px; 
                padding: 8px 0;
                border-bottom: 1px solid rgba(14, 165, 233, 0.1);
            }
            .detail-label { color: #0369a1; font-weight: 500; }
            .detail-value { 
                color: #0c4a6e; 
                font-weight: 600; 
                font-family: 'Courier New', monospace; 
                background: rgba(14, 165, 233, 0.1);
                padding: 4px 8px;
                border-radius: 4px;
            }
            .features-section {
                background: #f8fafc;
                border-radius: 12px;
                padding: 25px;
                margin: 30px 0;
            }
            .features-title { 
                font-size: 18px; 
                font-weight: bold; 
                color: #1e293b; 
                margin-bottom: 20px; 
                text-align: center;
            }
            .features-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
            .feature-item { 
                display: flex; 
                align-items: center; 
                padding: 15px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .feature-icon { 
                font-size: 24px; 
                margin-right: 12px; 
                color: #059669;
                width: 30px;
                text-align: center;
            }
            .feature-text { color: #374151; font-weight: 500; }
            .cta-section {
                background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
                color: white;
                padding: 30px;
                border-radius: 12px;
                text-align: center;
                margin: 30px 0;
            }
            .cta-title { font-size: 20px; font-weight: bold; margin-bottom: 15px; }
            .cta-button {
                display: inline-block;
                background: white;
                color: #1e40af;
                padding: 12px 30px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
                margin-top: 15px;
                transition: transform 0.2s;
            }
            .cta-button:hover { transform: translateY(-2px); }
            .footer { 
                padding: 30px; 
                text-align: center; 
                background: #1e293b; 
                color: #94a3b8;
                border-radius: 0 0 16px 16px;
            }
            .footer h3 { color: white; margin-bottom: 15px; font-size: 22px; }
            .footer p { margin: 8px 0; font-size: 14px; }
            .support-info { color: #60a5fa; font-weight: 500; }
            .social-links { margin: 20px 0; }
            .social-link { 
                color: #60a5fa; 
                text-decoration: none; 
                margin: 0 10px; 
                font-size: 14px;
            }
            @media (max-width: 600px) {
                .features-grid { grid-template-columns: 1fr; }
                .container { margin: 10px; }
                .content, .header, .footer { padding: 20px; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>🏠 ASRT Spaces</div>
                <div class='welcome-title'>Welcome Aboard!</div>
                <div class='welcome-subtitle'>Your journey with us begins now</div>
            </div>
            
            <div class='content'>
                <div class='greeting'>Hello {$safeName}!</div>
                
                <div class='welcome-message'>
                    Congratulations! Your ASRT Spaces account has been successfully created and verified. We're thrilled to have you join our community of satisfied clients who trust us with their space management needs.
                </div>
                
                <div class='account-details'>
                    <div class='details-title'>📋 Your Account Information</div>
                    <div class='detail-item'>
                        <span class='detail-label'>Full Name:</span>
                        <span class='detail-value'>{$safeName} {$safeLastName}</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Username:</span>
                        <span class='detail-value'>{$safeUsername}</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Email Address:</span>
                        <span class='detail-value'>{$email}</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Registration Date:</span>
                        <span class='detail-value'>{$registrationDate}</span>
                    </div>
                </div>
                
                <div class='features-section'>
                    <div class='features-title'>🌟 What You Can Do Now</div>
                    <div class='features-grid'>
                        <div class='feature-item'>
                            <div class='feature-icon'>🏡</div>
                            <div class='feature-text'>Browse Available Spaces</div>
                        </div>
                        <div class='feature-item'>
                            <div class='feature-icon'>💳</div>
                            <div class='feature-text'>Manage Payments & Invoices</div>
                        </div>
                        <div class='feature-item'>
                            <div class='feature-icon'>🔧</div>
                            <div class='feature-text'>Request Maintenance Services</div>
                        </div>
                        <div class='feature-item'>
                            <div class='feature-icon'>📞</div>
                            <div class='feature-text'>24/7 Customer Support</div>
                        </div>
                        <div class='feature-item'>
                            <div class='feature-icon'>📊</div>
                            <div class='feature-text'>Track Service History</div>
                        </div>
                        <div class='feature-item'>
                            <div class='feature-icon'>🔐</div>
                            <div class='feature-text'>Secure Account Management</div>
                        </div>
                    </div>
                </div>
                
                <div class='cta-section'>
                    <div class='cta-title'>Ready to Get Started?</div>
                    <p>Log in to your account now and explore everything ASRT Spaces has to offer!</p>
                    <a href='#' class='cta-button'>Access Your Dashboard</a>
                </div>
                
                <div style='margin-top: 30px; padding: 20px; background: #fef7cd; border-radius: 8px; border-left: 4px solid #f59e0b;'>
                    <strong style='color: #92400e;'>💡 Pro Tip:</strong>
                    <span style='color: #92400e;'>Keep your login credentials safe and never share them with anyone. If you need help, our support team is always here for you!</span>
                </div>
            </div>
            
            <div class='footer'>
                <h3>ASRT Spaces</h3>
                <p>Thank you for choosing us as your trusted space management partner</p>
                <div class='social-links'>
                    <a href='#' class='social-link'>Website</a> |
                    <a href='#' class='social-link'>Support Center</a> |
                    <a href='#' class='social-link'>Contact Us</a>
                </div>
                <p>Need assistance? Reach out to us at <span class='support-info'>management@asrt.space</span></p>
                <p style='margin-top: 20px; font-size: 12px;'>© 2025 ASRT Spaces. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    $mail->AltBody = "Welcome to ASRT Spaces, {$safeName}!\n\nYour account has been successfully created and verified.\n\nAccount Details:\nName: {$safeName} {$safeLastName}\nUsername: {$safeUsername}\nEmail: {$email}\nRegistration: {$registrationDate}\n\nYou can now:\n- Browse available spaces\n- Manage payments and invoices\n- Request maintenance services\n- Access 24/7 customer support\n- Track your service history\n- Manage your account securely\n\nLog in to your account to get started!\n\nIf you need help, contact us at management@asrt.space\n\nThank you for choosing ASRT Spaces!\n\nASRT Spaces Team";
    
    return $mail->send();
}

function handleFailedAttempt() {
    if (!isset($_SESSION['otp_attempts'])) {
        $_SESSION['otp_attempts'] = 0;
    }
    $_SESSION['otp_attempts']++;
    
    if ($_SESSION['otp_attempts'] >= MAX_OTP_ATTEMPTS) {
        $_SESSION['otp_locked_until'] = time() + LOCKOUT_DURATION;
        return [
            'success' => false, 
            'message' => 'Too many failed attempts. Please wait 5 minutes before retrying.'
        ];
    }
    
    $remainingAttempts = MAX_OTP_ATTEMPTS - $_SESSION['otp_attempts'];
    return [
        'success' => false, 
        'message' => "Incorrect OTP. {$remainingAttempts} attempts remaining."
    ];
}

function clearOTPSession() {
    unset(
        $_SESSION['otp'],
        $_SESSION['otp_expires'],
        $_SESSION['otp_attempts'],
        $_SESSION['otp_locked_until'],
        $_SESSION['pending_registration'],
        $_SESSION['otp_email']
    );
}

try {
    // Validate session
    $sessionCheck = validateOTPSession();
    if (!$sessionCheck['success']) {
        echo json_encode($sessionCheck);
        exit;
    }
    
    // Validate OTP input
    $otpValidation = validateOTPInput($_POST['otp'] ?? null);
    if (!$otpValidation['success']) {
        echo json_encode($otpValidation);
        exit;
    }
    
    $inputOtp = trim($_POST['otp']);
    
    // Check lockout
    $lockoutCheck = checkOTPLockout();
    if (!$lockoutCheck['success']) {
        echo json_encode($lockoutCheck);
        exit;
    }
    
    // Validate OTP exists
    $otpExistsCheck = validateOTPExists();
    if (!$otpExistsCheck['success']) {
        echo json_encode($otpExistsCheck);
        exit;
    }
    
    // Check expiry
    $expiryCheck = checkOTPExpiry();
    if (!$expiryCheck['success']) {
        echo json_encode($expiryCheck);
        exit;
    }
    
    // Verify OTP
    if (hash_equals($_SESSION['otp'], $inputOtp)) {
        // OTP is correct - proceed with registration
        require_once __DIR__ . '/database/database.php';
        $db = new Database();
        $userData = $_SESSION['pending_registration'];
        
        try {
            $success = $db->registerClient(
                $userData['fname'],
                $userData['lname'],
                $userData['email'],
                $userData['phone'],
                $userData['username'],
                $userData['password']
            );
            
            if ($success) {
                // Send welcome email
                $emailSent = sendWelcomeEmail(
                    $userData['email'],
                    $userData['fname'],
                    $userData['lname'],
                    $userData['username']
                );
                
                if ($emailSent) {
                    error_log("Welcome email sent successfully to: " . $userData['email']);
                } else {
                    error_log("Failed to send welcome email to: " . $userData['email']);
                }
                
                // Clear session data
                clearOTPSession();
                
                // Log successful registration
                error_log("User successfully registered: " . $userData['username'] . " (" . $userData['email'] . ")");
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Registration completed successfully! A welcome email has been sent to your inbox. You can now log in to your account.'
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Registration failed. Please try again.'
                ]);
            }
        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Registration failed. Please try again.',
                'error' => $e->getMessage()
            ]);
        }
    } else {
        // Wrong OTP
        $failureResponse = handleFailedAttempt();
        error_log("Failed OTP attempt for: " . $_SESSION['otp_email'] . " (Attempt: " . $_SESSION['otp_attempts'] . ")");
        echo json_encode($failureResponse);
    }
    
} catch (Exception $e) {
    error_log("OTP Verification Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}

exit;