<?php
session_start();
header('Content-Type: application/json');

// Disable error display to prevent breaking JSON but log errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'class.phpmailer.php';
require_once 'database/database.php';

// Debug logging function
function debugLog($message, $data = null) {
    $logMessage = "[FORGOT_OTP] " . $message;
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data);
    }
    error_log($logMessage);
}

try {
    debugLog("Script started", ["POST" => $_POST, "SESSION" => $_SESSION]);
    
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        debugLog("Invalid request method", ["method" => $_SERVER['REQUEST_METHOD']]);
        throw new Exception('Invalid request method.');
    }

    // Validate email input
    if (empty($_POST['email'])) {
        debugLog("Email is empty", ["POST" => $_POST]);
        throw new Exception('Email is required.');
    }

    $email = trim(strtolower($_POST['email'])); // Convert to lowercase for consistency
    debugLog("Processing email", ["email" => $email]);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        debugLog("Invalid email format", ["email" => $email]);
        throw new Exception('Invalid email format.');
    }

    // Enhanced rate limiting with IP tracking
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip_key = "forgot_otp_ip_{$client_ip}";
    
    if (!isset($_SESSION[$ip_key])) {
        $_SESSION[$ip_key] = [];
    }
    
    // Clean old IP attempts (older than 1 hour)
    $_SESSION[$ip_key] = array_filter($_SESSION[$ip_key], function($timestamp) {
        return (time() - $timestamp) < 3600;
    });
    
    // Check IP rate limit (max 3 attempts per hour)
    if (count($_SESSION[$ip_key]) >= 3) {
        debugLog("IP rate limit exceeded", ["ip" => $client_ip, "attempts" => count($_SESSION[$ip_key])]);
        throw new Exception('Too many password reset attempts. Please try again later.');
    }

    // Check session cooldown
    if (isset($_SESSION['last_forgot_otp_sent']) && (time() - $_SESSION['last_forgot_otp_sent']) < 60) {
        $wait = 60 - (time() - $_SESSION['last_forgot_otp_sent']);
        throw new Exception("Please wait {$wait} seconds before requesting a new OTP.");
    }

    // Use your existing database class
    $db = new Database();
    debugLog("Database initialized");
    try {
        $user = $db->getUserByEmail($email);
    } catch (Exception $e) {
        debugLog("Database error", ["error" => $e->getMessage()]);
        throw new Exception('Database error: ' . $e->getMessage());
    }
    if (!$user) {
        debugLog("No user found", ["email" => $email]);
        throw new Exception('No account found with that email address.');
    }
    debugLog("User found", ["user_exists" => true, "user_data" => $user]);

    // Update rate limiting
    $_SESSION['last_forgot_otp_sent'] = time();
    $_SESSION[$ip_key][] = time();

    // Generate secure OTP
    try {
        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        // Fallback to mt_rand if random_int fails
        $otp = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    $expires_at = time() + (5 * 60); // 5 minutes
    debugLog("OTP generated", ["otp_length" => strlen($otp), "expires_at" => $expires_at]);

    // Clear any existing forgot password session data
    unset($_SESSION['forgot_otp']);
    unset($_SESSION['forgot_otp_email']);
    unset($_SESSION['forgot_otp_expires']);
    unset($_SESSION['forgot_otp_verified']);
    unset($_SESSION['forgot_otp_attempts']);
    unset($_SESSION['forgot_otp_locked_until']);

    // Store OTP data in session
    $_SESSION['forgot_otp'] = $otp;
    $_SESSION['forgot_otp_email'] = $email;
    $_SESSION['forgot_otp_expires'] = $expires_at;
    $_SESSION['forgot_otp_attempts'] = 0;
    
    debugLog("OTP stored in session");

    // Initialize PHPMailer with enhanced error handling
    $mail = new PHPMailer(true); // Enable exceptions
    $mail->CharSet = 'UTF-8';
    $debugMailLog = [];
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->Port = 587;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        // Enhanced debugging for development
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level) use (&$debugMailLog) {
            $debugMailLog[] = "PHPMailer [$level]: $str";
            debugLog("PHPMailer [$level]", $str);
        };
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
        debugLog("SMTP configured");
        // Email setup
        $mail->setFrom('management@asrt.space', 'ASRT Spaces');
        $mail->addReplyTo('management@asrt.space', 'ASRT Spaces');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'ASRT Spaces - Password Reset Code';
        // Get user's name with fallback
        $safeName = 'User';
        if (isset($user['Client_fn']) && !empty($user['Client_fn'])) {
            $safeName = htmlspecialchars($user['Client_fn'], ENT_QUOTES, 'UTF-8');
        } elseif (isset($user['fname']) && !empty($user['fname'])) {
            $safeName = htmlspecialchars($user['fname'], ENT_QUOTES, 'UTF-8');
        }
        debugLog("Email content prepared", ["recipient" => $email, "name" => $safeName]);
        // Enhanced email body
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white;'>
                <div style='background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); padding: 30px 20px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 28px; font-weight: bold;'>ASRT Spaces</h1>
                    <p style='color: white; margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Password Reset Request</p>
                </div>
                <div style='padding: 40px 30px; background: white;'>
                    <p style='font-size: 16px; margin-bottom: 20px; color: #333;'>Hi {$safeName},</p>
                    
                    <p style='font-size: 16px; margin-bottom: 30px; color: #333; line-height: 1.5;'>
                        You requested to reset your password for your ASRT Spaces account. 
                        Use the verification code below to proceed:
                    </p>
                    
                    <div style='text-align: center; margin: 40px 0;'>
                        <div style='display: inline-block; background: #f8fafc; border: 2px dashed #1e40af; border-radius: 8px; padding: 20px 30px;'>
                            <span style='font-size: 36px; font-weight: bold; color: #1e40af; letter-spacing: 4px; font-family: monospace;'>
                                {$otp}
                            </span>
                        </div>
                    </div>
                    
                    <div style='background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 15px; margin: 30px 0; text-align: center;'>
                        <p style='color: #dc2626; font-weight: 600; margin: 0; font-size: 14px;'>
                            ⚠️ This code expires in 5 minutes
                        </p>
                    </div>
                    
                    <p style='font-size: 14px; color: #666; margin-top: 30px; line-height: 1.5;'>
                        If you did not request this password reset, you can safely ignore this email. 
                        Your password will remain unchanged.
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

        // Plain text alternative
        $mail->AltBody = "Hi {$safeName},\n\nYou requested to reset your password for your ASRT Spaces account.\n\nYour verification code is: {$otp}\n\nThis code expires in 5 minutes.\n\nIf you did not request this password reset, you can safely ignore this email.\n\nBest regards,\nASRT Spaces Team";

        debugLog("Attempting to send email");
        // Send the email
        if ($mail->send()) {
            debugLog("Email sent successfully");
            echo json_encode([
                'success' => true,
                'message' => 'Password reset code has been sent to your email.',
                'expires_at' => $expires_at,
                'debug' => $debugMailLog
            ]);
        } else {
            debugLog("PHPMailer send failed", ["error" => $mail->ErrorInfo, "debug" => $debugMailLog]);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send email: ' . $mail->ErrorInfo,
                'debug' => $debugMailLog
            ]);
        }
    } catch (Exception $e) {
        debugLog("PHPMailer exception", ["error" => $e->getMessage(), "debug" => $debugMailLog]);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send OTP email: ' . $e->getMessage(),
            'debug' => $debugMailLog
        ]);
    }

} catch (Exception $e) {
    debugLog("Script exception", ["error" => $e->getMessage(), "POST" => $_POST, "SESSION" => $_SESSION]);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => isset($debugMailLog) ? $debugMailLog : null,
        'post' => $_POST,
        'session' => $_SESSION
    ]);
}
?>