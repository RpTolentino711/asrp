<?php
session_start();
require_once '../database/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['client_id'])) {
    $client_id = $_SESSION['client_id'];
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
    exit;
}

echo json_encode([]);
?>