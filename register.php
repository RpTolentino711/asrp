<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/database/database.php';
require_once __DIR__ . '/class.phpmailer.php';
require_once __DIR__ . '/class.smtp.php';

// Configuration
define('OTP_EXPIRY_MINUTES', 5);
define('MIN_PASSWORD_LENGTH', 6);

function validateRegistrationInput($data) {
    $errors = [];
    
    // Required fields check
    $requiredFields = ['fname', 'lname', 'email', 'phone', 'username', 'password', 'confirm_password'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode(' ', $errors)];
    }
    
    // Email validation
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }
    
    // Password confirmation
    if ($data['password'] !== $data['confirm_password']) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }
    
    // Phone validation
    if (!preg_match('/^\d{11}$/', $data['phone'])) {
        return ['success' => false, 'message' => 'Phone number must be exactly 11 digits.'];
    }
    
    // Password strength validation
    if (strlen($data['password']) < MIN_PASSWORD_LENGTH) {
        return ['success' => false, 'message' => 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.'];
    }
    
    if (!preg_match('/[A-Z]/', $data['password'])) {
        return ['success' => false, 'message' => 'Password must contain at least one uppercase letter.'];
    }
    
    if (!preg_match('/[\W_]/', $data['password'])) {
        return ['success' => false, 'message' => 'Password must contain at least one special character.'];
    }
    
    return ['success' => true];
}

function checkDuplicateCredentials($pdo, $username, $email) {
    try {
        // Check username
        $stmt = $pdo->prepare("SELECT Client_ID FROM client WHERE C_username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Username already exists. Please choose a different username.'];
        }
        
        // Check email
        $stmt = $pdo->prepare("SELECT Client_ID FROM client WHERE Client_Email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Email address is already registered. Please use a different email or try logging in.'];
        }
        
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Database check error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to verify credentials. Please try again.'];
    }
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

function sendRegistrationOTPEmail($email, $firstName, $otp) {
    $mail = setupRegistrationMailer();
    
    $mail->setFrom($mail->Username, 'ASRT Spaces Registration');
    $mail->addReplyTo('no-reply@asrt.space', 'ASRT Spaces Registration');
    $mail->addAddress($email);
    
    $mail->isHTML(true);
    $mail->Subject = "Verify Your Email - ASRT Spaces Registration";
    
    $safeName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $expiryMinutes = OTP_EXPIRY_MINUTES;
    $currentTime = date('F j, Y \a\t g:i A T');
    
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .container { max-width: 600px; margin: 0 auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; }
            .header { 
                background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
                border-radius: 12px 12px 0 0;
            }
            .logo { font-size: 28px; font-weight: bold; margin-bottom: 10px; }
            .subtitle { font-size: 16px; opacity: 0.9; }
            .content { padding: 40px 30px; background: #f8fafc; }
            .greeting { font-size: 18px; margin-bottom: 20px; color: #1e293b; font-weight: 600; }
            .welcome-text { font-size: 16px; color: #475569; margin-bottom: 25px; }
            .otp-container { text-align: center; margin: 30px 0; }
            .otp-label { 
                font-size: 14px; 
                color: #64748b; 
                margin-bottom: 15px; 
                text-transform: uppercase; 
                letter-spacing: 1px;
                font-weight: 600;
            }
            .otp-code { 
                font-size: 36px; 
                font-weight: bold; 
                color: #1e40af; 
                padding: 25px 30px; 
                background: white; 
                border: 3px dashed #3b82f6;
                border-radius: 12px;
                letter-spacing: 8px;
                display: inline-block;
                box-shadow: 0 8px 25px rgba(30, 64, 175, 0.15);
                margin: 10px 0;
            }
            .info-box { 
                background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 100%); 
                border-left: 4px solid #0ea5e9; 
                padding: 25px; 
                margin: 30px 0;
                border-radius: 0 12px 12px 0;
                box-shadow: 0 2px 10px rgba(14, 165, 233, 0.1);
            }
            .info-title { font-weight: bold; color: #0c4a6e; margin-bottom: 15px; font-size: 16px; }
            .info-list { margin: 0; padding-left: 20px; color: #0369a1; }
            .info-list li { margin-bottom: 8px; }
            .next-steps {
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                padding: 20px;
                margin: 25px 0;
                border-radius: 8px;
            }
            .next-steps-title { color: #166534; font-weight: bold; margin-bottom: 10px; }
            .next-steps-text { color: #166534; font-size: 14px; }
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
                border-radius: 0 0 12px 12px;
            }
            .footer h3 { color: white; margin-bottom: 15px; font-size: 20px; }
            .footer p { margin: 8px 0; font-size: 14px; }
            .support-info { color: #60a5fa; font-weight: 500; }
            .timestamp { font-size: 12px; color: #64748b; margin-top: 20px; font-style: italic; }
            @media (max-width: 600px) {
                .container { margin: 10px; }
                .content, .header, .footer { padding: 20px 15px; }
                .otp-code { font-size: 28px; padding: 20px 25px; letter-spacing: 6px; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>🏠 ASRT Spaces</div>
                <div class='subtitle'>Email Verification Required</div>
            </div>
            
            <div class='content'>
                <div class='greeting'>Hello {$safeName}!</div>
                
                <div class='welcome-text'>
                    Thank you for choosing ASRT Spaces! We're excited to have you join our community. To complete your registration and secure your account, please verify your email address.
                </div>
                
                <div class='otp-container'>
                    <div class='otp-label'>Your Verification Code</div>
                    <div class='otp-code'>{$otp}</div>
                </div>
                
                <div class='info-box'>
                    <div class='info-title'>Verification Instructions:</div>
                    <ul class='info-list'>
                        <li>Enter this code in the verification window on our website</li>
                        <li>Code expires in <strong>{$expiryMinutes} minutes</strong></li>
                        <li>Code can only be used once</li>
                        <li>Keep this code confidential and don't share it with anyone</li>
                    </ul>
                </div>
                
                <div class='next-steps'>
                    <div class='next-steps-title'>What happens next?</div>
                    <div class='next-steps-text'>
                        Once verified, you'll receive a welcome email with your account details and can immediately start exploring all the features ASRT Spaces has to offer!
                    </div>
                </div>
                
                <div class='security-notice'>
                    <strong>Security Note:</strong> If you didn't create an account with ASRT Spaces, please ignore this email. No account will be created without email verification.
                </div>
                
                <div class='timestamp'>Verification code sent: {$currentTime}</div>
            </div>
            
            <div class='footer'>
                <h3>ASRT Spaces</h3>
                <p>Your trusted space management partner</p>
                <p>Need help? Contact us at <span class='support-info'>management@asrt.space</span></p>
                <p>© 2025 ASRT Spaces. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    $mail->AltBody = "Hello {$safeName}!\n\nThank you for registering with ASRT Spaces!\n\nTo complete your registration, please verify your email address using this code:\n\n{$otp}\n\nImportant Information:\n- Enter this code on our website to verify your email\n- Code expires in {$expiryMinutes} minutes\n- Keep this code confidential\n- Code sent: {$currentTime}\n\nOnce verified, you'll receive a welcome email and can start using your account.\n\nIf you didn't register with ASRT Spaces, please ignore this email.\n\nBest regards,\nASRT Spaces Registration Team\n\nNeed help? Contact: management@asrt.space";
    
    return $mail->send();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }
    
    // Collect and sanitize input
    $inputData = [
        'fname' => trim($_POST['fname'] ?? ''),
        'lname' => trim($_POST['lname'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];
    
    // Validate input
    $validation = validateRegistrationInput($inputData);
    if (!$validation['success']) {
        echo json_encode($validation);
        exit;
    }
    
    // Initialize database connection
    $db = new Database();
    $pdo = $db->pdo ?? null;
    if (!$pdo && method_exists($db, 'opencon')) {
        $pdo = $db->opencon();
    }
    
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']);
        exit;
    }
    
    // Check for duplicate credentials
    $duplicateCheck = checkDuplicateCredentials($pdo, $inputData['username'], $inputData['email']);
    if (!$duplicateCheck['success']) {
        echo json_encode($duplicateCheck);
        exit;
    }
    
    // Generate OTP and store session data
    $otp = generateRegistrationOTP();
    
    $_SESSION['pending_registration'] = [
        'fname' => $inputData['fname'],
        'lname' => $inputData['lname'],
        'email' => $inputData['email'],
        'phone' => $inputData['phone'],
        'username' => $inputData['username'],
        'password' => password_hash($inputData['password'], PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_email'] = $inputData['email'];
    $_SESSION['otp_expires'] = time() + (OTP_EXPIRY_MINUTES * 60);
    $_SESSION['otp_attempts'] = 0;
    unset($_SESSION['otp_locked_until']);
    
    // Send verification email
    if (sendRegistrationOTPEmail($inputData['email'], $inputData['fname'], $otp)) {
        error_log("Registration OTP sent successfully to: " . $inputData['email']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration initiated successfully! Please check your email for the verification code.',
            'pending_verification' => true,
            'email' => $inputData['email'],
            'expires_at' => $_SESSION['otp_expires']
        ]);
    } else {
        error_log("Failed to send registration OTP to: " . $inputData['email']);
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send verification email. Please try again later.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Registration Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}

exit;