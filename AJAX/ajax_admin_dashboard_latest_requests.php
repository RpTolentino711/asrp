<?php
require_once '../database/database.php';
session_start();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit('Forbidden');
}

$db = new Database();

// MARK REQUESTS AS SEEN WHEN LOADED
if (isset($_GET['mark_seen']) && $_GET['mark_seen'] == 'true') {
    $db->markRentalRequestsAsSeen();
}

$latest_requests = $db->getLatestPendingRequests(5);

if (!empty($latest_requests)) {
    $request_count = count($latest_requests);
    $new_requests_count = 0;
    
    // Count new (unseen) requests
    foreach ($latest_requests as $r) {
        if ($r['admin_seen'] == 0) {
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
    
    foreach ($latest_requests as $r) {
        $clientName = htmlspecialchars($r['Client_fn'] . ' ' . $r['Client_ln']);
        $unitName = htmlspecialchars($r['UnitName'] ?? 'N/A');
        $requestDate = date('M j, Y g:i A', strtotime($r['Requested_At'] ?? ''));
        $requestId = htmlspecialchars($r['Request_ID']);
        $isNew = $r['admin_seen'] == 0;
        
        echo '<tr class="' . ($isNew ? 'new-request-flash' : '') . '">';
        echo '<td>
                <div class="fw-bold">' . $clientName . '</div>
                <small class="text-muted">ID: #' . $requestId . '</small>
                ' . ($isNew ? '<span class="badge bg-success ms-2 new-request-indicator">New</span>' : '') . '
              </td>';
        echo '<td>' . $unitName . '</td>';
        echo '<td>
                <div class="small">' . $requestDate . '</div>
              </td>';
        echo '<td><span class="badge bg-warning text-white">' . htmlspecialchars($r['Status']) . '</span></td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '<div class="text-center p-3 border-top bg-light">
            <a href="view_rental_requests.php" class="btn btn-sm btn-primary">
                <i class="fas fa-list me-1"></i>
                Manage All Requests
            </a>
          </div>';
    echo '</div>';
    
} else {
    echo '<div class="text-center p-4 text-muted" data-count="0" data-new-count="0">
            <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
            <h5>No Pending Requests</h5>
            <p class="mb-3">All rental requests have been processed</p>
            <a href="view_rental_requests.php" class="btn btn-outline-primary">
                <i class="fas fa-external-link-alt me-1"></i>
                Check Rental Requests
            </a>
          </div>';
}
?>