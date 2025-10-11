<?php
require '../database/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Enable CORS for all origins
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$db = new Database();

try {
    // Get client_id from POST or GET request
    $client_id = null;
    
    if (isset($_POST['client_id']) && !empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
    } elseif (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
        $client_id = intval($_GET['client_id']);
    }
    
    if (!$client_id || $client_id <= 0) {
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid client ID',
            'debug' => ['received_client_id' => $client_id]
        ]);
        exit();
    }

    // Get recently paid invoices (within the last 5 minutes)
    $five_minutes_ago = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    $paid_invoices = $db->executeQuery("
        SELECT i.Invoice_ID, i.Space_ID, i.InvoiceDate, i.EndDate, 
               s.Name as UnitName, i.Client_ID,
               (SELECT Invoice_ID FROM invoice 
                WHERE Client_ID = i.Client_ID 
                AND Space_ID = i.Space_ID 
                AND Flow_Status = 'new' 
                ORDER BY InvoiceDate DESC LIMIT 1) as NextInvoiceId,
               (SELECT EndDate FROM invoice 
                WHERE Client_ID = i.Client_ID 
                AND Space_ID = i.Space_ID 
                AND Flow_Status = 'new' 
                ORDER BY InvoiceDate DESC LIMIT 1) as NextDueDate
        FROM invoice i
        JOIN space s ON i.Space_ID = s.Space_ID
        WHERE i.Client_ID = ? 
        AND i.Status = 'paid' 
        AND i.Flow_Status = 'done'
        AND i.Created_At >= ?
        ORDER BY i.Created_At DESC
    ", [$client_id, $five_minutes_ago]);

    // Get invoices that need payment reminders (due in 3 days or less)
    $three_days_from_now = date('Y-m-d', strtotime('+3 days'));
    
    $upcoming_payments = $db->executeQuery("
        SELECT i.Invoice_ID, i.Space_ID, i.InvoiceDate, i.EndDate, 
               s.Name as UnitName, i.InvoiceTotal,
               DATEDIFF(i.EndDate, CURDATE()) as DaysUntilDue
        FROM invoice i
        JOIN space s ON i.Space_ID = s.Space_ID
        WHERE i.Client_ID = ? 
        AND i.Status = 'unpaid' 
        AND i.Flow_Status = 'new'
        AND i.EndDate <= ?
        AND i.EndDate >= CURDATE()
        ORDER BY i.EndDate ASC
    ", [$client_id, $three_days_from_now]);

    echo json_encode([
        'success' => true,
        'paid_invoices' => $paid_invoices ?: [],
        'upcoming_payments' => $upcoming_payments ?: [],
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'client_id' => $client_id,
            'paid_count' => count($paid_invoices ?: []),
            'upcoming_count' => count($upcoming_payments ?: [])
        ]
    ]);

} catch (Exception $e) {
    error_log("Payment status check error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>