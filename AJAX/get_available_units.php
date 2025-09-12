<?php
require '../database/database.php';
session_start();

$db = new Database();

// Session/login logic as in index.php:
$is_logged_in = isset($_SESSION['client_id']);
$client_is_inactive = false; 
$hide_client_rented_unit_ids = $is_logged_in ? $db->getClientRentedUnitIds($_SESSION['client_id']) : [];

$available_units = $db->getHomepageAvailableUnits(10);

if (!empty($available_units)) {
    $modal_counter = 0;
    foreach ($available_units as $space) {
        if (in_array($space['Space_ID'], $hide_client_rented_unit_ids)) continue;
        $modal_counter++;
        $modal_id = "unitModal" . $modal_counter;
        $photo_modal_id = "photoModal" . $modal_counter;

        $photo_urls = [];
        $photo_fields = ['Photo', 'Photo1', 'Photo2', 'Photo3', 'Photo4', 'Photo5'];
        foreach ($photo_fields as $photo_field) {
            if (!empty($space[$photo_field])) {
                $photo_urls[] = "uploads/unit_photos/" . htmlspecialchars($space[$photo_field]);
            }
        }
        ?>
        <div class="col-lg-4 col-md-6 animate-on-scroll">
          <div class="card unit-card">
            <?php if (!empty($photo_urls)): ?>
              <div style="position:relative;">
                <img src="<?= $photo_urls[0] ?>" class="card-img-top" alt="<?= htmlspecialchars($space['Name']) ?>" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#<?= $photo_modal_id ?>">
                <span class="badge bg-primary position-absolute top-0 end-0 m-2" style="z-index:2;"> <?= count($photo_urls) ?>/6 </span>
              </div>
            <?php else: ?>
              <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 250px;">
                <i class="fa-solid fa-house text-primary" style="font-size: 4rem;"></i>
              </div>
            <?php endif; ?>
            <div class="card-body">
              <h5 class="card-title fw-bold"><?= htmlspecialchars($space['Name']) ?></h5>
              <p class="unit-price">₱<?= number_format($space['Price'], 0) ?> / month</p>
              <p class="card-text text-muted">Premium commercial space in a strategic location.</p>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="unit-type"><?= htmlspecialchars($space['SpaceTypeName']) ?></span>
                <small class="unit-location"><?= htmlspecialchars($space['City']) ?></small>
              </div>
              <?php if ($is_logged_in && !$client_is_inactive): ?>
                <button class="btn btn-accent w-100" data-bs-toggle="modal" data-bs-target="#<?= $modal_id ?>">
                  <i class="bi bi-key me-2"></i>Rent Now
                </button>
              <?php elseif ($is_logged_in && $client_is_inactive): ?>
                <button class="btn btn-secondary w-100" disabled>
                  Account Inactive
                </button>
              <?php else: ?>
                <button class="btn btn-accent w-100" data-bs-toggle="modal" data-bs-target="#loginModal">
                  <i class="bi bi-key me-2"></i>Login to Rent
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php
    }
} else {
    echo '<div class="col-12 text-center"><div class="alert alert-info">No units currently available.</div></div>';
}
?>