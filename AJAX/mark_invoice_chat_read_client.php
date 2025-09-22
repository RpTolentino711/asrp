<?php
// Mark all admin messages as read for client for a given invoice
require_once '../database/database.php';

if (!isset($_POST['invoice_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing invoice_id']);
    exit;
}

$invoice_id = intval($_POST['invoice_id']);

$db = new Database();
$sql = "UPDATE invoice_chat SET is_read_client = 1 WHERE Invoice_ID = ? AND Sender_Type = 'admin' AND is_read_client = 0";
$success = $db->executeStatement($sql, [$invoice_id]);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update read status']);
}
