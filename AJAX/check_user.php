<?php
// Require your single database file. Note the path goes up one level ('../').
require '../AJAX/database.php';
header('Content-Type: application/json');

// Create an instance of the Database class
$db = new Database();

// Default response
$response = ['exists' => false, 'message' => ''];

// Check for email validation request
if (isset($_POST['email'])) {
    $email = trim($_POST['email']);
    // Call the new, secure database method
    if ($db->checkClientCredentialExists('Client_Email', $email)) {
        $response = ['exists' => true, 'message' => 'Email already in use'];
    }
} 
// Check for username validation request
elseif (isset($_POST['username'])) {
    $username = trim($_POST['username']);
    // Call the new, secure database method
    if ($db->checkClientCredentialExists('C_username', $username)) {
        $response = ['exists' => true, 'message' => 'Username already in use'];
    }
}

// Send the JSON response back to the browser
echo json_encode($response);