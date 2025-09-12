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

// Use the correct method to check if the invoice exists
$invoice = $db->getSingleInvoiceForDisplay($invoice_id);
if (!$invoice) {
    echo json_encode(['error' => 'Invoice not found']);
    exit;
}

// Use the same chat fetch method as client (shows all messages)
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