<?php
require_once '../database/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['client_id']) && !isset($_POST['client_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : $_SESSION['client_id'];
$db = new Database();

try {
    // Get all paid invoices for this client in the last 7 days
    $sql = "SELECT i.Invoice_ID, i.InvoiceDate, i.EndDate, i.Status, i.Flow_Status, 
                   s.Name as SpaceName,
                   DATE_FORMAT(i.InvoiceDate, '%M %Y') as PaidMonth,
                   DATE_FORMAT(i.EndDate, '%M %d, %Y') as NextDueDate,
                   i.Created_At,
                   cs.CS_ID
            FROM invoice i
            INNER JOIN space s ON i.Space_ID = s.Space_ID
            INNER JOIN clientspace cs ON i.Space_ID = cs.Space_ID AND i.Client_ID = cs.Client_ID
            WHERE i.Client_ID = ? 
            AND i.Status = 'paid' 
            AND i.Flow_Status = 'done'
            AND i.Created_At >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND cs.active = 1
            ORDER BY i.Created_At DESC";
    
    $stmt = $db->pdo->prepare($sql);
    $stmt->execute([$client_id]);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($recent_payments) {
        $popups_to_show = [];
        
        foreach ($recent_payments as $payment) {
            $popup_shown_key = 'payment_popup_shown_' . $payment['Invoice_ID'];
            
            if (!isset($_SESSION[$popup_shown_key])) {
                $_SESSION[$popup_shown_key] = true;
                $popups_to_show[] = [
                    'paid_month' => $payment['PaidMonth'],
                    'next_due_date' => $payment['NextDueDate'],
                    'space_name' => $payment['SpaceName']
                ];
            }
        }
        
        if (!empty($popups_to_show)) {
            // Show popup for the most recent payment
            $latest_popup = $popups_to_show[0];
            
            echo json_encode([
                'success' => true,
                'show_popup' => true,
                'paid_month' => $latest_popup['paid_month'],
                'next_due_date' => $latest_popup['next_due_date'],
                'space_name' => $latest_popup['space_name'],
                'total_payments' => count($popups_to_show)
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'show_popup' => false,
                'message' => 'All recent payment popups already shown'
            ]);
        }
    } else {
        echo json_encode([
            'success' => true,
            'show_popup' => false,
            'message' => 'No recent paid invoices found'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Payment status check PDO error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>