<?php
require_once '../database/database.php';

if (!isset($_POST['client_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing client_id']);
    exit;
}

$client_id = intval($_POST['client_id']);
$db = new Database();

// Mark all admin messages as read for this client's invoices
$sql = "UPDATE invoice_chat 
        SET is_read_client = 1 
        WHERE Sender_Type = 'admin' 
        AND is_read_client = 0 
        AND Invoice_ID IN (
            SELECT Invoice_ID 
            FROM invoices 
            WHERE Client_ID = ?
        )";

try {
    $result = $db->execute($sql, [$client_id]);
    echo json_encode(['success' => true, 'affected_rows' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>