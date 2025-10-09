<?php
require_once '../database/database.php';
session_start();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit('Forbidden');
}

// Simple caching mechanism - 15 seconds
$cache_time = 15;
$cache_key = 'pending_requests_' . md5(session_id());
$cache_file = __DIR__ . '/cache/' . $cache_key . '.html';

// Create cache directory if it doesn't exist
if (!is_dir(__DIR__ . '/cache')) {
    mkdir(__DIR__ . '/cache', 0755, true);
}

// Return cached version if it exists and is fresh
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    readfile($cache_file);
    exit;
}

$db = new Database();
$pending_requests = $db->getPendingRentalRequests();

// Start output buffering for caching
ob_start();

if (!empty($pending_requests)) {
    $request_count = count($pending_requests);
    
    echo '<div class="table-container" data-count="' . $request_count . '">
            <table class="custom-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Space</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($pending_requests as $row) {
        $clientName = htmlspecialchars($row['Client_fn'] . ' ' . $row['Client_ln']);
        $spaceName = htmlspecialchars($row['Name']);
        $requestId = (int)$row['Request_ID'];
        
        echo '<tr>';
        
        // Client Info Column
        echo '<td>
                <div class="client-info">
                    <div class="fw-bold">' . $clientName . '</div>
                    <div class="text-muted small">ID: #' . $requestId . '</div>
                    <div class="client-tooltip">
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>' . htmlspecialchars($row['Client_Email']) . '</span>
                        </div>';
        
        if (!empty($row['Client_Phone'])) {
            echo '<div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <span>' . htmlspecialchars($row['Client_Phone']) . '</span>
                  </div>';
        }
        
        if (!empty($row['Requested_At'])) {
            $submitted_date = date('M j, Y g:i A', strtotime($row['Requested_At']));
            echo '<div class="request-date">
                    <i class="fas fa-calendar-check"></i>
                    <span>Submitted: ' . htmlspecialchars($submitted_date) . '</span>
                  </div>';
        }
        
        echo '</div></div></td>';
        
        // Space Name
        echo '<td>' . $spaceName . '</td>';
        
        // Dates
        echo '<td><div class="fw-medium">' . htmlspecialchars($row['StartDate']) . '</div></td>';
        echo '<td><div class="fw-medium">' . htmlspecialchars($row['EndDate']) . '</div></td>';
        
        // Actions - WITH FORMS!
        echo '<td>
                <div class="action-buttons d-flex gap-2">';
        
        // Accept Form
        echo '<form method="post" id="acceptForm_' . $requestId . '" style="display:inline;">
                <input type="hidden" name="request_id" value="' . $requestId . '">
                <input type="hidden" name="accept_request" value="1">
                <button type="button" 
                        onclick="confirmAccept(' . $requestId . ', \'' . addslashes($clientName) . '\', \'' . addslashes($spaceName) . '\')" 
                        class="btn-action btn-accept">
                    <i class="fas fa-check-circle"></i> Accept
                </button>
              </form>';
        
        // Reject Form
        echo '<form method="post" id="rejectForm_' . $requestId . '" style="display:inline;">
                <input type="hidden" name="request_id" value="' . $requestId . '">
                <input type="hidden" name="reject_request" value="1">
                <button type="button" 
                        onclick="confirmReject(' . $requestId . ', \'' . addslashes($clientName) . '\', \'' . addslashes($spaceName) . '\')" 
                        class="btn-action btn-reject">
                    <i class="fas fa-times-circle"></i> Reject
                </button>
              </form>';
        
        echo '</div></td></tr>';
    }
    
    echo '</tbody></table></div>';
    
} else {
    echo '<div class="empty-state" data-count="0">
            <i class="fas fa-inbox"></i>
            <h4>No pending requests</h4>
            <p class="text-muted">All rental requests have been processed.</p>
          </div>';
}

// Get the output and cache it
$output = ob_get_clean();

// Save to cache file
file_put_contents($cache_file, $output);

// Output the result
echo $output;
?>