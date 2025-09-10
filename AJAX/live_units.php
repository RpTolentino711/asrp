<?php
require '../database/database.php';
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

try {
    // Create an instance of the Database class
    $db = new Database();
    
    // Get session info
    $is_logged_in = isset($_SESSION['client_id']);
    $client_is_inactive = false;
    $client_id = $is_logged_in ? $_SESSION['client_id'] : null;
    
    // Check client status if logged in
    if ($is_logged_in) {
        if (!isset($_SESSION['C_status']) && $client_id) {
            $status_record = $db->getClientStatus($client_id);
            if ($status_record) {
                $_SESSION['C_status'] = $status_record['Status'];
            }
        }
        
        // Check if client is inactive
        if (isset($_SESSION['C_status'])) {
            $client_is_inactive = ($_SESSION['C_status'] == 0 || $_SESSION['C_status'] === 'inactive');
        }
    }
    
    // Get hidden unit IDs for logged-in clients (units they're already renting)
    $hide_client_rented_unit_ids = $is_logged_in ? $db->getClientRentedUnitIds($client_id) : [];
    
    // Get available units using the new method
    $available_units = $db->getLiveAvailableUnits(10);
    
    // Filter out units that should be hidden from this client
    $filtered_units = [];
    foreach ($available_units as $unit) {
        if (!in_array($unit['Space_ID'], $hide_client_rented_unit_ids)) {
            // Add additional info needed for frontend
            $unit['is_logged_in'] = $is_logged_in;
            $unit['client_is_inactive'] = $client_is_inactive;
            
            // Process photos based on your database structure
            $photo_urls = [];
            
            // Check Photo1-Photo5 fields first
            for ($i = 1; $i <= 5; $i++) {
                $photo_field = "Photo$i";
                if (!empty($unit[$photo_field])) {
                    $photo_urls[] = "uploads/unit_photos/" . $unit[$photo_field];
                }
            }
            
            // Fallback to legacy Photo field if no Photo1-Photo5
            if (empty($photo_urls) && !empty($unit['Photo'])) {
                $photo_urls[] = "uploads/unit_photos/" . $unit['Photo'];
            }
            
            $unit['photo_urls'] = $photo_urls;
            
            // Ensure all required fields exist with defaults
            $unit['Street'] = $unit['Street'] ?? '';
            $unit['Brgy'] = $unit['Brgy'] ?? '';
            $unit['City'] = $unit['City'] ?? '';
            $unit['SpaceTypeName'] = $unit['SpaceTypeName'] ?? 'Commercial Space';
            
            $filtered_units[] = $unit;
        }
    }
    
    // Return the filtered units with success response
    echo json_encode([
        'success' => true,
        'data' => $filtered_units,
        'timestamp' => date('Y-m-d H:i:s'),
        'total_count' => count($filtered_units),
        'session_info' => [
            'is_logged_in' => $is_logged_in,
            'client_is_inactive' => $client_is_inactive,
            'client_id' => $client_id
        ]
    ]);

} catch (Exception $e) {
    // Log the error for debugging
    error_log("AJAX live_units error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch units',
        'message' => 'An error occurred while loading available units. Please try again.',
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'error_message' => $e->getMessage(),
            'error_line' => $e->getLine(),
            'error_file' => $e->getFile()
        ]
    ]);
} finally {
    // Ensure output is sent
    if (ob_get_level()) {
        ob_end_flush();
    }
}
?>