<?php
session_start();
require_once __DIR__ . '/database/database.php';

$db = new Database();
$pdo = $db->pdo ?? null;
if (!$pdo && method_exists($db, 'opencon')) {
    $pdo = $db->opencon();
}

if (!isset($_SESSION['pending_registration'], $_SESSION['otp'], $_SESSION['otp_expires'])) {
    $_SESSION['register_error'] = "Session expired. Please register again.";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_otp = trim($_POST['otp']);

    if (time() > $_SESSION['otp_expires']) {
        $_SESSION['register_error'] = "OTP expired. Please register again.";
        unset($_SESSION['pending_registration']);
        header("Location: index.php");
        exit();
    }

    if ($input_otp !== $_SESSION['otp']) {
        $_SESSION['register_error'] = "Invalid OTP.";
        header("Location: verify_otp_page.php");
        exit();
    }

    $user = $_SESSION['pending_registration'];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO client (Client_fn, Client_ln, Client_Email, Client_Phone, C_username, C_password, Status)
            VALUES (?, ?, ?, ?, ?, ?, 'Active')
        ");
        $stmt->execute([
            $user['fname'],
            $user['lname'],
            $user['email'],
            $user['phone'],
            $user['username'],
            $user['password']
        ]);

        unset($_SESSION['pending_registration'], $_SESSION['otp'], $_SESSION['otp_expires']);
        $_SESSION['register_success'] = "Registration successful! You can now log in.";
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['register_error'] = "Database error: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
