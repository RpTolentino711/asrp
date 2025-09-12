<?php
require_once '../database/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['client_id']) || !isset($_GET['invoice_id'])) {
    echo json_encode(['error' => 'Unauthorized or missing invoice_id']);
    exit;
}

$db = new Database();
$invoice_id = intval($_GET['invoice_id']);
$client_id = $_SESSION['client_id'];

// Optional: check if the invoice belongs to the client
$invoices = $db->getClientInvoiceHistory($client_id);
$invoice_ids = array_column($invoices, 'Invoice_ID');
if (!in_array($invoice_id, $invoice_ids)) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$chat_messages = $db->getInvoiceChatMessagesForClient($invoice_id);
$result = [];
foreach ($chat_messages as $msg) {
    $result[] = [
        'Sender_Type' => $msg['Sender_Type'],
        'SenderName' => $msg['SenderName'] ?? '',
        'Message' => $msg['Message'],
        'Image_Path' => $msg['Image_Path'],
        'Created_At' => $msg['Created_At']
    ];
}
echo json_encode($result);