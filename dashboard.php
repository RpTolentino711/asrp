<?php
require 'database/database.php'; 
session_start();

// Create an instance of the Database class
$db = new Database();

if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit();
}
$client_id = $_SESSION['client_id'];

// --- CHECK IF CLIENT IS INACTIVE ---
$client_status = $db->getClientStatus($client_id);
if ($client_status && isset($client_status['Status']) && strtolower($client_status['Status']) !== 'active') {
    // Show SweetAlert and log out automatically
    echo <<<HTML
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title>Account Inactive</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
    <script>
      Swal.fire({
        icon: 'warning',
        title: 'Account has been Inactive',
        text: 'Please message the admin for activation.',
        confirmButtonColor: '#d33',
        allowOutsideClick: false,
        allowEscapeKey: false
      }).then(() => {
        window.location.href = 'logout.php?inactive=1';
      });
      setTimeout(function() {
        window.location.href = 'logout.php?inactive=1';
      }, 7000); // Fallback: auto-logout after 7 seconds
    </script>
    </body>
    </html>
    HTML;
    exit();
}

$show_login_success = false;
if (isset($_SESSION['login_success'])) {
    $show_login_success = true;
    unset($_SESSION['login_success']);
}

$feedback_success = '';
if (isset($_POST['submit_feedback'], $_POST['invoice_id'], $_POST['rating'])) {
    $invoice_id = intval($_POST['invoice_id']);
    $rating = intval($_POST['rating']);
    $comments = trim($_POST['comments']);

    if ($db->saveFeedback($invoice_id, $rating, $comments)) {
        $feedback_success = "Thank you for your feedback!";
    }
}

// --- PHOTO UPLOAD/DELETE LOGIC ---
$photo_upload_success = '';
$photo_upload_error = '';
if (isset($_POST['upload_unit_photo'], $_POST['space_id']) && isset($_FILES['unit_photo'])) {
    $space_id = intval($_POST['space_id']);
    $file = $_FILES['unit_photo'];

    // Fetch current photos to enforce limit
    $unit_photos = $db->getUnitPhotosForClient($client_id);
    $current_photos = $unit_photos[$space_id] ?? [];
    if (count($current_photos) >= 5) {
        $photo_upload_error = "You can upload up to 5 photos only.";
    } elseif ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowed_types) && $file['size'] <= 2*1024*1024) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $upload_dir = __DIR__ . "/uploads/unit_photos/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = "unit_{$space_id}_client_{$client_id}_" . uniqid() . "." . $ext;
            $filepath = $upload_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $db->addUnitPhoto($space_id, $client_id, $filename);
                $photo_upload_success = "Photo uploaded for this unit!";
            } else {
                $photo_upload_error = "Failed to move uploaded file.";
            }
        } else {
            $photo_upload_error = "Invalid file type or size too large.";
        }
    } else {
        $photo_upload_error = "File upload error. Please try again.";
    }
}

if (isset($_POST['delete_unit_photo'], $_POST['space_id'], $_POST['photo_filename'])) {
    $space_id = intval($_POST['space_id']);
    $photo_filename = $_POST['photo_filename'];
    $db->deleteUnitPhoto($space_id, $client_id, $photo_filename);
    $file_to_delete = __DIR__ . "/uploads/unit_photos/" . $photo_filename;
    if (file_exists($file_to_delete)) unlink($file_to_delete);
    $photo_upload_success = "Photo deleted!";
}

// --- SAFELY GET CLIENT DETAILS ---
$client_details = $db->getClientDetails($client_id);
if ($client_details && is_array($client_details)) {
    $first_name = $client_details['Client_fn'] ?? '';
    $last_name = $client_details['Client_ln'] ?? '';
    $username = $client_details['C_username'] ?? '';
    $client_display = trim("$first_name $last_name") ?: $username;
} else {
    $client_display = "Unknown User";
}

$feedback_prompts = $db->getFeedbackPrompts($client_id);
$rented_units = $db->getRentedUnits($client_id);

// --- 5. SOLVE THE N+1 PROBLEM ---
$unit_ids = !empty($rented_units) ? array_column($rented_units, 'Space_ID') : [];
$maintenance_history = $db->getMaintenanceHistoryForUnits($unit_ids, $client_id);
// Fetch all unit photos for this client
$unit_photos = $db->getUnitPhotosForClient($client_id); // [space_id => [photo1, photo2, ...] ]
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Client Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .unit-icon { font-size: 2.5rem; color: #198754; margin-bottom: 8px; }
    .unit-photo { width: 100%; max-width: 140px; max-height: 110px; object-fit: cover; border-radius: 8px; margin-bottom: 8px; border: 2px solid #c1e3d5; }
    .maintenance-history { font-size: 0.93rem; background: #f7f7f7; border-radius: 0.35rem; padding: 0.75rem 1rem 0.4rem 1rem; margin-top: 0.7rem; border-left: 4px solid #198754; }
    .maintenance-history h6 { font-size: 1rem; font-weight: 600; margin-bottom: 0.3rem; color: #198754; }
    .maintenance-history ul { padding-left: 1.2rem; margin-bottom: 0.1rem; }
    .maintenance-history li { margin-bottom: 0.2rem; }
    .maintenance-history .no-history { color: #888; font-style: italic; font-size: 0.98em; }
    .photo-thumb { position:relative; display:inline-block; margin-right:7px; margin-bottom:7px; }
    .photo-thumb form { position:absolute; top:2px; right:2px; }
    .photo-thumb .btn { padding:2px 6px; font-size:0.8rem; }
  </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">
      <i class="bi bi-house-door-fill"></i> ASRT Home
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item">
          <a class="nav-link" href="index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="invoice_history.php">Payment</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="handyman_type.php">Handyman</a>
        </li>
        <li class="nav-item ms-lg-3">
          <form action="logout.php" method="post" class="d-inline">
            <button type="submit" class="btn btn-danger btn-sm px-3">Logout</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</nav>
<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Welcome, <?= htmlspecialchars($client_display) ?>!</h2>
  </div>

  <?php if ($photo_upload_success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($photo_upload_success) ?></div>
  <?php endif; ?>
  <?php if ($photo_upload_error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($photo_upload_error) ?></div>
  <?php endif; ?>

  <!-- Feedback for kicked units (Now uses the pre-fetched data) -->
  <?php if ($feedback_prompts): ?>
    <div class="alert alert-warning">
      <i class="fa-solid fa-comment-dots"></i> We value your experience! Please provide feedback for your recently ended rental(s):
    </div>
    <?php foreach ($feedback_prompts as $prompt): ?>
      <div class="card mb-3">
        <div class="card-header">
          Feedback for <?= htmlspecialchars($prompt['SpaceName']) ?> (Invoice Date: <?= htmlspecialchars($prompt['InvoiceDate']) ?>)
        </div>
        <div class="card-body">
          <form method="post" action="">
            <input type="hidden" name="invoice_id" value="<?= $prompt['Invoice_ID'] ?>">
            <div class="mb-2">
              <label class="form-label">Rating</label>
              <select name="rating" class="form-select" required>
                <option value="">Select</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <option value="<?= $i ?>"><?= $i ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Comments</label>
              <textarea name="comments" class="form-control"></textarea>
            </div>
            <button class="btn btn-primary" type="submit" name="submit_feedback">Submit Feedback</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!empty($feedback_success)): ?>
      <div class="alert alert-success"><?= htmlspecialchars($feedback_success) ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Your Rented Units (Now uses the pre-fetched data) -->
  <h4 class="mb-3">Your Rented Units</h4>
  <div class="row">
  <?php if ($rented_units): ?>
    <?php foreach ($rented_units as $rent): 
      $photos = $unit_photos[$rent['Space_ID']] ?? [];
    ?>
      <div class="col-lg-4 col-md-6 mb-4">
        <div class="card border-success shadow">
          <div class="text-center pt-3">
            <i class="fa-solid fa-person-shelter unit-icon"></i>
          </div>
          <div class="card-body">
            <h5><?= htmlspecialchars($rent['Name']) ?></h5>
            <h6>₱<?= number_format($rent['Price'], 0) ?> a month</h6>
            <span class="badge bg-success mb-2"><?= htmlspecialchars($rent['SpaceTypeName']) ?></span>
            <p><?= htmlspecialchars($rent['Street']) ?>, <?= htmlspecialchars($rent['Brgy']) ?>, <?= htmlspecialchars($rent['City']) ?></p>
            <p class="mb-0"><b>Rental Period:</b> <?= htmlspecialchars($rent['StartDate']) ?> to <?= htmlspecialchars($rent['EndDate']) ?></p>

            <!-- Unit Photo Upload/Display -->
            <div class="mt-3 mb-2">
              <?php if ($photos): ?>
                  <div class="mb-2 d-flex flex-wrap">
                    <?php foreach ($photos as $photo): ?>
                      <div class="photo-thumb">
                        <img src="uploads/unit_photos/<?= htmlspecialchars($photo) ?>" class="unit-photo" alt="Unit Photo">
                        <form method="post">
                          <input type="hidden" name="space_id" value="<?= (int)$rent['Space_ID'] ?>">
                          <input type="hidden" name="photo_filename" value="<?= htmlspecialchars($photo) ?>">
                          <button type="submit" name="delete_unit_photo" class="btn btn-danger btn-sm" 
                            onclick="return confirm('Delete this photo?');"><i class="fa fa-times"></i></button>
                        </form>
                      </div>
                    <?php endforeach; ?>
                  </div>
              <?php else: ?>
                  <span class="text-muted mb-2">No photo uploaded for this unit.</span>
              <?php endif; ?>
              <?php if (count($photos) < 5): ?>
              <form method="post" enctype="multipart/form-data" class="d-flex flex-column align-items-start">
                <input type="hidden" name="space_id" value="<?= (int)$rent['Space_ID'] ?>">
                <input type="file" name="unit_photo" accept="image/*" class="form-control mb-2" style="max-width: 220px;" required>
                <button type="submit" name="upload_unit_photo" class="btn btn-outline-success btn-sm">
                  Upload Photo
                </button>
              </form>
              <?php endif; ?>
            </div>

            <div class="maintenance-history mt-3">
              <h6><i class="fa-solid fa-screwdriver-wrench"></i> Maintenance History</h6>
              <?php if (isset($maintenance_history[$rent['Space_ID']])): ?>
                <ul>
                  <?php foreach ($maintenance_history[$rent['Space_ID']] as $mh): ?>
                  <li>
                    <b><?= htmlspecialchars($mh['RequestDate']) ?></b> - 
                    <span class="badge bg-<?= ($mh['Status']=='Completed'?'success':'secondary') ?>"><?= htmlspecialchars($mh['Status']) ?></span>
                  </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="no-history">No maintenance requests yet for this unit.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="col-12 text-center"><div class="alert alert-warning">You have no active rentals.</div></div>
  <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($show_login_success): ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'Login Successful!',
  text: 'Welcome!',
  confirmButtonColor: '#3085d6'
});
</script>
<?php endif; ?>
</body>
</html>