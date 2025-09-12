<?php
require_once '../database/database.php';

header('Content-Type: application/json');

$db = new Database();
$rented_units_display = $db->getHomepageRentedUnits(10);
$rented_unit_ids = array_column($rented_units_display, 'Space_ID');
$rented_unit_photos = $db->getAllUnitPhotosForUnits($rented_unit_ids);

$result = [];
foreach ($rented_units_display as $rent) {
    $rented_photo_urls = [];
    if (!empty($rented_unit_photos[$rent['Space_ID']])) {
        foreach ($rented_unit_photos[$rent['Space_ID']] as $photo) {
            if (!empty($photo)) {
                $rented_photo_urls[] = 'uploads/unit_photos/' . htmlspecialchars($photo);
            }
        }
    }
    $result[] = [
        'Space_ID' => $rent['Space_ID'],
        'Name' => $rent['Name'],
        'Price' => $rent['Price'],
        'SpaceTypeName' => $rent['SpaceTypeName'],
        'City' => $rent['City'],
        'Street' => $rent['Street'],
        'Brgy' => $rent['Brgy'],
        'Client_fn' => $rent['Client_fn'],
        'Client_ln' => $rent['Client_ln'],
        'StartDate' => $rent['StartDate'],
        'EndDate' => $rent['EndDate'],
        'photo_urls' => $rented_photo_urls
    ];
}
echo json_encode($result);