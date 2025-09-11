<?php
require_once '../database/database.php';
session_start();
$db = new Database();

$is_logged_in = isset($_SESSION['client_id']);
$client_is_inactive = false;
if ($is_logged_in) {
    $client_id = $_SESSION['client_id'];
    if (isset($_SESSION['C_status'])) {
        $client_is_inactive = ($_SESSION['C_status'] == 0 || $_SESSION['C_status'] === 'inactive');
    } else {
        $details = $db->getClientFullDetails($client_id);
        if ($details) {
            $client_is_inactive = ($details['Status'] == 0 || $details['Status'] === 'inactive');
        }
    }
}

$hide_client_rented_unit_ids = $is_logged_in ? $db->getClientRentedUnitIds($_SESSION['client_id']) : [];
$available_units = $db->getHomepageAvailableUnits(10);

if (empty($available_units)) {
    echo '<div class="col-12 text-center text-muted py-5">No available units at the moment.</div>';
    exit;
}

$modal_counter = 0;
$modals = '';
foreach ($available_units as $space) {
    if (in_array($space['Space_ID'], $hide_client_rented_unit_ids)) continue;
    $modal_counter++;
    $modal_id = "unitModal" . $modal_counter;
    $photo_modal_id = "photoModal" . $modal_counter;
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
        echo '<img src="' . $photo_urls[0] . '" class="card-img-top" alt="' . htmlspecialchars($space['Name']) . '" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#' . $photo_modal_id . '">';
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
    if ($is_logged_in && !$client_is_inactive) {
        echo '<button class="btn btn-accent w-100" data-bs-toggle="modal" data-bs-target="#' . $modal_id . '"><i class="bi bi-key me-2"></i>Rent Now</button>';
    } elseif ($is_logged_in && $client_is_inactive) {
        echo '<button class="btn btn-secondary w-100" disabled>Account Inactive</button>';
    } else {
        echo '<button class="btn btn-accent w-100" data-bs-toggle="modal" data-bs-target="#loginModal"><i class="bi bi-key me-2"></i>Login to Rent</button>';
    }
    echo '</div></div></div>';

    // Photo Modal
    echo '<div class="modal fade" id="' . $photo_modal_id . '" tabindex="-1" aria-labelledby="' . $photo_modal_id . 'Label" aria-hidden="true">';
    echo '<div class="modal-dialog modal-dialog-centered modal-xl">';
    echo '<div class="modal-content bg-dark">';
    echo '<div class="modal-header border-0">';
    echo '<h5 class="modal-title text-white" id="' . $photo_modal_id . 'Label">Photo Gallery: ' . htmlspecialchars($space['Name']) . '</h5>';
    echo '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>';
    echo '</div>';
    echo '<div class="modal-body text-center">';
    if (count($photo_urls) === 1) {
        echo '<img src="' . $photo_urls[0] . '" alt="Unit Photo Zoom" class="img-fluid rounded shadow" style="max-height:60vh;">';
    } else {
        echo '<div id="zoomCarousel' . $modal_counter . '" class="carousel slide" data-bs-ride="carousel">';
        echo '<div class="carousel-inner">';
        foreach ($photo_urls as $idx => $url) {
            echo '<div class="carousel-item' . ($idx === 0 ? ' active' : '') . '">';
            echo '<img src="' . $url . '" class="d-block mx-auto img-fluid rounded shadow" alt="Zoom Photo ' . ($idx+1) . '" style="max-height:60vh;">';
            echo '</div>';
        }
        echo '</div>';
        echo '<button class="carousel-control-prev" type="button" data-bs-target="#zoomCarousel' . $modal_counter . '" data-bs-slide="prev">';
        echo '<span class="carousel-control-prev-icon" aria-hidden="true"></span>';
        echo '<span class="visually-hidden">Previous</span>';
        echo '</button>';
        echo '<button class="carousel-control-next" type="button" data-bs-target="#zoomCarousel' . $modal_counter . '" data-bs-slide="next">';
        echo '<span class="carousel-control-next-icon" aria-hidden="true"></span>';
        echo '<span class="visually-hidden">Next</span>';
        echo '</button>';
        echo '</div>';
    }
    echo '</div></div></div></div>';

    // Rental Modal
    if ($is_logged_in && !$client_is_inactive) {
        echo '<div class="modal fade" id="' . $modal_id . '" tabindex="-1" aria-labelledby="' . $modal_id . 'Label" aria-hidden="true">';
        echo '<div class="modal-dialog modal-dialog-centered">';
        echo '<div class="modal-content">';
        echo '<div class="modal-header">';
        echo '<h5 class="modal-title" id="' . $modal_id . 'Label">Contact Admin to Rent: ' . htmlspecialchars($space['Name']) . '</h5>';
        echo '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
        echo '</div>';
        echo '<div class="modal-body">';
        echo '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>To rent this unit and receive an invoice, please contact our admin team for rental approval.</div>';
        echo '<div class="row">';
        echo '<div class="col-md-6">';
        echo '<h6 class="fw-bold">Admin Contact:</h6>';
        echo '<p class="mb-1"><i class="bi bi-envelope me-2"></i><a href="mailto:rom_telents@asrt.com">rom_telents@asrt.com</a></p>';
        echo '<p class="mb-3"><i class="bi bi-telephone me-2"></i><a href="tel:+639171234567">+63 917 123 4567</a></p>';
        echo '</div>';
        echo '<div class="col-md-6">';
        echo '<div class="alert alert-warning"><strong>Invoice Required:</strong><br>Please request your invoice from the admin for the rental process.</div>';
        echo '</div>';
        echo '</div>';
        echo '<ul class="list-group list-group-flush">';
        echo '<li class="list-group-item"><strong>Price:</strong> ₱' . number_format($space['Price'], 0) . ' per month</li>';
        echo '<li class="list-group-item"><strong>Unit Type:</strong> ' . htmlspecialchars($space['SpaceTypeName']) . '</li>';
        echo '<li class="list-group-item"><strong>Location:</strong> ' . htmlspecialchars($space['Street']) . ', ' . htmlspecialchars($space['Brgy']) . ', ' . htmlspecialchars($space['City']) . '</li>';
        echo '</ul>';
        echo '</div>';
        echo '<div class="modal-footer">';
        echo '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
        echo '<a href="rent_request.php?space_id=' . urlencode($space['Space_ID']) . '" class="btn btn-success"><i class="bi bi-receipt me-2"></i>Request Invoice</a>';
        echo '</div>';
        echo '</div></div></div>';
    }
}
