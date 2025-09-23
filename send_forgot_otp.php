<?php
// Start session first before any output
session_start();

require_once 'database/database.php';
require_once 'class.phpmailer.php';

// Set JSON header
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Validate email input
if (empty($_POST['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

$email = trim($_POST['email']);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

try {
    $db = new Database();
    
    // Check if user exists with this email using correct column name
    $stmt = $db->getConnection()->prepare("SELECT Client_ID, Client_Email FROM client WHERE Client_Email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // For security, don't reveal if email exists or not
        echo json_encode(['success' => false, 'message' => 'If an account with this email exists, an OTP has been sent.']);
        exit;
    }

    // Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = time() + 300; // 5 minutes

    // Clear any existing forgot password session data
    unset($_SESSION['forgot_otp']);
    unset($_SESSION['forgot_otp_email']);
    unset($_SESSION['forgot_otp_expires']);
    unset($_SESSION['forgot_otp_verified']);

    // Store OTP data in session
    $_SESSION['forgot_otp'] = $otp;
    $_SESSION['forgot_otp_email'] = $email;
    $_SESSION['forgot_otp_expires'] = $expires_at;

    // Initialize PHPMailer
    $mail = new PHPMailer(true); // Enable exceptions
    
    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->Port = 587;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->Username = 'management@asrt.space';
        $mail->Password = '@Pogilameg10';
        
        // Recipients
        $mail->setFrom('management@asrt.space', 'ASRT Spaces - Password Reset');
        $mail->addReplyTo('no-reply@asrt.space', 'ASRT Spaces');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'ASRT Spaces - Password Reset OTP';
        
        // HTML email body
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); padding: 20px; text-align: center;'>
                <h1 style='color: white; margin: 0;'>ASRT Spaces</h1>
                <p style='color: white; margin: 5px 0 0 0;'>Password Reset Request</p>
            </div>
            <div style='padding: 30px; background: #f8fafc;'>
                <h2 style='color: #1e40af; margin-bottom: 20px;'>Password Reset OTP</h2>
                <p style='font-size: 16px; line-height: 1.5; margin-bottom: 20px;'>
                    You requested to reset your password. Use the following OTP to verify your identity:
                </p>
                <div style='text-align: center; margin: 30px 0;'>
                    <span style='font-size: 32px; font-weight: bold; color: #1e40af; background: white; padding: 15px 30px; border-radius: 8px; border: 2px solid #e2e8f0; letter-spacing: 5px;'>
                        {$otp}
                    </span>
                </div>
                <p style='color: #ef4444; font-weight: 600; text-align: center; margin: 20px 0;'>
                    This code expires in 5 minutes
                </p>
                <p style='font-size: 14px; color: #64748b; margin-top: 30px;'>
                    If you did not request this password reset, please ignore this email and ensure your account is secure.
                </p>
            </div>
            <div style='background: #e2e8f0; padding: 15px; text-align: center; font-size: 12px; color: #64748b;'>
                © " . date('Y') . " ASRT Spaces. All rights reserved.
            </div>
        </div>";
        
        // Plain text version
        $mail->AltBody = "ASRT Spaces - Password Reset\n\nYour OTP for password reset is: {$otp}\n\nThis code expires in 5 minutes.\n\nIf you did not request this password reset, please ignore this email.";

        // Send the email
        if ($mail->send()) {
            echo json_encode([
                'success' => true, 
                'expires_at' => $expires_at,
                'message' => 'OTP sent successfully to your email.'
            ]);
        } else {
            throw new Exception('Failed to send email');
        }
        
    } catch (Exception $e) {
        error_log("Mail sending failed: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send OTP email. Please try again.',
            'debug' => $mail->ErrorInfo ?? $e->getMessage()
        ]);
    }
    
} catch (Exception $e) {
    error_log("Database error in send_forgot_otp.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again later.'
    ]);
}
?>