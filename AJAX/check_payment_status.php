<?php
require 'database/database.php';
session_start();

header('Content-Type: application/json');

// Check if client is authenticated
if (!isset($_SESSION['client_id']) && !isset($_POST['client_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : $_SESSION['client_id'];
$db = new Database();

try {
    // Check if there are any recently paid invoices that should trigger the popup
    $sql = "SELECT i.Invoice_ID, i.InvoiceDate, i.EndDate, i.Status, i.Flow_Status, 
                   s.Name as SpaceName,
                   DATE_FORMAT(i.InvoiceDate, '%M %Y') as PaidMonth,
                   DATE_FORMAT(i.EndDate, '%M %d, %Y') as NextDueDate
            FROM invoice i
            INNER JOIN space s ON i.Space_ID = s.Space_ID
            WHERE i.Client_ID = ? 
            AND i.Status = 'paid' 
            AND i.Flow_Status = 'done'
            AND i.InvoiceDate >= DATE_SUB(NOW(), INTERVAL 7 DAY) -- Show popup for payments in last 7 days
            ORDER BY i.InvoiceDate DESC 
            LIMIT 1";
    
    $stmt = $db->pdo->prepare($sql);
    $stmt->execute([$client_id]);
    $recent_payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($recent_payment) {
        // Check if we've already shown this popup to avoid showing it repeatedly
        $popup_shown_key = 'payment_popup_shown_' . $recent_payment['Invoice_ID'];
        
        if (!isset($_SESSION[$popup_shown_key])) {
            $_SESSION[$popup_shown_key] = true;
            
            echo json_encode([
                'success' => true,
                'show_popup' => true,
                'paid_month' => $recent_payment['PaidMonth'],
                'next_due_date' => $recent_payment['NextDueDate'],
                'space_name' => $recent_payment['SpaceName']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'show_popup' => false,
                'message' => 'Popup already shown'
            ]);
        }
    } else {
        echo json_encode([
            'success' => true,
            'show_popup' => false,
            'message' => 'No recent payments found'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Payment status check PDO error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Payment status check error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'System error occurred'
    ]);
}
?>