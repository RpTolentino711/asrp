<?php
require_once '../database/database.php';
session_start();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit('Forbidden');
}

$db = new Database();

// Get current counts with MONTHLY filtering for invoices
try {
    $currentMonth = date('m');
    $currentYear = date('Y');
    $currentDate = date('Y-m-d');
    
    $counts = [
        'pending_rentals' => 0,
        'pending_maintenance' => 0,
        'unpaid_invoices' => 0,
        'overdue_invoices' => 0,
        'new_maintenance_requests' => 0,
        'unseen_rentals' => 0,
        'unread_client_messages' => 0
    ];
    
    // Pending rental requests (ALL pending, not just unseen)
    $sql = "SELECT COUNT(*) as count FROM rentalrequest WHERE Status = 'Pending' AND Flow_Status = 'new'";
    $result = $db->getRow($sql);
    $counts['pending_rentals'] = $result['count'] ?? 0;
    
    // Unseen rental requests (for notifications only)
    $sql = "SELECT COUNT(*) as count FROM rentalrequest WHERE Status = 'Pending' AND admin_seen = 0 AND Flow_Status = 'new'";
    $result = $db->getRow($sql);
    $counts['unseen_rentals'] = $result['count'] ?? 0;
    
    // Total pending maintenance requests (all statuses)
    $sql = "SELECT COUNT(*) as count FROM maintenancerequest WHERE Status IN ('Submitted', 'In Progress')";
    $result = $db->getRow($sql);
    $counts['pending_maintenance'] = $result['count'] ?? 0;
    
    // NEW: Get count of unseen maintenance requests (admin_seen = 0)
    $sql = "SELECT COUNT(*) as count FROM maintenancerequest WHERE Status = 'Submitted' AND admin_seen = 0";
    $result = $db->getRow($sql);
    $counts['new_maintenance_requests'] = $result['count'] ?? 0;
    
    // FIXED: Unpaid invoices for CURRENT MONTH only
    $sql = "SELECT COUNT(*) as count FROM invoice 
            WHERE Status = 'unpaid' 
            AND MONTH(EndDate) = ? 
            AND YEAR(EndDate) = ?";
    $result = $db->getRow($sql, [$currentMonth, $currentYear]);
    $counts['unpaid_invoices'] = $result['count'] ?? 0;
    
    // FIXED: Overdue invoices for CURRENT MONTH only
    $sql = "SELECT COUNT(*) as count FROM invoice 
            WHERE Status = 'unpaid' 
            AND EndDate < ? 
            AND MONTH(EndDate) = ? 
            AND YEAR(EndDate) = ?";
    $result = $db->getRow($sql, [$currentDate, $currentMonth, $currentYear]);
    $counts['overdue_invoices'] = $result['count'] ?? 0;
    
    // NEW: Get count of unread client messages in invoice chat
    $sql = "SELECT COUNT(*) as count FROM invoice_chat 
            WHERE Sender_Type = 'client' AND is_read_admin = 0";
    $result = $db->getRow($sql);
    $counts['unread_client_messages'] = $result['count'] ?? 0;
    
    header('Content-Type: application/json');
    echo json_encode($counts);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'pending_rentals' => 0,
        'pending_maintenance' => 0,
        'unpaid_invoices' => 0,
        'overdue_invoices' => 0,
        'new_maintenance_requests' => 0,
        'unseen_rentals' => 0,
        'unread_client_messages' => 0,
        'error' => $e->getMessage()
    ]);
}
?>