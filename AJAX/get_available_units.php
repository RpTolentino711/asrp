<?php
require_once '../database/database.php';
$db = new Database();

// Fetch available units (limit as needed)
$available_units = $db->getHomepageAvailableUnits(10);

if (empty($available_units)) {
    echo '<div class="col-12 text-center text-muted py-5">No available units at the moment.</div>';
    exit;
}

// Output HTML for each unit card (same markup as in index.php)
foreach ($available_units as $space) {
    $photo_urls = [];
    for ($i=1; $i<=5; $i++) {
        $photo_field = "Photo$i";
        if (!empty($space[$photo_field])) {
            $photo_urls[] = "uploads/unit_photos/" . htmlspecialchars($space[$photo_field]);
        }
    }
    if (empty($photo_urls) && !empty($space['Photo'])) {
        $photo_urls[] = "uploads/unit_photos/" . htmlspecialchars($space['Photo']);
    }
    echo '<div class="col-lg-4 col-md-6 animate-on-scroll">';
    echo '<div class="card unit-card">';
    if (!empty($photo_urls)) {
        echo '<img src="' . $photo_urls[0] . '" class="card-img-top" alt="' . htmlspecialchars($space['Name']) . '">';
    } else {
        echo '<div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 250px;">';
        echo '<i class="fa-solid fa-house text-primary" style="font-size: 4rem;"></i>';
        echo '</div>';
    }
    echo '<div class="card-body">';
    echo '<h5 class="card-title fw-bold">' . htmlspecialchars($space['Name']) . '</h5>';
    echo '<p class="unit-price">₱' . number_format($space['Price'], 0) . ' / month</p>';
    echo '<p class="card-text text-muted">Premium commercial space in a strategic location.</p>';
    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<span class="unit-type">' . htmlspecialchars($space['SpaceTypeName']) . '</span>';
    echo '<small class="unit-location">' . htmlspecialchars($space['City']) . '</small>';
    echo '</div>';
    echo '</div></div></div>';
}
