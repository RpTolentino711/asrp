<?php
// Mark all messages as read for admin for a given invoice (AJAX endpoint)
require_once '../database/database.php';

if (!isset($_POST['invoice_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$invoice_id = intval($_POST['invoice_id']);
$db = new Database();
$db->executeStatement('UPDATE invoice_chat SET is_read_admin = 1 WHERE Invoice_ID = ?', [$invoice_id]);
echo json_encode(['success' => true]);
