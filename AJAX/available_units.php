<?php
require_once '../database/database.php';

header('Content-Type: application/json');

$db = new Database();
$available_units = $db->getHomepageAvailableUnits(10);

// Prepare data for JSON output
$result = [];
foreach ($available_units as $space) {
    $photo_fields = ['Photo', 'Photo1', 'Photo2', 'Photo3', 'Photo4', 'Photo5'];
    $photo_urls = [];
    foreach ($photo_fields as $photo_field) {
        if (!empty($space[$photo_field])) {
            $photo_urls[] = 'uploads/unit_photos/' . htmlspecialchars($space[$photo_field]);
        }
    }
    $result[] = [
        'Space_ID' => $space['Space_ID'],
        'Name' => $space['Name'],
        'Price' => $space['Price'],
        'SpaceTypeName' => $space['SpaceTypeName'],
        'City' => $space['City'],
        'photo_urls' => $photo_urls
    ];
}
echo json_encode($result);