<?php
// Require your single database file. Adjust path if needed.
require 'database/database.php';
session_start();

// Create an instance of the Database class
$db = new Database();

// Redirect if already logged in
if (isset($_SESSION['client_id'])) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $user = $db->getClientByUsername($username);

    if (!$user) {
        $_SESSION['login_error'] = "Username not found.";
        header("Location: index.php");
        exit();
    }

    if (!password_verify($password, $user['C_password'])) {
        $_SESSION['login_error'] = "Incorrect password.";
        header("Location: index.php");
        exit();
    }

    if (strtolower($user['Status']) !== 'active') {
        $_SESSION['login_error'] = "Account inactive. Please contact admin.";
        header("Location: index.php");
        exit();
    }

    session_regenerate_id(true);
    $_SESSION['client_id'] = $user['Client_ID'];
    $_SESSION['client_fn'] = $user['Client_fn'];
    $_SESSION['C_username'] = $user['C_username'];
    $_SESSION['login_success'] = true;
    header("Location: dashboard.php");
    exit();
}
?>