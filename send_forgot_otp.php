<?php
// Disable HTML error output and enable JSON-only responses
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

try {
    // Test database connection first
    require_once 'database/database.php';
    $db = new Database();
    
    // Test email input
    if (empty($_POST['email'])) {
        echo json_encode(['success' => false, 'message' => 'Email is required.']);
        exit;
    }
    
    $email = trim($_POST['email']);
    
    // Test database query
    $stmt = $db->getConnection()->prepare("SELECT Client_ID, Client_Email FROM client WHERE Client_Email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No account found with that email.']);
        exit;
    }
    
    // For now, just return success without sending email
    echo json_encode([
        'success' => true, 
        'message' => 'Test successful - user found',
        'expires_at' => time() + 300
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
exit;
?>