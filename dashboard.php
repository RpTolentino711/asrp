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
  <title>Client Dashboard - ASRT Home</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- CSS Libraries -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <style>
    :root {
      --primary-color: #3b82f6;
      --primary-dark: #2563eb;
      --primary-light: #dbeafe;
      --secondary-color: #64748b;
      --success-color: #10b981;
      --danger-color: #ef4444;
      --warning-color: #f59e0b;
      --dark-color: #1e293b;
      --light-gray: #f8fafc;
      --border-color: #e2e8f0;
      --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1);
      --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
      --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
      --border-radius: 12px;
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      color: var(--dark-color);
      line-height: 1.6;
      min-height: 100vh;
    }

    /* Navigation */
    .navbar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border-color);
      box-shadow: var(--shadow-sm);
      padding: 1rem 0;
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    .navbar-brand {
      font-weight: 700;
      font-size: 1.5rem;
      color: var(--primary-color) !important;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .navbar-brand i {
      font-size: 1.8rem;
    }

    .nav-link {
      color: var(--secondary-color) !important;
      font-weight: 500;
      font-size: 0.95rem;
      padding: 0.75rem 1.25rem !important;
      border-radius: var(--border-radius);
      transition: var(--transition);
      position: relative;
    }

    .nav-link:hover,
    .nav-link.active {
      color: var(--primary-color) !important;
      background: var(--primary-light);
      transform: translateY(-1px);
    }

    .btn-logout {
      background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
      border: none;
      color: white;
      padding: 0.75rem 1.5rem;
      border-radius: var(--border-radius);
      font-weight: 500;
      font-size: 0.9rem;
      transition: var(--transition);
      box-shadow: var(--shadow-sm);
    }

    .btn-logout:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    /* Main Content */
    .main-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 2rem 1rem;
    }

    .page-header {
      margin-bottom: 2.5rem;
      text-align: center;
    }

    .page-title {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--dark-color);
      margin-bottom: 0.5rem;
    }

    .page-subtitle {
      color: var(--secondary-color);
      font-size: 1.1rem;
    }

    /* Cards */
    .card {
      background: white;
      border: none;
      border-radius: var(--border-radius);
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
      overflow: hidden;
      height: 100%;
    }

    .card:hover {
      box-shadow: var(--shadow-lg);
      transform: translateY(-4px);
    }

    .card-header-custom {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
      color: white;
      padding: 1.25rem;
      font-weight: 600;
      font-size: 1.1rem;
      border: none;
    }

    .card-body {
      padding: 1.5rem;
    }

    /* Unit Cards */
    .unit-card {
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .unit-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--primary-color), var(--success-color));
    }

    .unit-icon {
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
    }

    .unit-icon i {
      font-size: 1.5rem;
      color: var(--primary-color);
    }

    .unit-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--dark-color);
      margin-bottom: 0.5rem;
    }

    .unit-price {
      font-size: 1.1rem;
      font-weight: 600;
      color: var(--success-color);
      margin-bottom: 0.75rem;
    }

    .unit-type-badge {
      background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
      color: var(--primary-dark);
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 500;
      display: inline-block;
      margin-bottom: 1rem;
    }

    .unit-address {
      color: var(--secondary-color);
      font-size: 0.9rem;
      margin-bottom: 0.5rem;
    }

    .unit-period {
      color: var(--secondary-color);
      font-size: 0.9rem;
      margin-bottom: 1.5rem;
    }

    /* Photo Section */
    .photo-section {
      background: var(--light-gray);
      border-radius: var(--border-radius);
      padding: 1.25rem;
      margin-bottom: 1.5rem;
    }

    .photo-section-title {
      font-size: 1rem;
      font-weight: 600;
      color: var(--dark-color);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .photo-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 0.75rem;
      margin-bottom: 1rem;
    }

    .photo-item {
      position: relative;
      aspect-ratio: 1;
      border-radius: var(--border-radius);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }

    .photo-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: var(--transition);
    }

    .photo-item:hover img {
      transform: scale(1.05);
    }

    .photo-delete-btn {
      position: absolute;
      top: 0.5rem;
      right: 0.5rem;
      width: 28px;
      height: 28px;
      background: rgba(255, 255, 255, 0.9);
      border: none;
      border-radius: 50%;
      color: var(--danger-color);
      font-size: 0.8rem;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: var(--transition);
      cursor: pointer;
    }

    .photo-item:hover .photo-delete-btn {
      opacity: 1;
    }

    .photo-upload-form {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .file-input {
      border: 2px dashed var(--border-color);
      border-radius: var(--border-radius);
      padding: 1rem;
      text-align: center;
      transition: var(--transition);
      cursor: pointer;
    }

    .file-input:hover {
      border-color: var(--primary-color);
      background: var(--primary-light);
    }

    .file-input input[type="file"] {
      display: none;
    }

    .upload-btn {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
      border: none;
      color: white;
      padding: 0.75rem 1.5rem;
      border-radius: var(--border-radius);
      font-weight: 500;
      transition: var(--transition);
      align-self: flex-start;
    }

    .upload-btn:hover {
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
    }

    /* Maintenance History */
    .maintenance-section {
      background: var(--light-gray);
      border-radius: var(--border-radius);
      padding: 1.25rem;
      border-left: 4px solid var(--primary-color);
    }

    .maintenance-title {
      font-size: 1rem;
      font-weight: 600;
      color: var(--dark-color);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .maintenance-list {
      list-style: none;
      padding: 0;
    }

    .maintenance-item {
      background: white;
      padding: 0.75rem 1rem;
      border-radius: var(--border-radius);
      margin-bottom: 0.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--shadow-sm);
    }

    .maintenance-date {
      font-weight: 500;
      color: var(--dark-color);
    }

    .maintenance-status {
      padding: 0.25rem 0.75rem;
      border-radius: 15px;
      font-size: 0.8rem;
      font-weight: 500;
      background: var(--primary-light);
      color: var(--primary-dark);
    }

    .no-maintenance {
      color: var(--secondary-color);
      font-style: italic;
      text-align: center;
      padding: 1rem;
    }

    /* Feedback Section */
    .feedback-section {
      background: linear-gradient(135deg, var(--warning-color) 0%, #f97316 100%);
      color: white;
      padding: 1.5rem;
      border-radius: var(--border-radius);
      margin-bottom: 2rem;
    }

    .feedback-card {
      background: white;
      border-radius: var(--border-radius);
      overflow: hidden;
      margin-bottom: 1rem;
    }

    .feedback-header {
      background: var(--primary-color);
      color: white;
      padding: 1rem 1.25rem;
      font-weight: 600;
    }

    .feedback-form {
      padding: 1.5rem;
    }

    .form-group {
      margin-bottom: 1.25rem;
    }

    .form-label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
      color: var(--dark-color);
    }

    .form-control,
    .form-select {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 2px solid var(--border-color);
      border-radius: var(--border-radius);
      font-size: 0.95rem;
      transition: var(--transition);
    }

    .form-control:focus,
    .form-select:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px var(--primary-light);
    }

    .submit-btn {
      background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
      border: none;
      color: white;
      padding: 0.75rem 1.5rem;
      border-radius: var(--border-radius);
      font-weight: 500;
      transition: var(--transition);
    }

    .submit-btn:hover {
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
    }

    /* Alerts */
    .alert {
      border: none;
      border-radius: var(--border-radius);
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      font-weight: 500;
    }

    .alert-success {
      background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
      color: var(--success-color);
      border-left: 4px solid var(--success-color);
    }

    .alert-danger {
      background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
      color: var(--danger-color);
      border-left: 4px solid var(--danger-color);
    }

    .alert-warning {
      background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
      color: var(--warning-color);
      border-left: 4px solid var(--warning-color);
    }

    .empty-state {
      text-align: center;
      padding: 3rem 2rem;
      color: var(--secondary-color);
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      opacity: 0.5;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .main-container {
        padding: 1rem;
      }

      .page-title {
        font-size: 2rem;
      }

      .card-body {
        padding: 1rem;
      }

      .photo-section,
      .maintenance-section {
        padding: 1rem;
      }

      .navbar-nav {
        background: white;
        padding: 1rem;
        border-radius: var(--border-radius);
        margin-top: 1rem;
        box-shadow: var(--shadow-md);
      }

      .photo-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 0.5rem;
      }
    }

    @media (max-width: 576px) {
      .page-title {
        font-size: 1.75rem;
      }

      .unit-card {
        margin-bottom: 1rem;
      }

      .photo-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    /* Loading Animation */
    .loading {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Smooth Scrolling */
    html {
      scroll-behavior: smooth;
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: var(--light-gray);
    }

    ::-webkit-scrollbar-thumb {
      background: var(--border-color);
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: var(--secondary-color);
    }
  </style>
</head>
<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg">
    <div class="container-fluid px-4">
      <a class="navbar-brand" href="index.php">
        <i class="bi bi-house-door-fill"></i>
        ASRT Home
      </a>
      
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto align-items-lg-center">
          <li class="nav-item">
            <a class="nav-link active" href="index.php">
              <i class="bi bi-house-door me-2"></i>Home
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="invoice_history.php">
              <i class="bi bi-receipt me-2"></i>Payment
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="handyman_type.php">
              <i class="bi bi-tools me-2"></i>Handyman
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="maintenance.php">
              <i class="bi bi-gear me-2"></i>Maintenance
            </a>
          </li>
          <li class="nav-item ms-lg-3">
            <form action="logout.php" method="post" class="d-inline">
              <button type="submit" class="btn btn-logout">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
              </button>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="main-container">
    <!-- Page Header -->
    <div class="page-header">
      <h1 class="page-title">Welcome back, <?= htmlspecialchars($client_display) ?>!</h1>
      <p class="page-subtitle">Manage your rental properties and stay updated with your account</p>
    </div>

    <!-- Alerts -->
    <?php if ($photo_upload_success): ?>
      <div class="alert alert-success">
        <i class="bi bi-check-circle me-2"></i>
        <?= htmlspecialchars($photo_upload_success) ?>
      </div>
    <?php endif; ?>

    <?php if ($photo_upload_error): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle me-2"></i>
        <?= htmlspecialchars($photo_upload_error) ?>
      </div>
    <?php endif; ?>

    <!-- Feedback Section -->
    <?php if ($feedback_prompts): ?>
      <div class="feedback-section">
        <h4 class="mb-3">
          <i class="bi bi-chat-heart me-2"></i>
          Your Feedback Matters!
        </h4>
        <p class="mb-0">We value your experience! Please provide feedback for your recently ended rental(s):</p>
      </div>

      <?php foreach ($feedback_prompts as $prompt): ?>
        <div class="feedback-card">
          <div class="feedback-header">
            <i class="bi bi-building me-2"></i>
            Feedback for <?= htmlspecialchars($prompt['SpaceName']) ?>
            <small class="ms-2 opacity-75">(<?= htmlspecialchars($prompt['InvoiceDate']) ?>)</small>
          </div>
          <div class="feedback-form">
            <form method="post" action="">
              <input type="hidden" name="invoice_id" value="<?= $prompt['Invoice_ID'] ?>">
              
              <div class="form-group">
                <label class="form-label">
                  <i class="bi bi-star me-1"></i>Rating
                </label>
                <select name="rating" class="form-select" required>
                  <option value="">Select your rating</option>
                  <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>">
                      <?= $i ?> Star<?= $i > 1 ? 's' : '' ?> 
                      <?= str_repeat('⭐', $i) ?>
                    </option>
                  <?php endfor; ?>
                </select>
              </div>
              
              <div class="form-group">
                <label class="form-label">
                  <i class="bi bi-chat-text me-1"></i>Comments
                </label>
                <textarea name="comments" class="form-control" rows="4" placeholder="Share your experience with us..."></textarea>
              </div>
              
              <button class="submit-btn" type="submit" name="submit_feedback">
                <i class="bi bi-send me-2"></i>Submit Feedback
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!empty($feedback_success)): ?>
        <div class="alert alert-success">
          <i class="bi bi-heart me-2"></i>
          <?= htmlspecialchars($feedback_success) ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Rented Units Section -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h2 class="h3 mb-0 fw-bold">
        <i class="bi bi-buildings me-2 text-primary"></i>
        Your Rented Properties
      </h2>
      <?php if ($rented_units): ?>
        <span class="badge bg-primary fs-6"><?= count($rented_units) ?> Active</span>
      <?php endif; 
      ?>