<?php
// AJAX endpoint to check if an email is registered (for forgot password live validation)
session_start();
header('Content-Type: application/json');
require_once 'database/database.php';

if (!isset($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false, 'message' => 'Please enter a valid email.']);
    exit;
}

$email = trim($_POST['email']);
$db = new Database();
$user = $db->getUserByEmail($email);

if ($user) {
    echo json_encode(['exists' => true, 'message' => 'Email is registered.']);
} else {
    echo json_encode(['exists' => false, 'message' => 'Email not registered.']);
}
