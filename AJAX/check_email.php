<?php
require_once '../database/database.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if email parameter is provided
if (!isset($_POST['email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email is required']);
    exit;
}

$email = trim($_POST['email']);

// Basic email validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'exists' => false,
        'valid' => false,
        'message' => 'Please enter a valid email address.'
    ]);
    exit;
}

try {
    $db = new Database();
    
    // Check if email exists in the client table
    $sql = "SELECT Client_ID, Client_fn, Client_ln, Status FROM client WHERE Client_Email = ? LIMIT 1";
    $result = $db->getRow($sql, [$email]);
    
    if ($result) {
        // Email exists - check if account is active
        if ($result['Status'] === 'Active') {
            echo json_encode([
                'exists' => true,
                'valid' => true,
                'active' => true,
                'message' => 'Email found. We will send an OTP to this address.',
                'client_name' => trim($result['Client_fn'] . ' ' . $result['Client_ln'])
            ]);
        } else {
            echo json_encode([
                'exists' => true,
                'valid' => false,
                'active' => false,
                'message' => 'This account is inactive. Please contact support.'
            ]);
        }
    } else {
        // Email does not exist
        echo json_encode([
            'exists' => false,
            'valid' => false,
            'message' => 'No account found with this email address.'
        ]);
    }
    
} catch (Exception $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'message' => 'Unable to verify email at this time. Please try again.'
    ]);
    
    // Log the actual error for debugging (don't send to client)
    error_log("Email check error: " . $e->getMessage());
}
?>