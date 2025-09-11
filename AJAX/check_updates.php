<?php
// ajax/check_updates.php
require '../database/database.php';
session_start();

header('Content-Type: application/json');

try {
    $db = new Database();
    
    // Get data counts and hashes to check for changes
    $response = [
        'success' => true,
        'hashes' => []
    ];
    
    // Available Units Hash
    $available_units = $db->getHomepageAvailableUnits(10);
    $response['hashes']['available_units'] = md5(serialize($available_units));
    
    // Rented Units Hash
    $rented_units = $db->getHomepageRentedUnits(10);
    $response['hashes']['rented_units'] = md5(serialize($rented_units));
    
    // Services Hash
    $services = $db->getAllJobTypes();
    $response['hashes']['services'] = md5(serialize($services));
    
    // Testimonials Hash
    $testimonials = $db->getHomepageTestimonials(6);
    $response['hashes']['testimonials'] = md5(serialize($testimonials));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking for updates',
        'error' => $e->getMessage()
    ]);
}
?>