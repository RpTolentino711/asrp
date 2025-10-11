<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the start of the script
error_log("=== MAINTENANCE AJAX STARTED ===");

require_once '../database/database.php';
session_start();

error_log("Session check - is_admin: " . (isset($_SESSION['is_admin']) ? 'yes' : 'no'));

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    error_log("ACCESS DENIED - Not admin");
    http_response_code(403);
    exit('Forbidden - Admin access required');
}

$db = new Database();
error_log("Database connected");

// MARK MAINTENANCE REQUESTS AS SEEN WHEN LOADED
if (isset($_GET['mark_seen']) && $_GET['mark_seen'] == 'true') {
    error_log("Marking requests as seen");
    try {
        $db->markMaintenanceRequestsAsSeen();
        error_log("Requests marked as seen successfully");
    } catch (Exception $e) {
        error_log("Error marking requests as seen: " . $e->getMessage());
    }
}

// Get latest maintenance requests
try {
    error_log("Executing SQL query");
    $sql = "SELECT mr.Request_ID, mr.RequestDate, mr.Status, mr.IssuePhoto, mr.admin_seen,
                   c.Client_ID, c.Client_fn, c.Client_ln, c.Client_Email,
                   s.Name AS UnitName, s.Space_ID
            FROM maintenancerequest mr
            LEFT JOIN client c ON mr.Client_ID = c.Client_ID
            LEFT JOIN space s ON mr.Space_ID = s.Space_ID
            ORDER BY mr.admin_seen ASC, mr.RequestDate DESC
            LIMIT 5";
    
    error_log("SQL: " . $sql);
    
    $stmt = $db->pdo->prepare($sql);
    $stmt->execute();
    $maintenance_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Query executed successfully. Found: " . count($maintenance_requests) . " requests");
    
} catch (Exception $e) {
    error_log("DATABASE ERROR: " . $e->getMessage());
    $maintenance_requests = [];
    // Output the error for debugging
    echo "<!-- DATABASE ERROR: " . htmlspecialchars($e->getMessage()) . " -->";
}

// Output simple debug response first
echo "<!-- DEBUG: Starting output -->";

if (!empty($maintenance_requests)) {
    $request_count = count($maintenance_requests);
    $new_requests_count = 0;
    
    error_log("Processing " . $request_count . " requests");
    
    // Count new (unseen) requests
    foreach ($maintenance_requests as $mr) {
        if ($mr['admin_seen'] == 0 && $mr['Status'] == 'Submitted') {
            $new_requests_count++;
        }
    }
    
    error_log("New requests: " . $new_requests_count);
    
    echo '<div class="table-container" data-count="' . $request_count . '" data-new-count="' . $new_requests_count . '">';
    echo '<table class="custom-table">';
    echo '<thead><tr><th>Client</th><th>Unit</th><th>Requested</th><th>Status</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($maintenance_requests as $mr) {
        $clientName = htmlspecialchars($mr['Client_fn'] . ' ' . $mr['Client_ln']);
        $unitName = htmlspecialchars($mr['UnitName'] ?? 'N/A');
        $requestDate = date('M j, Y g:i A', strtotime($mr['RequestDate'] ?? ''));
        $requestId = htmlspecialchars($mr['Request_ID']);
        $status = htmlspecialchars($mr['Status']);
        $isNew = ($mr['admin_seen'] == 0 && $status == 'Submitted');
        
        // Determine badge color based on status
        $badgeClass = '';
        switch ($status) {
            case 'Submitted': $badgeClass = 'bg-warning text-white'; break;
            case 'In Progress': $badgeClass = 'bg-info text-white'; break;
            case 'Completed': $badgeClass = 'bg-success text-white'; break;
            default: $badgeClass = 'bg-secondary text-white';
        }
        
        echo '<tr class="' . ($isNew ? 'maintenance-highlight' : '') . '">';
        echo '<td><div class="fw-bold">' . $clientName . '</div>';
        echo '<small class="text-muted">ID: #' . $requestId . '</small>';
        if ($isNew) {
            echo '<span class="badge bg-danger ms-2 new-request-indicator">New</span>';
        }
        echo '</td>';
        echo '<td>' . $unitName . '</td>';
        echo '<td><div class="small">' . $requestDate . '</div></td>';
        echo '<td><span class="badge ' . $badgeClass . '">' . $status . '</span></td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '<div class="text-center p-3 border-top bg-light">';
    echo '<a href="manage_maintenance.php" class="btn btn-sm btn-primary">';
    echo '<i class="fas fa-tools me-1"></i>Manage Maintenance</a>';
    echo '</div></div>';
    
} else {
    error_log("No maintenance requests found");
    echo '<div class="text-center p-4 text-muted" data-count="0" data-new-count="0">';
    echo '<i class="fas fa-tools fa-3x mb-3 text-muted"></i>';
    echo '<h5>No Maintenance Requests</h5>';
    echo '<p class="mb-3">All maintenance requests are processed</p>';
    echo '<a href="manage_maintenance.php" class="btn btn-outline-primary">';
    echo '<i class="fas fa-external-link-alt me-1"></i>Check Maintenance</a>';
    echo '</div>';
}

error_log("=== MAINTENANCE AJAX COMPLETED ===");
?>