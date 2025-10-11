<?php
require_once '../database/database.php';
session_start();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit('Forbidden');
}

$db = new Database();

// MARK MAINTENANCE REQUESTS AS SEEN WHEN LOADED
if (isset($_GET['mark_seen']) && $_GET['mark_seen'] == 'true') {
    $db->markMaintenanceRequestsAsSeen();
}

// Get latest maintenance requests - REMOVED THE WHERE CLAUSE
try {
    $sql = "SELECT mr.Request_ID, mr.RequestDate, mr.Status, mr.IssuePhoto, mr.admin_seen,
                   c.Client_ID, c.Client_fn, c.Client_ln, c.Client_Email,
                   s.Name AS UnitName, s.Space_ID
            FROM maintenancerequest mr
            LEFT JOIN client c ON mr.Client_ID = c.Client_ID
            LEFT JOIN space s ON mr.Space_ID = s.Space_ID
            ORDER BY mr.admin_seen ASC, mr.RequestDate DESC
            LIMIT 5";
    
    $stmt = $db->pdo->prepare($sql);
    $stmt->execute();
    $maintenance_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $maintenance_requests = [];
}

if (!empty($maintenance_requests)) {
    $request_count = count($maintenance_requests);
    $new_requests_count = 0;
    
    // Count new (unseen) requests
    foreach ($maintenance_requests as $mr) {
        if ($mr['admin_seen'] == 0 && $mr['Status'] == 'Submitted') {
            $new_requests_count++;
        }
    }
    
    echo '<div class="table-container" data-count="' . $request_count . '" data-new-count="' . $new_requests_count . '">
            <table class="custom-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Unit</th>
                    <th>Requested</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
    
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
        echo '<td>
                <div class="fw-bold">' . $clientName . '</div>
                <small class="text-muted">ID: #' . $requestId . '</small>';
        
        if ($isNew) {
            echo '<span class="badge bg-danger ms-2 new-request-indicator">New</span>';
        }
        
        echo '</td>';
        echo '<td>' . $unitName . '</td>';
        echo '<td>
                <div class="small">' . $requestDate . '</div>
              </td>';
        echo '<td><span class="badge ' . $badgeClass . '">' . $status . '</span></td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '<div class="text-center p-3 border-top bg-light">
            <a href="manage_maintenance.php" class="btn btn-sm btn-primary">
                <i class="fas fa-tools me-1"></i>
                Manage Maintenance
            </a>
          </div>';
    echo '</div>';
    
} else {
    echo '<div class="text-center p-4 text-muted" data-count="0" data-new-count="0">
            <i class="fas fa-tools fa-3x mb-3 text-muted"></i>
            <h5>No Maintenance Requests</h5>
            <p class="mb-3">All maintenance requests are processed</p>
            <a href="manage_maintenance.php" class="btn btn-outline-primary">
                <i class="fas fa-external-link-alt me-1"></i>
                Check Maintenance
            </a>
          </div>';
}
?>