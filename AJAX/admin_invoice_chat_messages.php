<?php
require_once '../database/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || !isset($_GET['invoice_id'])) {
    echo json_encode(['error' => 'Unauthorized or missing invoice_id']);
    exit;
}

$db = new Database();
$invoice_id = intval($_GET['invoice_id']);

// Optional: check if the invoice exists
$invoice = $db->getInvoiceById($invoice_id);
if (!$invoice) {
    echo json_encode(['error' => 'Invoice not found']);
    exit;
}

$chat_messages = $db->getInvoiceChatMessagesForAdmin($invoice_id);
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