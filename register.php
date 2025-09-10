<?php
require 'database/database.php';
require_once __DIR__ . '/class.phpmailer.php';
session_start();

$db = new Database();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Backup user input except passwords
    $_SESSION['register_backup'] = [
        'fname' => $fname,
        'lname' => $lname,
        'email' => $email,
        'phone' => $phone,
        'username' => $username
    ];

    $errors = [];
    if (empty($fname) || empty($lname) || empty($email) || empty($phone) || empty($username) || empty($password)) {
        $errors[] = "All fields are required.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (!preg_match('/^\d{11}$/', $phone)) {
        $errors[] = "Phone number must be exactly 11 digits and numbers only.";
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[\W_]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter and one special character.";
    }
    if ($db->checkClientCredentialExists('C_username', $username)) {
        $errors[] = "Username already exists.";
        $_SESSION['register_duplicate'] = 'username';
    }
    if ($db->checkClientCredentialExists('Client_Email', $email)) {
        $errors[] = "Email address is already registered.";
        $_SESSION['register_duplicate'] = 'email';
    }

    if (!empty($errors)) {
        $_SESSION['register_error'] = implode(' ', $errors);
        header('Location: index.php');
        exit();
    } else {
        // Store pending registration (instead of inserting now)
        $_SESSION['pending_registration'] = [
            'fname' => $fname,
            'lname' => $lname,
            'email' => $email,
            'phone' => $phone,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT)
        ];

        // Generate OTP
        $otp = random_int(100000, 999999);
        $_SESSION['otp'] = (string)$otp;
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_expires'] = time() + 300; // 5 mins
        $_SESSION['otp_attempts'] = 0;

        // Send OTP email
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'ahmadpaguta2005@gmail.com';
        $mail->Password = 'unwr kdad ejcd rysq'; // App password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom($mail->Username, 'ASRP Registration');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code';
        $mail->Body    = "Hello {$fname},<br><br>Your OTP is <b>{$otp}</b>. It expires in 5 minutes.";
        $mail->AltBody = "Your OTP is {$otp}. It expires in 5 minutes.";

        if (!$mail->send()) {
            $_SESSION['register_error'] = "Failed to send OTP. Please try again.";
            header('Location: index.php');
            exit();
        } else {
            // Redirect to OTP page
            header('Location: verify_otp_page.php');
            exit();
        }
    }
} else {
    header('Location: index.php');
    exit();
}
