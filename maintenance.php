<?php
require 'database/database.php';
session_start();

try {
    $db = new Database();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

date_default_timezone_set('Asia/Manila');

$is_logged_in = isset($_SESSION['C_username']) && isset($_SESSION['client_id']);

$spaces = [];
$requests = [];
$message = '';
$pending_space_ids = [];
$show_success_modal = false;

if ($is_logged_in) {
    $client_id = $_SESSION['client_id'];

    // --- Handle success message from last submission
    if (isset($_SESSION['maintenance_success'])) {
        $message = "<div class='alert alert-success'>Request submitted successfully!</div>";
        unset($_SESSION['maintenance_success']);
        $show_success_modal = true;
    }

    // --- Handle Form Submission
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_request'])) {
        $space_id = filter_input(INPUT_POST, 'space_id', FILTER_VALIDATE_INT);

        if ($space_id === false || $space_id === null) {
            $message = "<div class='alert alert-danger'>Invalid unit selected. Please try again.</div>";
        } else {
            // Check for pending requests
            if ($db->hasPendingMaintenanceRequest($client_id, $space_id)) {
                $message = "<div class='alert alert-danger'>You already have a pending maintenance request for this unit. Please wait until it is completed.</div>";
            } else {
                // Handle photo upload
                $issue_photo = null;
                if (isset($_FILES['issue_photo']) && $_FILES['issue_photo']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['issue_photo'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    
                    if (!in_array($file['type'], $allowed_types)) {
                        $message = "<div class='alert alert-danger'>Invalid file type for photo. Please upload JPG, PNG, or GIF images only.</div>";
                    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                        $message = "<div class='alert alert-danger'>Photo is too large (max 5MB). Please choose a smaller file.</div>";
                    } else {
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = "issue_" . time() . "_" . rand(1000, 9999) . "." . $ext;
                        $upload_dir = __DIR__ . "/uploads/maintenance_issues/";
                        
                        // Create directory if it doesn't exist
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $filepath = $upload_dir . $filename;
                        if (move_uploaded_file($file['tmp_name'], $filepath)) {
                            $issue_photo = $filename;
                        } else {
                            $message = "<div class='alert alert-danger'>Failed to upload photo. Please try again.</div>";
                        }
                    }
                } else {
                    $message = "<div class='alert alert-danger'>Please upload a photo of the issue.</div>";
                }

                // If photo upload was successful, create the maintenance request
                if ($issue_photo && empty($message)) {
                    if ($db->createMaintenanceRequestWithPhoto($client_id, $space_id, $issue_photo)) {
                        $_SESSION['maintenance_success'] = true;
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        error_log("Failed to create maintenance request for client_id: {$client_id}, space_id: {$space_id}");
                        $message = "<div class='alert alert-danger'>Failed to submit request. Please try again.</div>";
                    }
                }
            }
        }
    }

    // --- Fetch Data
    $spaces = $db->getClientSpacesForMaintenance($client_id);
    $requests = $db->getClientMaintenanceHistory($client_id);

    foreach ($requests as $req) {
        if (in_array($req['Status'], ['Submitted', 'In Progress'])) {
            $pending_space_ids[] = $req['Space_ID'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Center - ASRT Commercial Spaces</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php require('links.php'); ?>

    <style>
        /* Modern Alert Styles */
        .modern-alert {
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.5rem;
            animation: fadeIn 0.5s ease-out;
            font-weight: 500;
        }
        .modern-alert.alert-success {
            color: #0f5132;
            background-color: #d1e7dd;
            border-color: #badbcc;
        }
        .modern-alert.alert-danger {
            color: #842029;
            background-color: #f8d7da;
            border-color: #f5c2c7;
        }
        .modern-alert.alert-warning {
            color: #664d03;
            background-color: #fff3cd;
            border-color: #ffecb5;
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-block;
            padding: .35em .65em;
            font-size: .75em;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: .375rem;
        }
        .status-submitted { background-color: #0d6efd; }
        .status-in-progress { background-color: #ffc107; color: #343a40;}
        .status-completed { background-color: #198754; }
        .status-cancelled { background-color: #dc3545; }
        .status-pending { background-color: #6c757d; }

        /* Photo Upload Styles */
        .photo-upload-container {
            border: 2px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
            position: relative;
            margin-bottom: 1rem;
        }

        .photo-upload-container:hover {
            border-color: var(--primary);
            background: rgba(0, 123, 255, 0.05);
        }

        .photo-upload-container.dragover {
            border-color: var(--primary);
            background: rgba(0, 123, 255, 0.1);
        }

        .photo-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
        }

        .photo-upload-label {
            cursor: pointer;
            display: block;
            position: relative;
            z-index: 5;
        }

        .photo-upload-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .photo-info {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .photo-preview-container {
            margin-top: 1rem;
            text-align: center;
        }

        .photo-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 0.5rem;
            border: 2px solid var(--primary);
            margin-bottom: 0.5rem;
        }

        /* Request Photos */
        .request-photos {
            margin-top: 1rem;
        }

        .photo-thumbnail {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .photo-thumbnail:hover {
            transform: scale(1.1);
        }

        /* Modal for photo viewing */
        .photo-modal-img {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
        }

        /* Other existing styles */
        .maintenance-header {
            background-color: var(--primary);
            color: white;
            padding: 4rem 0;
            margin-bottom: 2rem;
        }
        .maintenance-header h1 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .maintenance-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .main-content {
            padding-bottom: 3rem;
        }
        .request-form-section, .history-section, .no-units-alert, .login-required-section {
            margin-bottom: 3rem;
        }
        .request-card, .history-card {
            background-color: #fff;
            border-radius: 0.75rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        .request-card-header, .history-card-header {
            background-color: var(--light);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
        }
        .request-card-header h5, .history-card-header h5 {
            margin-bottom: 0;
            font-weight: 600;
            color: var(--dark);
        }
        .request-card-header i, .history-card-header i {
            margin-right: 0.75rem;
            color: var(--primary);
        }
        .request-card-body {
            padding: 1.5rem;
        }
        .form-select {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border-color: #dee2e6;
        }
        .form-label {
            font-weight: 600;
            color: #343a40;
            margin-bottom: 0.75rem;
        }
        .form-label i {
            margin-right: 0.5rem;
            color: var(--secondary);
        }
        .submit-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            width: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .submit-btn:hover {
            background-color: var(--dark-primary);
            color: white;
        }
        .submit-btn i {
            margin-right: 0.5rem;
        }
        .submit-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .pending-warning {
            color: #dc3545;
            font-size: 0.9em;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
        }
        .pending-warning i {
            margin-right: 0.5rem;
        }
        .history-table-container {
            overflow-x: auto;
            padding: 0 1.5rem 1.5rem;
        }
        .history-table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        .history-table th, .history-table td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
            border-top: 1px solid #e9ecef;
            white-space: nowrap;
        }
        .history-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #343a40;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            color: var(--light);
        }
        .empty-state h5 {
            font-weight: 600;
            color: #343a40;
            margin-bottom: 0.5rem;
        }

        .no-units-alert {
            text-align: center;
            padding: 3rem;
            background-color: #f8f9fa;
            border-radius: 0.75rem;
            border: 1px dashed #ced4da;
            color: #6c757d;
            margin-top: 2rem;
        }
        .no-units-icon {
            font-size: 4rem;
            color: var(--secondary);
            margin-bottom: 1.5rem;
        }

        .login-required-section {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 40vh;
        }
        .login-card {
            text-align: center;
            padding: 3rem;
            background-color: #fff;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.1);
        }
        .lock-icon {
            font-size: 4rem;
            color: var(--info);
            margin-bottom: 1.5rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-on-scroll {
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
        }

        :root {
            --primary: #007bff;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --dark-primary: #0056b3;
        }
    </style>
</head>
<body>
    <?php require('header.php'); ?>

    <div class="main-content">
        <!-- Header Section -->
        <section class="maintenance-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1>Maintenance Center</h1>
                        <p>Request maintenance services for your commercial space and track progress</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="d-flex align-items-center justify-content-md-end">
                            <i class="bi bi-tools fs-1 me-3"></i>
                            <div>
                                <small class="opacity-75">Professional Service</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container">
            <!-- Display Messages -->
            <?php if (!empty($message)): ?>
                <div class="animate-on-scroll">
                    <?= str_replace(
                        ['alert-success', 'alert-danger', 'alert-warning'],
                        ['modern-alert alert-success', 'modern-alert alert-danger', 'modern-alert alert-warning'],
                        $message
                    ) ?>
                </div>
            <?php endif; ?>

            <?php if ($is_logged_in): ?>
                <?php if (!empty($spaces)): ?>
                    <!-- Request Form Section -->
                    <div class="request-form-section animate-on-scroll">
                        <div class="request-card">
                            <div class="request-card-header">
                                <h5><i class="bi bi-plus-circle"></i> Submit New Maintenance Request</h5>
                            </div>
                            <div class="request-card-body">
                                <form method="post" id="maintenanceForm" enctype="multipart/form-data">
                                    <div class="mb-4">
                                        <label class="form-label">
                                            <i class="bi bi-building"></i> Select Your Unit
                                        </label>
                                        <select name="space_id" id="space_id" class="form-select" required>
                                            <option value="">Choose your commercial space...</option>
                                            <?php foreach ($spaces as $space): ?>
                                                <?php $is_pending = in_array($space['Space_ID'], $pending_space_ids); ?>
                                                <option value="<?= htmlspecialchars($space['Space_ID']) ?>"
                                                    <?= $is_pending ? 'data-pending="1"' : '' ?>>
                                                    <?= htmlspecialchars($space['Name']) ?>
                                                    <?= $is_pending ? ' (Active Request)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="pending-warning" id="pendingInfo" style="display:none;">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            You already have an active request for this unit. Please wait for completion.
                                        </div>
                                    </div>

                                    <!-- Photo Upload Section -->
                                    <div class="mb-4">
                                        <label class="form-label">
                                            <i class="bi bi-camera"></i> Photo of the Issue (Required)
                                        </label>
                                        <div class="photo-upload-container" id="photoUploadContainer">
                                            <input type="file" name="issue_photo" id="issue_photo" class="photo-input" 
                                                   accept="image/*" required onchange="handlePhotoSelect(this)">
                                            <label for="issue_photo" class="photo-upload-label">
                                                <div class="photo-upload-icon">
                                                    <i class="bi bi-cloud-upload"></i>
                                                </div>
                                                <h5>Click to upload photo</h5>
                                                <p class="text-muted mb-2">or drag and drop</p>
                                                <p class="photo-info">JPG, PNG, GIF up to 5MB</p>
                                                
                                                <!-- Image Preview -->
                                                <div id="photoPreview" class="photo-preview-container" style="display: none;">
                                                    <img id="previewImage" class="photo-preview" src="" alt="Preview">
                                                    <p class="text-success mb-0" id="photoFileName"></p>
                                                </div>
                                            </label>
                                        </div>
                                        <small class="text-muted">Please provide a clear photo of the issue that needs to be fixed.</small>
                                    </div>

                                    <button type="submit" name="submit_request" class="submit-btn" id="submitBtn">
                                        <i class="bi bi-send"></i> Submit Maintenance Request
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Units Alert -->
                    <div class="no-units-alert animate-on-scroll">
                        <i class="bi bi-house-x no-units-icon"></i>
                        <h4 class="fw-bold mb-3">No Units Available</h4>
                        <p class="mb-0">You currently do not have any rented units.</p>
                    </div>
                <?php endif; ?>

                <!-- History Section -->
                <div class="history-section animate-on-scroll">
                    <div class="history-card">
                        <div class="history-card-header">
                            <h5><i class="bi bi-clock-history"></i> My Maintenance History</h5>
                        </div>
                        <div class="p-0">
                            <?php if (!empty($requests)): ?>
                                <div class="history-table-container">
                                    <table class="table history-table">
                                        <thead>
                                            <tr>
                                                <th>Request Date</th>
                                                <th>Unit</th>
                                                <th>Status</th>
                                                <th>Issue Photo</th>
                                                <th>Completion Photo</th>
                                                <th>Last Updated</th>
                                                <th>Handyman</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requests as $req): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($req['RequestDate']))) ?></td>
                                                    <td><strong><?= htmlspecialchars($req['SpaceName']) ?></strong></td>
                                                    <td>
                                                        <?php
                                                        $status_class = 'status-' . strtolower(str_replace(' ', '-', $req['Status']));
                                                        ?>
                                                        <span class="status-badge <?= $status_class ?>">
                                                            <?= htmlspecialchars($req['Status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($req['IssuePhoto'])): ?>
                                                            <img src="uploads/maintenance_issues/<?= htmlspecialchars($req['IssuePhoto']) ?>" 
                                                                 class="photo-thumbnail" 
                                                                 data-bs-toggle="modal" 
                                                                 data-bs-target="#photoModal"
                                                                 data-photo="uploads/maintenance_issues/<?= htmlspecialchars($req['IssuePhoto']) ?>"
                                                                 alt="Issue Photo">
                                                        <?php else: ?>
                                                            <span class="text-muted">No photo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($req['CompletionPhoto'])): ?>
                                                            <img src="uploads/maintenance_completions/<?= htmlspecialchars($req['CompletionPhoto']) ?>" 
                                                                 class="photo-thumbnail" 
                                                                 data-bs-toggle="modal" 
                                                                 data-bs-target="#photoModal"
                                                                 data-photo="uploads/maintenance_completions/<?= htmlspecialchars($req['CompletionPhoto']) ?>"
                                                                 alt="Completion Photo">
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($req['LastStatusDate']) ?></td>
                                                    <td>
                                                        <?php if (!empty($req['Handyman_fn'])): ?>
                                                            <?= htmlspecialchars($req['Handyman_fn'] . ' ' . $req['Handyman_ln']) ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not assigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-clipboard-x"></i>
                                    <h5>No Maintenance Requests</h5>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Login Required Section -->
                <div class="login-required-section animate-on-scroll">
                    <div class="login-card">
                        <div class="lock-icon"><i class="bi bi-shield-lock"></i></div>
                        <h2 class="fw-bold text-primary mb-3">Login Required</h2>
                        <p class="text-muted mb-4">Please log in to request maintenance.</p>
                        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="bi bi-person me-2"></i>Login to Continue
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Photo View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" class="photo-modal-img" src="" alt="Photo">
                </div>
            </div>
        </div>
    </div>

    <?php require('footer.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Success modal
            <?php if ($show_success_modal): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Request Submitted!',
                    text: 'Your maintenance request has been submitted successfully. Our team will review it shortly.',
                    confirmButtonColor: 'var(--success)'
                });
            <?php endif; ?>

            // Pending unit warning
            const spaceSelect = document.getElementById('space_id');
            const pendingInfo = document.getElementById('pendingInfo');
            const submitBtn = document.getElementById('submitBtn');

            if (spaceSelect && pendingInfo && submitBtn) {
                const selectedOption = spaceSelect.options[spaceSelect.selectedIndex];
                if (selectedOption && selectedOption.dataset.pending === "1") {
                    pendingInfo.style.display = "block";
                    submitBtn.disabled = true;
                } else {
                    pendingInfo.style.display = "none";
                    submitBtn.disabled = false;
                }

                spaceSelect.addEventListener('change', function() {
                    const selected = spaceSelect.options[spaceSelect.selectedIndex];
                    if (selected && selected.dataset.pending === "1") {
                        pendingInfo.style.display = "block";
                        submitBtn.disabled = true;
                    } else {
                        pendingInfo.style.display = "none";
                        submitBtn.disabled = false;
                    }
                });
            }

            // Photo modal functionality
            const photoModal = document.getElementById('photoModal');
            if (photoModal) {
                photoModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const photoSrc = button.getAttribute('data-photo');
                    const modalImage = document.getElementById('modalPhoto');
                    modalImage.src = photoSrc;
                });
            }

            // Drag and drop functionality for photo upload
            const photoUploadContainer = document.getElementById('photoUploadContainer');
            if (photoUploadContainer) {
                const photoInput = document.getElementById('issue_photo');
                
                photoUploadContainer.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    photoUploadContainer.classList.add('dragover');
                });

                photoUploadContainer.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    photoUploadContainer.classList.remove('dragover');
                });

                photoUploadContainer.addEventListener('drop', (e) => {
                    e.preventDefault();
                    photoUploadContainer.classList.remove('dragover');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        photoInput.files = files;
                        handlePhotoSelect(photoInput);
                    }
                });
            }
        });

        function handlePhotoSelect(input) {
            const preview = document.getElementById('photoPreview');
            const fileName = document.getElementById('photoFileName');
            const previewImage = document.getElementById('previewImage');
            const photoUploadContainer = document.getElementById('photoUploadContainer');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPEG, PNG, GIF).');
                    input.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB.');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    fileName.textContent = file.name;
                    preview.style.display = 'block';
                    photoUploadContainer.style.borderColor = '#28a745';
                    photoUploadContainer.style.background = 'rgba(40, 167, 69, 0.05)';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>