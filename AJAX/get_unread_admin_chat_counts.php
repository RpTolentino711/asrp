<?php
// Get unread admin message count for each invoice for a client
require_once '../database/database.php';

session_start(); // Add session start

// Use session client_id instead of POST for security
if (!isset($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$client_id = intval($_SESSION['client_id']);
$db = new Database();

try {
    // Get all invoice IDs for this client
    $invoices = $db->getClientInvoiceHistory($client_id);
    $results = [];
    
    foreach ($invoices as $inv) {
        $invoice_id = $inv['Invoice_ID'];
        // Count unread admin messages for this invoice
        $sql = "SELECT COUNT(*) as unread_count FROM invoice_chat WHERE Invoice_ID = ? AND Sender_Type = 'admin' AND is_read_client = 0";
        $row = $db->getRow($sql, [$invoice_id]);
        $results[$invoice_id] = intval($row['unread_count'] ?? 0);
    }
    
    echo json_encode($results);
    
} catch (Exception $e) {
    error_log("Error in get_unread_admin_chat_counts: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>