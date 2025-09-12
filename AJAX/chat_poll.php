<?php
require '../database/database.php';
session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$client_id = $_SESSION['client_id'];
$db = new Database();

// Check if client has any active rental units
$hasActiveUnit = $db->checkClientHasActiveUnit($client_id);
if (!$hasActiveUnit) {
    echo json_encode(['success' => false, 'error' => 'No active rental unit']);
    exit;
}

// Get invoice_id and last_message_id from GET parameters
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
$last_message_id = isset($_GET['last_message_id']) ? intval($_GET['last_message_id']) : 0;

if (!$invoice_id) {
    echo json_encode(['success' => false, 'error' => 'Missing invoice_id']);
    exit;
}

// Fetch new messages (ID > last_message_id)
$new_messages = $db->getInvoiceChatMessagesForClient($invoice_id, $last_message_id);

// Check admin typing status (simulate or fetch from DB/other mechanism)
// For real-time typing, you might have a table or cache to track typing status.
// Here's a stub:
$admin_typing = false;
if (method_exists($db, 'isAdminTypingForInvoice')) {
    $admin_typing = $db->isAdminTypingForInvoice($invoice_id);
}

// Prepare response
echo json_encode([
    'success' => true,
    'messages' => $new_messages,
    'typing' => $admin_typing
]);
exit;