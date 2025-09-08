<?php
require 'database/database.php'; // adjust path if needed
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['client_name'] ?? '');
    $email = trim($_POST['client_email'] ?? '');
    $phone = trim($_POST['client_phone'] ?? '');
    $message = trim($_POST['message_text'] ?? '');

    if ($name && $email && $message) {
        $db->insertFreeMessage($name, $email, $phone, $message);
        // Redirect with success
        header('Location: index.php?free_msg=sent');
        exit();
    } else {
        // Redirect with error
        header('Location: index.php?free_msg=fail');
        exit();
    }
} 
?>