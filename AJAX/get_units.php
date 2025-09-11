<?php
require 'database/database.php';
session_start();

header('Content-Type: application/json');

try {
    $db = new Database();
    $is_logged_in = isset($_SESSION['client_id']);
    $client_is_inactive = false;
    
    if ($is_logged_in) {
        if (!isset($_SESSION['C_status'])) {
            $status_record = $db->getClientStatus($_SESSION['client_id']);
            if ($status_record) {
                $_SESSION['C_status'] = $status_record['Status'];
            }
        }
        $client_is_inactive = ($_SESSION['C_status'] == 0 || $_SESSION['C_status'] === 'inactive');
    }
    
    $available_units = $db->getHomepageAvailableUnits(20);
    
    $units_data = [];
    foreach ($available_units as $unit) {
        $units_data[] = [
            'Space_ID' => $unit['Space_ID'],
            'Name' => $unit['Name'],
            'Price' => $unit['Price'],
            'SpaceTypeName' => $unit['SpaceTypeName'],
            'City' => $unit['City'],
            'Photo1' => $unit['Photo1'] ?? $unit['Photo'] ?? null,
            'is_logged_in' => $is_logged_in,
            'client_is_inactive' => $client_is_inactive
        ];
    }
    
    echo json_encode([
        'success' => true,
        'units' => $units_data
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
?>