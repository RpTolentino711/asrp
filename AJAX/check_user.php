<?php
require '../AJAX/database.php';
header('Content-Type: application/json');

// Create an instance of the Database class
$db = new Database();

// Default response
$response = ['exists' => false, 'message' => '', 'valid' => true];

try {
    // Check for email validation request
    if (isset($_POST['email'])) {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $response = ['exists' => false, 'message' => '', 'valid' => true]; // Empty is OK while typing
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response = ['exists' => false, 'message' => 'Invalid email format', 'valid' => false];
        } else {
            // Check if email exists in database
            if ($db->checkClientCredentialExists('Client_Email', $email)) {
                $response = ['exists' => true, 'message' => 'Email already in use', 'valid' => false];
            } else {
                $response = ['exists' => false, 'message' => 'Email available', 'valid' => true];
            }
        }
    } 
    // Check for username validation request
    elseif (isset($_POST['username'])) {
        $username = trim($_POST['username']);
        
        if (empty($username)) {
            $response = ['exists' => false, 'message' => '', 'valid' => true]; // Empty is OK while typing
        } elseif (strlen($username) < 3 && strlen($username) > 0) {
            $response = ['exists' => false, 'message' => 'Username must be at least 3 characters', 'valid' => false];
        } elseif (strlen($username) > 20) {
            $response = ['exists' => false, 'message' => 'Username must be 20 characters or less', 'valid' => false];
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $response = ['exists' => false, 'message' => 'Username can only contain letters, numbers, and underscores', 'valid' => false];
        } else {
            // Check if username exists in database
            if ($db->checkClientCredentialExists('C_username', $username)) {
                $response = ['exists' => true, 'message' => 'Username already in use', 'valid' => false];
            } else {
                $response = ['exists' => false, 'message' => 'Username available', 'valid' => true];
            }
        }
    } else {
        $response = ['exists' => false, 'message' => 'No validation parameter provided', 'valid' => false];
    }

} catch (Exception $e) {
    error_log("Live validation error: " . $e->getMessage());
    $response = ['exists' => false, 'message' => 'Validation service temporarily unavailable', 'valid' => false];
}

echo json_encode($response);
?>