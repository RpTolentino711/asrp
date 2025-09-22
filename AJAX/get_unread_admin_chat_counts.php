<?php
// Get unread admin message count for each invoice for a client
require_once '../database/database.php';

if (!isset($_POST['client_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing client_id']);
    exit;
}

$client_id = intval($_POST['client_id']);
$db = new Database();

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
