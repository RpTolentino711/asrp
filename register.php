<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database/database.php';
require_once __DIR__ . '/class.phpmailer.php';
require_once __DIR__ . '/class.smtp.php';

$db = new Database();
$pdo = $db->pdo ?? null;
if (!$pdo && method_exists($db, 'opencon')) {
    $pdo = $db->opencon();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = isset($_POST['fname']) ? trim($_POST['fname']) : '';
    $lname = isset($_POST['lname']) ? trim($_POST['lname']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (empty($fname) || empty($lname) || empty($email) || empty($phone) || empty($username) || empty($password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }
    if (!preg_match('/^\d{11}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number must be exactly 11 digits and numbers only.']);
        exit;
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[\W_]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter and one special character.']);
        exit;
    }

    // Check for duplicate username/email
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT C_id FROM client WHERE C_username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT C_id FROM client WHERE Client_Email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email address is already registered.']);
            exit;
        }
    }

    // Store pending registration in session
    $_SESSION['pending_registration'] = [
        'fname' => $fname,
        'lname' => $lname,
        'email' => $email,
        'phone' => $phone,
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Generate OTP + session state
    $otp = random_int(100000, 999999);
    $_SESSION['otp'] = (string)$otp;
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_expires'] = time() + 5 * 60; // 5 minutes
    $_SESSION['otp_attempts'] = 0;
    unset($_SESSION['otp_locked_until']);

    // Send email via PHPMailer
    $mail = new PHPMailer;
    $mail->CharSet    = 'UTF-8';
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->Port       = 587;
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = 'tls';

    // Log SMTP debug to Apache error.log
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function ($str, $level) {
        error_log("PHPMailer [$level]: $str");
    };
    $mail->Timeout = 20;

    // Force TLS 1.2
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
            'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ],
    ];

    // Gmail credentials - CHANGED HERE
    $mail->Username = 'ahmadpaguta2005@gmail.com';
    $mail->Password = 'unwr kdad ejcd rysq';
    $mail->setFrom($mail->Username, 'ASRP Registration');
    $mail->addReplyTo('no-reply@asrp.local', 'ASRP Registration');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Your verification code";
    $safeName = htmlspecialchars($fname, ENT_QUOTES, 'UTF-8');
    $mail->Body    = "<p>Hi {$safeName},</p>
                      <p>Your OTP code is <b>{$otp}</b>.</p>
                      <p>This code expires in 5 minutes.</p>
                      <p>If you did not request this email, please ignore it.</p>
                      <p>See you soon!</p>
                      <p>Regards,<br>ASRP Registration</p>";
    $mail->AltBody = "Your OTP code is {$otp}. It expires in 5 minutes.";

    if (!$mail->send()) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send verification email. Please try again later.',
            'error'   => $mail->ErrorInfo,
        ]);
    } else {
        echo json_encode([
            'success'               => true,
            'message'               => 'Registration initiated, OTP sent to your email.',
            'pending_verification'  => true,
            'email'                 => $email,
            'expires_at'            => $_SESSION['otp_expires']
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}