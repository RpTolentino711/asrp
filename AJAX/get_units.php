<?php
require '../database/database.php';
header('Content-Type: application/json');

$db = new Database();

// Get available units again
$available_units = $db->getHomepageAvailableUnits(10);
$hide_client_rented_unit_ids = []; // adjust if needed
$is_logged_in = isset($_SESSION['client_id']);
$client_is_inactive = false; // adjust if needed

if (!empty($available_units)) {
    $modal_counter = 0;
    foreach ($available_units as $space) {
        if (in_array($space['Space_ID'], $hide_client_rented_unit_ids)) continue;
        $modal_counter++;
        $modal_id = "unitModal" . $modal_counter;
        $photo_modal_id = "photoModal" . $modal_counter;

        // Multi-photo display logic
        $photo_urls = [];
        $photo_fields = ['Photo', 'Photo1', 'Photo2', 'Photo3', 'Photo4', 'Photo5'];
        foreach ($photo_fields as $photo_field) {
            if (!empty($space[$photo_field])) {
                $photo_urls[] = "uploads/unit_photos/" . htmlspecialchars($space[$photo_field]);
            }
        }
        ?>
        <!-- Unit Card HTML (same as before) -->
        <div class="col-lg-4 col-md-6 animate-on-scroll">
          ...
        </div>
        <?php
    }
} else {
    echo '<div class="col-12 text-center">
            <div class="alert alert-info">No units currently available.</div>
          </div>';
}
