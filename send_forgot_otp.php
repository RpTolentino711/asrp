<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/class.phpmailer.php';
require_once __DIR__ . '/class.smtp.php';
require_once 'database/database.php';

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

// --- Prevent spamming: 60s cooldown ---
if (isset($_SESSION['last_forgot_otp_sent']) && (time() - $_SESSION['last_forgot_otp_sent']) < 60) {
    $wait = 60 - (time() - $_SESSION['last_forgot_otp_sent']);
    echo json_encode([
        'success' => false,
        'message' => "Please wait {$wait} seconds before requesting a new OTP."
    ]);
    exit;
}

try {
    $db = new Database();
    
    // Check if user exists with this email using correct column name
    $stmt = $db->getConnection()->prepare("SELECT Client_ID, Client_Email, Client_fn FROM client WHERE Client_Email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No account found with that email address.']);
        exit;
    }

    $_SESSION['last_forgot_otp_sent'] = time();

    // --- Generate new OTP ---
    $otp = random_int(100000, 999999);
    $expires_at = time() + (5 * 60); // 5 minutes

    // Clear any existing forgot password session data
    unset($_SESSION['forgot_otp']);
    unset($_SESSION['forgot_otp_email']);
    unset($_SESSION['forgot_otp_expires']);
    unset($_SESSION['forgot_otp_verified']);
    unset($_SESSION['forgot_otp_attempts']);
    unset($_SESSION['forgot_otp_locked_until']);

    // Store OTP data in session
    $_SESSION['forgot_otp'] = (string)$otp;
    $_SESSION['forgot_otp_email'] = $email;
    $_SESSION['forgot_otp_expires'] = $expires_at;
    $_SESSION['forgot_otp_attempts'] = 0;

    // --- Send OTP via PHPMailer (same config as working registration) ---
    $mail = new PHPMailer;
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';

    // Debug log to PHP error_log (same as working version)
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function ($str, $level) {
        error_log("PHPMailer [$level]: $str");
    };
    $mail->Timeout = 20;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ],
    ];

    // Same credentials as working version
    $mail->Username = 'management@asrt.space';
    $mail->Password = '@Pogilameg10';

    $mail->setFrom($mail->Username, 'ASRT Spaces Password Reset');
    $mail->addReplyTo('no-reply@asrt.space', 'ASRT Spaces Password Reset');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Your ASRT Spaces Password Reset Code";
    
    $safeName = htmlspecialchars($user['Client_fn'] ?? 'User', ENT_QUOTES, 'UTF-8');
    $mail->Body = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); padding: 20px; text-align: center;'>
                        <h1 style='color: white; margin: 0;'>ASRT Spaces</h1>
                        <p style='color: white; margin: 5px 0 0 0;'>Password Reset Request</p>
                    </div>
                    <div style='padding: 30px; background: #f8fafc;'>
                        <p>Hi {$safeName},</p>
                        <p>You requested to reset your password. Your verification code is:</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <span style='font-size: 32px; font-weight: bold; color: #1e40af; background: white; padding: 15px 30px; border-radius: 8px; border: 2px solid #e2e8f0; letter-spacing: 5px;'>
                                {$otp}
                            </span>
                        </div>
                        <p style='color: #ef4444; font-weight: 600; text-align: center;'><strong>This code expires in 5 minutes.</strong></p>
                        <p>If you did not request this password reset, please ignore this email.</p>
                        <p>Regards,<br>ASRT Spaces Team</p>
                    </div>
                </div>";
    
    $mail->AltBody = "Hi {$safeName}, Your password reset OTP code is {$otp}. This code expires in 5 minutes. If you did not request this, please ignore this email.";

    if (!$mail->send()) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send OTP email. Please try again.',
            'error' => $mail->ErrorInfo,
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Password reset OTP has been sent to your email.',
            'expires_at' => $expires_at
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in send_forgot_otp.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again.'
    ]);
} catch (Exception $e) {
    error_log("Error in send_forgot_otp.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
exit;
?>