<?php
session_start();
header('Content-Type: application/json');

require_once '../database/database.php';

if (!isset($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$client_id = $_SESSION['client_id'];
$db = new Database();

try {
    // Update only unread admin messages
    $sql = "UPDATE invoice_chat ic
            JOIN invoice i ON ic.Invoice_ID = i.Invoice_ID 
            SET ic.is_read_client = 1 
            WHERE i.Client_ID = ? AND ic.Sender_Type = 'admin' AND ic.is_read_client = 0";

    $success = $db->executeStatement($sql, [$client_id]);
    if ($success === false) {
        throw new Exception("Failed to update messages");
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error marking messages as read: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>