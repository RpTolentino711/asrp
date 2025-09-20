<?php
require_once '../database/database.php';
session_start();
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit('Forbidden');
}
$db = new Database();
$latest_requests = $db->getLatestPendingRequests(5);
if (!empty($latest_requests)) {
    echo '<div class="table-container"><table class="custom-table"><thead><tr><th>Request ID</th><th>Client</th><th>Unit</th><th>Date</th><th>Status</th></tr></thead><tbody>';
    foreach ($latest_requests as $r) {
        echo '<tr>';
        echo '<td>#' . htmlspecialchars($r['Request_ID']) . '</td>';
        echo '<td>' . htmlspecialchars($r['Client_fn'] . ' ' . $r['Client_ln']) . '</td>';
        echo '<td>' . htmlspecialchars($r['UnitName'] ?? $r['Name'] ?? 'N/A') . '</td>';
        echo '<td>' . date('M j, Y', strtotime($r['Requested_At'] ?? '')) . '</td>';
        echo '<td><span class="badge bg-warning-light text-warning">' . htmlspecialchars($r['Status']) . '</span></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
} else {
    echo '<div class="text-center p-4 text-muted"><i class="fas fa-inbox fa-2x mb-2"></i><p>No pending rental requests</p></div>';
}
