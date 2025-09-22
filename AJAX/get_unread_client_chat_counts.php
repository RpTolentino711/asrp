<?php
// Get unread client message count for each invoice for admin
require_once '../database/database.php';

if (!isset($_POST['invoice_ids'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$invoice_ids = $_POST['invoice_ids'];
if (!is_array($invoice_ids)) {
    $invoice_ids = json_decode($invoice_ids, true);
}

$db = new Database();
$results = [];
foreach ($invoice_ids as $invoice_id) {
    $sql = "SELECT COUNT(*) as unread_count FROM invoice_chat WHERE Invoice_ID = ? AND Sender_Type = 'client' AND is_read_admin = 0";
    $row = $db->getRow($sql, [$invoice_id]);
    $results[$invoice_id] = intval($row['unread_count'] ?? 0);
}
echo json_encode($results);
