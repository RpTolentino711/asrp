<?php
require '../database/database.php';
session_start();

header('Content-Type: application/json');

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
        $client_is_inactive = ($_SESSION['C_status'] == 0 || $_SESSION['C_status'] === 'inactive');
    }
    
    // Get hidden unit IDs for logged-in clients
    $hide_client_rented_unit_ids = $is_logged_in ? $db->getClientRentedUnitIds($client_id) : [];
    
    // Get available units (you can adjust the limit or remove it)
    $available_units = $db->getLiveAvailableUnits(10);
    
    // Filter out units that should be hidden from this client
    $filtered_units = [];
    foreach ($available_units as $unit) {
        if (!in_array($unit['Space_ID'], $hide_client_rented_unit_ids)) {
            // Add additional info needed for frontend
            $unit['is_logged_in'] = $is_logged_in;
            $unit['client_is_inactive'] = $client_is_inactive;
            
            // Process photos
            $photo_urls = [];
            for ($i = 1; $i <= 5; $i++) {
                $photo_field = "Photo$i";
                if (!empty($unit[$photo_field])) {
                    $photo_urls[] = "uploads/unit_photos/" . $unit[$photo_field];
                }
            }
            if (empty($photo_urls) && !empty($unit['Photo'])) {
                $photo_urls[] = "uploads/unit_photos/" . $unit['Photo'];
            }
            $unit['photo_urls'] = $photo_urls;
            
            $filtered_units[] = $unit;
        }
    }
    
    // Return the filtered units
    echo json_encode([
        'success' => true,
        'data' => $filtered_units,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch units',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}