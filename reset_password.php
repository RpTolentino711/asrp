<?php
session_start();
header('Content-Type: application/json');

require_once 'database/database.php';
require_once __DIR__ . '/class.phpmailer.php';
require_once __DIR__ . '/class.smtp.php';

function validateResetSession() {
    if (!isset($_SESSION['forgot_otp_verified']) || $_SESSION['forgot_otp_verified'] !== true) {
        return ['success' => false, 'message' => 'Unauthorized. Please verify your email first.'];
    }
    
    if (!isset($_SESSION['forgot_email'])) {
        return ['success' => false, 'message' => 'Session expired. Please start the password reset process again.'];
    }
    
    if (!isset($_SESSION['reset_token'])) {
        return ['success' => false, 'message' => 'Invalid session. Please verify your email again.'];
    }
    
    return ['success' => true];
}

function validatePasswordInput($password, $confirmPassword) {
    if (empty($password) || empty($confirmPassword)) {
        return ['success' => false, 'message' => 'Please fill in both password fields.'];
    }
    
    if ($password !== $confirmPassword) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
    }
    
    // Enhanced password validation
    if (!preg_match('/[A-Z]/', $password)) {
        return ['success' => false, 'message' => 'Password must contain at least one uppercase letter.'];
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        return ['success' => false, 'message' => 'Password must contain at least one special character.'];
    }
    
    return ['success' => true];
}

function setupResetConfirmationMailer() {
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

function sendPasswordResetConfirmationEmail($email, $firstName, $username) {
    $mail = setupResetConfirmationMailer();
    
    $mail->setFrom($mail->Username, 'ASRT Spaces Security');
    $mail->addReplyTo('no-reply@asrt.space', 'ASRT Spaces Security');
    $mail->addAddress($email);
    
    $mail->isHTML(true);
    $mail->Subject = "Password Successfully Reset - ASRT Spaces";
    
    $safeName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $resetTime = date('F j, Y \a\t g:i A T');
    
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
            .header { background: #059669; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px; background: #f8fafc; }
            .success-badge { 
                display: inline-block;
                background: #059669; 
                color: white; 
                padding: 10px 20px;
                border-radius: 25px;
                font-weight: bold;
                margin: 20px 0;
            }
            .info-box { 
                background: #ecfdf5; 
                border-left: 4px solid #059669; 
                padding: 20px; 
                margin: 20px 0;
                border-radius: 0 8px 8px 0;
            }
            .security-notice { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                padding: 15px; 
                margin: 20px 0;
                border-radius: 8px;
                color: #856404;
            }
            .footer { padding: 20px; text-align: center; color: #64748b; font-size: 12px; }
            .username { font-family: 'Courier New', monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ ASRT Spaces</h1>
                <p>Password Reset Successful</p>
            </div>
            <div class='content'>
                <p>Hi {$safeName},</p>
                
                <div class='success-badge'>
                    🔒 Password Successfully Reset
                </div>
                
                <p>Your ASRT Spaces account password has been successfully updated.</p>
                
                <div class='info-box'>
                    <strong>Account Details:</strong><br>
                    <strong>Username:</strong> <span class='username'>{$safeUsername}</span><br>
                    <strong>Email:</strong> {$email}<br>
                    <strong>Reset Time:</strong> {$resetTime}
                </div>
                
                <div class='security-notice'>
                    <strong>🛡️ Security Reminder:</strong>
                    <ul>
                        <li>You can now log in with your new password</li>
                        <li>If you didn't reset your password, contact us immediately</li>
                        <li>Keep your login credentials secure and don't share them</li>
                        <li>Consider using a password manager for better security</li>
                    </ul>
                </div>
                
                <p>If you have any questions or concerns about your account security, please contact our support team.</p>
                
                <p><strong>Next Steps:</strong></p>
                <p>You can now log in to your ASRT Spaces account using your username <strong>{$safeUsername}</strong> and your new password.</p>
            </div>
            <div class='footer'>
                <p>This is an automated security notification from ASRT Spaces</p>
                <p>If you need help, contact support at management@asrt.space</p>
                <p>© 2025 ASRT Spaces. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    $mail->AltBody = "Hi {$safeName},\n\nYour ASRT Spaces password has been successfully reset.\n\nAccount Details:\nUsername: {$safeUsername}\nEmail: {$email}\nReset Time: {$resetTime}\n\nYou can now log in with your new password.\n\nIf you didn't reset your password, please contact us immediately at management@asrt.space.\n\nRegards,\nASRT Spaces Security Team";
    
    return $mail->send();
}

function updateClientPassword($email, $newPassword) {
    try {
        $db = new Database();
        
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password using your PDO method
        if ($db->updatePasswordByEmail($email, $hashedPassword)) {
            return ['success' => true, 'message' => 'Password updated successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update password. Please try again.'];
        }
        
    } catch (Exception $e) {
        error_log("Password update error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error. Please try again later.'];
    }
}

function clearForgotPasswordSession() {
    // Clear all forgot password related session data
    unset($_SESSION['forgot_otp_verified']);
    unset($_SESSION['forgot_email']);
    unset($_SESSION['reset_token']);
    unset($_SESSION['last_forgot_otp_sent']);
}

try {
    // Validate session
    $sessionValidation = validateResetSession();
    if (!$sessionValidation['success']) {
        echo json_encode($sessionValidation);
        exit;
    }
    
    // Get password inputs
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate password
    $passwordValidation = validatePasswordInput($password, $confirmPassword);
    if (!$passwordValidation['success']) {
        echo json_encode($passwordValidation);
        exit;
    }
    
    // Get email from session
    $email = $_SESSION['forgot_email'];
    
    // Get user details before updating password
    $db = new Database();
    $user = $db->getUserByEmail($email);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found. Please try again.'
        ]);
        exit;
    }
    
    // Update password in database
    $updateResult = updateClientPassword($email, $password);
    
    if ($updateResult['success']) {
        // Send confirmation email
        $firstName = $user['Client_fn'] ?: 'User';
        $username = $user['C_username'] ?: 'N/A';
        
        $emailSent = sendPasswordResetConfirmationEmail($email, $firstName, $username);
        
        if ($emailSent) {
            error_log("Password reset confirmation email sent to: " . $email);
        } else {
            error_log("Failed to send password reset confirmation email to: " . $email);
        }
        
        // Log successful password reset
        error_log("Password successfully reset for email: {$email}, Username: {$username}");
        
        // Clear session data
        clearForgotPasswordSession();
        
        echo json_encode([
            'success' => true,
            'message' => 'Your password has been successfully reset. A confirmation email has been sent to your email address. You can now log in with your new password.'
        ]);
    } else {
        // Log failed password reset
        error_log("Failed password reset for email: {$email}");
        
        echo json_encode($updateResult);
    }
    
} catch (Exception $e) {
    error_log("Password Reset Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}

exit;