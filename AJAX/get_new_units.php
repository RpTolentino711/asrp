<?php
require '../database/database.php';
session_start();

$db = new Database();

$after_id = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
$is_logged_in = isset($_SESSION['client_id']);
$client_is_inactive = false;
$hide_client_rented_unit_ids = $is_logged_in ? $db->getClientRentedUnitIds($_SESSION['client_id']) : [];

$sql = "SELECT * FROM space WHERE Space_ID > ? AND Flow_Status = 'new' ORDER BY Space_ID ASC";
$stmt = $db->pdo->prepare($sql);
$stmt->execute([$after_id]);
$new_units = $stmt->fetchAll(PDO::FETCH_ASSOC);

$photo_fields = ['Photo', 'Photo1', 'Photo2', 'Photo3', 'Photo4', 'Photo5'];

foreach ($new_units as $space) {
    if (in_array($space['Space_ID'], $hide_client_rented_unit_ids)) continue;
    $photo_urls = [];
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
            <img src="<?= $photo_urls[0] ?>" class="card-img-top" alt="<?= htmlspecialchars($space['Name']) ?>">
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
            <span class="unit-type"><?= htmlspecialchars($space['SpaceType_ID']) ?></span>
            <small class="unit-location"><?= htmlspecialchars($space['City']) ?></small>
          </div>
          <button class="btn btn-accent w-100">
            <i class="bi bi-key me-2"></i>Rent Now
          </button>
        </div>
      </div>
    </div>
    <?php
}
?>