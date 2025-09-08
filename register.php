<?php
// Require your single database file. Adjust path if needed.
require 'database/database.php';
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
    // Ensure phone number is exactly 11 digits and only numeric
    if (!preg_match('/^\d{11}$/', $phone)) {
        $errors[] = "Phone number must be exactly 11 digits and numbers only.";
    }
    // Password must contain at least one uppercase and one special character
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
    header('Location: index.php'); // Or a dedicated register page
    exit();
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        if ($db->registerClient($fname, $lname, $email, $phone, $username, $hashed_password)) {
            $_SESSION['register_success'] = "Registration successful! You can now log in.";
            unset($_SESSION['register_backup']);
            unset($_SESSION['register_duplicate']);
            header('Location: index.php');
            exit();
        } else {
            $_SESSION['register_error'] = "An unexpected error occurred. Please try again later.";
            header('Location: index.php');
            exit();
        }
    }
} else {
    header('Location: index.php');
    exit();
}
?>