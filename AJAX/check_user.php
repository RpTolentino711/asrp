<?php
// Fix the require path - go up one level to reach database folder
require '../database/database.php';
header('Content-Type: application/json');

// Create an instance of the Database class
$db = new Database();

// Default response
$response = ['exists' => false, 'message' => ''];

try {
    // Check for email validation request
    if (isset($_POST['email'])) {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $response = ['exists' => false, 'message' => ''];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response = ['exists' => true, 'message' => 'Invalid email format'];
        } else {
            // Call the database method
            if ($db->checkClientCredentialExists('Client_Email', $email)) {
                $response = ['exists' => true, 'message' => 'Email already in use'];
            } else {
                $response = ['exists' => false, 'message' => 'Email available'];
            }
        }
    } 
    // Check for username validation request
    elseif (isset($_POST['username'])) {
        $username = trim($_POST['username']);
        
        if (empty($username)) {
            $response = ['exists' => false, 'message' => ''];
        } elseif (strlen($username) < 3) {
            $response = ['exists' => true, 'message' => 'Username must be at least 3 characters'];
        } elseif (strlen($username) > 20) {
            $response = ['exists' => true, 'message' => 'Username must be 20 characters or less'];
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $response = ['exists' => true, 'message' => 'Username can only contain letters, numbers, and underscores'];
        } else {
            // Call the database method
            if ($db->checkClientCredentialExists('C_username', $username)) {
                $response = ['exists' => true, 'message' => 'Username already in use'];
            } else {
                $response = ['exists' => false, 'message' => 'Username available'];
            }
        }
    }
} catch (Exception $e) {
    error_log("Validation error: " . $e->getMessage());
    $response = ['exists' => false, 'message' => 'Validation service temporarily unavailable'];
}

// Send the JSON response back to the browser
echo json_encode($response);