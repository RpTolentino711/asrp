<?php
require '../database/database.php';
session_start();

// Check if user is logged in and get rented units to hide (if needed)
$is_logged_in = isset($_SESSION['client_id']);
$hide_client_rented_unit_ids = [];
if ($is_logged_in) {
    $db = new Database();
    $hide_client_rented_unit_ids = $db->getClientRentedUnitIds($_SESSION['client_id']);
} else {
    $db = new Database();
}

// Get the after_id (default to 0)
$after_id = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

// Fetch new units with Space_ID greater than after_id
$available_units = $db->getHomepageAvailableUnits(10, $after_id);

// Render units using the same HTML as your main loop
if (!empty($available_units)) {
    $modal_counter = 0;
    foreach ($available_units as $space) {
        if (in_array($space['Space_ID'], $hide_client_rented_unit_ids)) continue;
        $modal_counter++;
        $modal_id = "unitModal" . uniqid();
        $photo_modal_id = "photoModal" . uniqid();

        // Multi-photo logic
        $photo_urls = [];
        $photo_fields = ['Photo', 'Photo1', 'Photo2', 'Photo3', 'Photo4', 'Photo5'];
        foreach ($photo_fields as $photo_field) {
            if (!empty($space[$photo_field])) {
                $photo_urls[] = "uploads/unit_photos/" . htmlspecialchars($space[$photo_field]);
            }
        }
        ?>
        <div class="col-lg-4 col-md-6 animate-on-scroll" data-unit-id="<?= $space['Space_ID'] ?>">
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
              
              <?php if ($is_logged_in && empty($_SESSION['client_is_inactive'])): ?>
                <button class="btn btn-accent w-100" data-bs-toggle="modal" data-bs-target="#<?= $modal_id ?>">
                  <i class="bi bi-key me-2"></i>Rent Now
                </button>
              <?php elseif ($is_logged_in && !empty($_SESSION['client_is_inactive'])): ?>
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

        <!-- Photo Modal -->
        <div class="modal fade" id="<?= $photo_modal_id ?>" tabindex="-1" aria-labelledby="<?= $photo_modal_id ?>Label" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content bg-dark">
              <div class="modal-header border-0">
                <h5 class="modal-title text-white" id="<?= $photo_modal_id ?>Label">
                  Photo Gallery: <?= htmlspecialchars($space['Name']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body text-center">
                <?php if (count($photo_urls) === 1): ?>
                  <img src="<?= $photo_urls[0] ?>" alt="Unit Photo Zoom" class="img-fluid rounded shadow" style="max-height:60vh;">
                <?php elseif (count($photo_urls) > 1): ?>
                  <div id="zoomCarousel<?= $modal_counter ?>" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                      <?php foreach ($photo_urls as $idx => $url): ?>
                        <div class="carousel-item<?= $idx === 0 ? ' active' : '' ?>">
                          <img src="<?= $url ?>" class="d-block mx-auto img-fluid rounded shadow" alt="Zoom Photo <?= $idx+1 ?>" style="max-height:60vh;">
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#zoomCarousel<?= $modal_counter ?>" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#zoomCarousel<?= $modal_counter ?>" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                  </div>
                <?php else: ?>
                  <div class="text-center mb-3" style="font-size:56px;color:#2563eb;">
                    <i class="fa-solid fa-house"></i>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php 
    }
}
?>