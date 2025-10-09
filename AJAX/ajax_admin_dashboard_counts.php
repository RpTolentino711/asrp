<?php
require_once '../database/database.php';
session_start();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit('Forbidden');
}

$db = new Database();

// Get current counts without date filtering for real-time updates
try {
    $counts = [
        'pending_rentals' => 0,
        'pending_maintenance' => 0,
        'unpaid_invoices' => 0,
        'overdue_invoices' => 0
    ];
    
    // Pending rental requests (all, not filtered by date)
    $sql = "SELECT COUNT(*) as count FROM rentalrequest WHERE Status = 'Pending' AND admin_seen = 0 AND Flow_Status = 'new'";
    $result = $db->getRow($sql);
    $counts['pending_rentals'] = $result['count'] ?? 0;
    
    // Pending maintenance requests (all)
    $sql = "SELECT COUNT(*) as count FROM maintenancerequest WHERE Status IN ('Submitted', 'In Progress')";
    $result = $db->getRow($sql);
    $counts['pending_maintenance'] = $result['count'] ?? 0;
    
    // Unpaid invoices (all)
    $sql = "SELECT COUNT(*) as count FROM invoice WHERE Status = 'unpaid' AND Flow_Status = 'new'";
    $result = $db->getRow($sql);
    $counts['unpaid_invoices'] = $result['count'] ?? 0;
    
    // Overdue invoices (all)
    $sql = "SELECT COUNT(*) as count FROM invoice WHERE Status = 'unpaid' AND EndDate < CURDATE() AND Flow_Status = 'new'";
    $result = $db->getRow($sql);
    $counts['overdue_invoices'] = $result['count'] ?? 0;
    
    header('Content-Type: application/json');
    echo json_encode($counts);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'pending_rentals' => 0,
        'pending_maintenance' => 0,
        'unpaid_invoices' => 0,
        'overdue_invoices' => 0,
        'error' => $e->getMessage()
    ]);
}
?>