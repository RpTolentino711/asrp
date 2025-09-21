<?php
// update_profile.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database/database.php';

$response = ['success' => false, 'message' => 'Unknown error.'];

if (!isset($_SESSION['client_id'])) {
    $response['message'] = 'Not logged in.';
    echo json_encode($response);
    exit;
}

$db = new Database();
$pdo = $db->pdo ?? null;
if (!$pdo && method_exists($db, 'opencon')) {
    $pdo = $db->opencon();
}

$client_id = $_SESSION['client_id'];

// Get POST data
$fname = trim($_POST['fname'] ?? '');
$lname = trim($_POST['lname'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$username = trim($_POST['username'] ?? '');

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_new_password = $_POST['confirm_new_password'] ?? '';

// Validate profile fields
if (empty($fname) || empty($lname) || empty($email) || empty($phone) || empty($username)) {
    $response['message'] = 'All profile fields are required.';
    echo json_encode($response);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email format.';
    echo json_encode($response);
    exit;
}
if (!preg_match('/^\d{11}$/', $phone)) {
    $response['message'] = 'Phone number must be exactly 11 digits.';
    echo json_encode($response);
    exit;
}

// Check for duplicate username/email (excluding self)
$stmt = $pdo->prepare('SELECT C_id FROM client WHERE (C_username = ? OR Client_Email = ?) AND C_id != ?');
$stmt->execute([$username, $email, $client_id]);
if ($stmt->rowCount() > 0) {
    $response['message'] = 'Username or email already in use.';
    echo json_encode($response);
    exit;
}

// Update profile info
$stmt = $pdo->prepare('UPDATE client SET Client_fn=?, Client_ln=?, Client_Email=?, Client_Contact=?, C_username=? WHERE C_id=?');
$ok = $stmt->execute([$fname, $lname, $email, $phone, $username, $client_id]);

if (!$ok) {
    $response['message'] = 'Failed to update profile.';
    echo json_encode($response);
    exit;
}

// Handle password change if requested
if ($current_password || $new_password || $confirm_new_password) {
    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $response['message'] = 'All password fields are required.';
        echo json_encode($response);
        exit;
    }
    if ($new_password !== $confirm_new_password) {
        $response['message'] = 'New passwords do not match.';
        echo json_encode($response);
        exit;
    }
    // Get current hash
    $stmt = $pdo->prepare('SELECT C_password FROM client WHERE C_id=?');
    $stmt->execute([$client_id]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current_password, $row['C_password'])) {
        $response['message'] = 'Current password is incorrect.';
        echo json_encode($response);
        exit;
    }
    // Update password
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE client SET C_password=? WHERE C_id=?');
    $stmt->execute([$new_hash, $client_id]);
}

$response['success'] = true;
$response['message'] = 'Profile updated successfully.';
echo json_encode($response);
