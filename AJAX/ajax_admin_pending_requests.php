<?php
require_once '../database/database.php';
session_start();
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit('Forbidden');
}
$db = new Database();
$pending_requests = $db->getPendingRentalRequests();
if (!empty($pending_requests)) {
    echo '<div class="table-container"><table class="custom-table"><thead><tr><th>Client</th><th>Space</th><th>Start Date</th><th>End Date</th><th>Actions</th></tr></thead><tbody>';
    foreach ($pending_requests as $row) {
        echo '<tr>';
        echo '<td><div class="client-info"><div class="fw-bold">' . htmlspecialchars($row['Client_fn'].' '.$row['Client_ln']) . '</div>';
        echo '<div class="text-muted small">ID: #' . htmlspecialchars($row['Request_ID']) . '</div>';
        echo '<div class="client-tooltip">';
        echo '<div class="contact-item"><i class="fas fa-envelope"></i><span>' . htmlspecialchars($row['Client_Email']) . '</span></div>';
        if (!empty($row['Client_Phone'])) {
            echo '<div class="contact-item"><i class="fas fa-phone"></i><span>' . htmlspecialchars($row['Client_Phone']) . '</span></div>';
        }
        // Add the request submission date
        if (!empty($row['Requested_At'])) {
            $submitted_date = date('M j, Y g:i A', strtotime($row['Requested_At']));
            echo '<div class="request-date">';
            echo '<i class="fas fa-calendar-check"></i>';
            echo '<span>Submitted: ' . htmlspecialchars($submitted_date) . '</span>';
            echo '</div>';
        }
        echo '</div></div></td>';
        echo '<td>' . htmlspecialchars($row['Name']) . '</td>';
        echo '<td><div class="fw-medium">' . htmlspecialchars($row['StartDate']) . '</div></td>';
        echo '<td><div class="fw-medium">' . htmlspecialchars($row['EndDate']) . '</div></td>';
        echo '<td><form method="post" action="process_request.php" class="d-inline">';
        echo '<input type="hidden" name="request_id" value="' . $row['Request_ID'] . '">';
        echo '<button name="action" value="accept" class="btn-action btn-accept" onclick="return confirm(\'Are you sure you want to ACCEPT this rental request?\')"><i class="fas fa-check-circle"></i>Accept</button>';
        echo '<button name="action" value="reject" class="btn-action btn-reject" onclick="return confirm(\'Are you sure you want to REJECT this rental request?\')"><i class="fas fa-times-circle"></i>Reject</button>';
        echo '</form></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    echo '<span style="display:none" data-count="' . count($pending_requests) . '"></span>';
} else {
    echo '<div class="empty-state"><i class="fas fa-inbox"></i><h4>No pending requests</h4><p>All rental requests have been processed</p></div>';
    echo '<span style="display:none" data-count="0"></span>';
}