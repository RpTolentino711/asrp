<?php
require 'database/database.php';
session_start();

$db = new Database();

$logged_in = isset($_SESSION['client_id']);
$client_id = $logged_in ? $_SESSION['client_id'] : null;

if (!$logged_in) {
    $show_login_modal = true;
}

// Handle form submission with POST-redirect-GET pattern to prevent duplicate submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logged_in && isset($_POST['space_id'], $_POST['start_date'], $_POST['confirm_price'])) {
    $space_id = intval($_POST['space_id']);
    $start_date = $_POST['start_date'];
    
    // Calculate end date as 1 month from start date
    $end_date = date('Y-m-d', strtotime($start_date . ' +1 month'));

    if ($db->createRentalRequest($client_id, $space_id, $start_date, $end_date)) {
        // Redirect to prevent form resubmission on page reload
        header("Location: " . $_SERVER['PHP_SELF'] . "?space_id=" . $_GET['space_id'] . "&success=1");
        exit();
    }
}

// Check for success message from redirect
$success = "";
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "Your rental request has been sent to the admin!";
}

$id = $_GET["space_id"];
$available_units = $db->getAvailableUnitsForRental($id);

// Get pending requests for this client
$pending_requests = [];
if ($logged_in) {
    $pending_requests = $db->getPendingRequestsByClient($client_id);
}

// Create an array of space IDs that have pending requests
$pending_space_ids = [];
foreach ($pending_requests as $request) {
    $pending_space_ids[] = $request['Space_ID'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rental Request - ASRT Commercial Spaces</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary: #0f172a;
            --accent: #ef4444;
            --accent-light: #f87171;
            --success: #059669;
            --warning: #d97706;
            --light: #f8fafc;
            --lighter: #ffffff;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --gray-dark: #334155;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
            --shadow-xl: 0 16px 40px rgba(0, 0, 0, 0.15);
            --border-radius: 16px;
            --border-radius-sm: 8px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light);
            color: var(--secondary);
            line-height: 1.6;
            padding-top: 2rem;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Header Section */
        .rental-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 3rem 0 2rem;
            margin: -2rem -1rem 3rem;
            position: relative;
            overflow: hidden;
        }

        .rental-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="buildings" patternUnits="userSpaceOnUse" width="50" height="50"><rect x="10" y="20" width="8" height="20" fill="white" opacity="0.1"/><rect x="25" y="15" width="6" height="25" fill="white" opacity="0.1"/><rect x="35" y="18" width="7" height="22" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23buildings)"/></svg>');
            opacity: 0.2;
        }

        .rental-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .rental-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
            position: relative;
            z-index: 2;
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0 0 2rem 0;
            position: relative;
            z-index: 2;
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: white;
        }

        .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.6);
        }

        /* Success Alert */
        .success-alert {
            background: linear-gradient(135deg, #d1fae5 0%, #86efac 100%);
            color: #065f46;
            border: 1px solid #86efac;
            border-radius: var(--border-radius-sm);
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }

        .success-alert i {
            font-size: 1.25rem;
            margin-right: 1rem;
        }

        /* Pending Requests Alert */
        .pending-alert {
            background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 100%);
            color: #78350f;
            border: 1px solid #fbbf24;
            border-radius: var(--border-radius-sm);
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .pending-alert h5 {
            margin: 0 0 0.5rem 0;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .pending-alert i {
            margin-right: 0.5rem;
        }

        .pending-requests-list {
            margin: 0.5rem 0 0 0;
            padding: 0;
            list-style: none;
        }

        .pending-requests-list li {
            background: rgba(255, 255, 255, 0.5);
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: var(--border-radius-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pending-unit-name {
            font-weight: 600;
        }

        .pending-status {
            background: var(--warning);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Login Required Section */
        .login-required {
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
        }

        .login-card {
            background: var(--lighter);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            padding: 3rem 2rem;
            border: none;
        }

        .login-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 2rem;
        }

        .login-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .login-description {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* Rental Cards */
        .rental-card {
            background: var(--lighter);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-light);
            overflow: hidden;
            transition: var(--transition);
            height: 100%;
            position: relative;
        }

        .rental-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }

        .rental-card.pending-request {
            opacity: 0.6;
            border-color: var(--warning);
            background: linear-gradient(135deg, rgba(217, 119, 6, 0.05) 0%, rgba(251, 191, 36, 0.05) 100%);
        }

        .rental-card.pending-request:hover {
            transform: none;
            box-shadow: var(--shadow-lg);
        }

        .rental-card-header {
            background: linear-gradient(135deg, var(--light) 0%, var(--lighter) 100%);
            padding: 1.5rem 2rem 1rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .rental-card.pending-request .rental-card-header {
            background: linear-gradient(135deg, rgba(217, 119, 6, 0.1) 0%, rgba(251, 191, 36, 0.1) 100%);
        }

        .rental-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 1rem;
        }

        .pending-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--warning);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 10;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .rental-info {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            color: var(--gray-dark);
        }

        .info-item i {
            color: var(--primary);
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        .price-highlight {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success);
        }

        .rental-card-body {
            padding: 2rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }

        .form-label i {
            margin-right: 0.5rem;
            color: var(--primary);
        }

        .form-control {
            background: var(--lighter);
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius-sm);
            padding: 0.875rem 1rem;
            font-size: 1rem;
            transition: var(--transition);
            color: var(--secondary);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
            outline: none;
        }

        .form-control:disabled {
            background: var(--gray-light);
            opacity: 0.6;
        }

        .rental-notes {
            background: var(--light);
            border-radius: var(--border-radius-sm);
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }

        .rental-notes p {
            color: var(--gray-dark);
            font-size: 0.9rem;
            margin: 0;
            font-style: italic;
        }

        .request-btn {
            background: linear-gradient(135deg, var(--success) 0%, #10b981 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .request-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        .request-btn:disabled {
            background: var(--gray-light);
            color: var(--gray);
            cursor: not-allowed;
            transform: none;
        }

        .request-btn i {
            margin-right: 0.5rem;
        }

        .pending-message {
            text-align: center;
            padding: 2rem;
            color: var(--warning);
            font-weight: 600;
        }

        .pending-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        /* Due date display */
        .due-date-display {
            background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 100%);
            border: 1px solid #0ea5e9;
            border-radius: var(--border-radius-sm);
            padding: 1rem;
            margin: 1rem 0;
            text-align: center;
        }

        .due-date-display strong {
            color: var(--primary);
            font-size: 1.1rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        .empty-state h4 {
            color: var(--gray-dark);
            margin-bottom: 1rem;
        }

        /* Navigation */
        .navigation-section {
            text-align: center;
            margin-top: 3rem;
            padding: 2rem 0;
        }

        .nav-btn {
            background: var(--lighter);
            color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: var(--border-radius-sm);
            padding: 0.875rem 2rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
        }

        .nav-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .nav-btn i {
            margin-right: 0.5rem;
        }

        /* Modal Improvements */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--light) 0%, var(--lighter) 100%);
            border-bottom: 1px solid var(--gray-light);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            padding: 1.5rem 2rem;
        }

        .modal-title {
            font-weight: 600;
            color: var(--secondary);
        }

        .modal-body {
            padding: 2rem;
        }

        .confirmation-text {
            color: var(--gray-dark);
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .price-display {
            background: var(--light);
            border-radius: var(--border-radius-sm);
            padding: 1rem 1.5rem;
            margin: 1rem 0;
            text-align: center;
            border-left: 4px solid var(--success);
        }

        .price-display .price-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success);
        }

        .modal-footer {
            border-top: 1px solid var(--gray-light);
            padding: 1.5rem 2rem;
        }

        .confirm-btn {
            background: linear-gradient(135deg, var(--success) 0%, #10b981 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .confirm-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        .cancel-btn {
            background: var(--lighter);
            color: var(--gray-dark);
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .cancel-btn:hover {
            background: var(--light);
            border-color: var(--gray);
        }

        /* Login Modal Specific */
        .login-modal .modal-body {
            padding: 2rem;
        }

        .login-form-control {
            background: var(--lighter);
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius-sm);
            padding: 0.875rem 1rem;
            transition: var(--transition);
        }

        .login-form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .login-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 0.875rem;
            font-weight: 600;
            width: 100%;
            transition: var(--transition);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        /* Animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease-out;
        }

        .animate-on-scroll.animate {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .rental-header {
                padding: 2rem 0 1.5rem;
                margin: -2rem -1rem 2rem;
            }

            .rental-header h1 {
                font-size: 2rem;
            }

            .rental-card-header,
            .rental-card-body {
                padding: 1.5rem;
            }

            .login-card {
                padding: 2rem 1.5rem;
            }

            .modal-body {
                padding: 1.5rem;
            }

            .pending-requests-list li {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .rental-header h1 {
                font-size: 1.75rem;
            }

            .rental-card {
                margin-bottom: 1.5rem;
            }

            .rental-card-header,
            .rental-card-body {
                padding: 1rem;
            }

            .login-card {
                padding: 1.5rem 1rem;
            }

            .login-title {
                font-size: 1.5rem;
            }
        }

        /* Loading states */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="main-container">
        <!-- Header Section -->
        <section class="rental-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Rental Request</li>
                    </ol>
                </nav>

                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1>Request to Rent a Unit</h1>
                        <p>Choose your perfect commercial space and submit your rental application</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="d-flex align-items-center justify-content-md-end">
                            <i class="bi bi-key fs-1 me-3"></i>
                            <div>
                                <div class="fw-bold">Rental Application</div>
                                <small class="opacity-75">Secure Process</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Success Message -->
        <?php if (!empty($success)): ?>
            <div class="success-alert animate-on-scroll">
                <i class="bi bi-check-circle"></i>
                <div>
                    <strong>Request Submitted Successfully!</strong><br>
                    <?= htmlspecialchars($success) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Pending Requests Alert - Only show if there are pending requests -->
        <?php if ($logged_in && !empty($pending_requests)): ?>
            <div class="pending-alert animate-on-scroll">
                <h5><i class="bi bi-clock-history"></i>You have pending rental requests</h5>
                <p>The units you have requested are temporarily disabled until your requests are processed.</p>
                <ul class="pending-requests-list">
                    <?php foreach ($pending_requests as $request): ?>
                        <li>
                            <div>
                                <span class="pending-unit-name"><?= htmlspecialchars($request['Name']) ?></span>
                                <small class="text-muted d-block">Submitted: <?= date('M j, Y', strtotime($request['RequestDate'])) ?></small>
                            </div>
                            <span class="pending-status">PENDING</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$logged_in): ?>
            <!-- Login Required Section -->
            <div class="login-required animate-on-scroll">
                <div class="login-card">
                    <div class="login-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h2 class="login-title">Login Required</h2>
                    <p class="login-description">
                        Please log in to your account to submit a rental request. If you don't have an account, 
                        you can register from our homepage.
                    </p>
                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="bi bi-person me-2"></i>Login to Continue
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- Available Units Section -->
            <div class="row">
                <?php if ($available_units && count($available_units) > 0): ?>
                    <?php foreach ($available_units as $space): ?>
                        <?php 
                        // Check if this specific unit has a pending request
                        $has_pending_request = in_array($space['Space_ID'], $pending_space_ids);
                        ?>
                        <div class="col-lg-6 col-xl-4 mb-4 animate-on-scroll">
                            <div class="rental-card <?= $has_pending_request ? 'pending-request' : '' ?>">
                                <?php if ($has_pending_request): ?>
                                    <div class="pending-badge">
                                        <i class="bi bi-hourglass-split me-1"></i>PENDING REQUEST
                                    </div>
                                <?php endif; ?>
                                
                                <div class="rental-card-header">
                                    <h3 class="rental-card-title"><?= htmlspecialchars($space['Name']) ?></h3>
                                    <div class="rental-info">
                                        <div class="info-item">
                                            <i class="bi bi-tag"></i>
                                            <span><strong>Type:</strong> <?= htmlspecialchars($space['SpaceTypeName']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <i class="bi bi-cash-coin"></i>
                                            <span class="price-highlight">₱<?= number_format($space['Price'], 0) ?> / month</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="rental-card-body">
                                    <form method="post" action="" onsubmit="return <?= $has_pending_request ? 'false' : 'showConfirmModal(this, ' . htmlspecialchars(json_encode($space['Price'])) . ', \'' . htmlspecialchars($space['Name']) . '\')' ?>;">
                                        <input type="hidden" name="space_id" value="<?= htmlspecialchars($space['Space_ID']) ?>">
                                        <input type="hidden" name="confirm_price" value="1">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="bi bi-calendar-plus"></i>
                                                Start Date
                                            </label>
                                            <input type="date" name="start_date" class="form-control start-date-input" <?= $has_pending_request ? 'disabled' : '' ?> required min="<?= date('Y-m-d') ?>">
                                        </div>
                                        
                                        <div class="due-date-display" style="display: none;">
                                            <strong>Rental Period: 1 Month</strong>
                                            <p class="mb-0 mt-2 text-muted">Due date will be automatically set to 1 month from your selected start date</p>
                                            <div class="calculated-end-date mt-2" style="font-weight: 600; color: var(--primary);"></div>
                                        </div>
                                        
                                        <div class="rental-notes">
                                            <p>
                                                <i class="bi bi-info-circle me-2"></i>
                                                <?php if ($has_pending_request): ?>
                                                    You have already submitted a request for this unit. Please wait for admin approval.
                                                <?php else: ?>
                                                    Choose your preferred start date. The rental period is fixed at 1 month. Our team will review your application and contact you within 24 hours.
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        
                                        <button type="submit" class="request-btn" <?= $has_pending_request ? 'disabled' : '' ?>>
                                            <?php if ($has_pending_request): ?>
                                                <i class="bi bi-clock-history"></i>
                                                PENDING
                                            <?php else: ?>
                                                <i class="bi bi-send"></i>
                                                Submit Request
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="bi bi-house-x"></i>
                            <h4>No Available Units</h4>
                            <p>There are currently no available units for rental. Please check back later or contact our team for more information.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Navigation Section -->
        <div class="navigation-section">
            <a href="index.php" class="nav-btn">
                <i class="bi bi-arrow-left"></i>
                Back to Home
            </a>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel">
                        <i class="bi bi-question-circle me-2 text-primary"></i>
                        Confirm Rental Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-building text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <div class="confirmation-text text-center">
                        <h6 id="modalUnitName" class="fw-bold mb-3"></h6>
                        <div class="price-display">
                            <div class="text-muted small">Monthly Rental Price</div>
                            <div class="price-amount" id="modalPriceText"></div>
                        </div>
                        <div class="due-date-display">
                            <div class="text-muted small">Rental Period</div>
                            <div style="font-weight: 600; color: var(--primary);" id="modalDateRange"></div>
                        </div>
                        <p class="mb-0">
                            Are you sure you want to submit this rental request? 
                            The price is fixed and rental period is 1 month.
                        </p>
                        <div class="alert alert-warning mt-3">
                            <small><i class="bi bi-exclamation-triangle me-2"></i>
                            Once submitted, this unit will be temporarily disabled from new requests until your request is processed.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-2"></i>Cancel
                    </button>
                    <button type="button" class="confirm-btn" id="confirmRequestBtn">
                        <i class="bi bi-check-lg me-2"></i>Yes, Submit Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div class="modal fade login-modal" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">
                        <i class="bi bi-person-circle me-2 text-primary"></i>
                        Login to Continue
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="login.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="bi bi-person me-2"></i>Username
                            </label>
                            <input type="text" class="form-control login-form-control" id="username" name="username" placeholder="Enter your username" required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock me-2"></i>Password
                            </label>
                            <input type="password" class="form-control login-form-control" id="password" name="password" placeholder="Enter your password" required>
                        </div>
                        <button type="submit" class="login-btn">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let pendingForm = null;

        // Show confirmation modal
        function showConfirmModal(form, price, unitName) {
            const startDateInput = form.querySelector('input[name="start_date"]');
            if (!startDateInput.value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Please Select Start Date',
                    text: 'Please select a start date before submitting your request.',
                    confirmButtonColor: 'var(--primary)'
                });
                return false;
            }

            pendingForm = form;
            document.getElementById('modalPriceText').textContent = "₱" + Number(price).toLocaleString() + " per month";
            document.getElementById('modalUnitName').textContent = unitName;
            
            // Calculate and display date range
            const startDate = new Date(startDateInput.value);
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + 1);
            
            const dateRange = `${formatDate(startDate)} - ${formatDate(endDate)}`;
            document.getElementById('modalDateRange').textContent = dateRange;
            
            const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            modal.show();
            return false;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Scroll animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate');
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.animate-on-scroll').forEach((el) => {
                observer.observe(el);
            });

            // Confirmation modal submit
            document.getElementById('confirmRequestBtn').addEventListener('click', function() {
                if (pendingForm) {
                    // Add loading state
                    this.classList.add('loading');
                    this.disabled = true;
                    
                    // Show loading message
                    Swal.fire({
                        title: 'Submitting Request...',
                        text: 'Please wait while we process your rental application.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit the form
                    pendingForm.submit();
                }
            });

            // Start date change handler to show/calculate end date
            document.querySelectorAll('.start-date-input').forEach(input => {
                input.addEventListener('change', function() {
                    const form = this.closest('form');
                    const dueDateDisplay = form.querySelector('.due-date-display');
                    const calculatedEndDate = form.querySelector('.calculated-end-date');
                    
                    if (this.value && dueDateDisplay && calculatedEndDate) {
                        const startDate = new Date(this.value);
                        const endDate = new Date(startDate);
                        endDate.setMonth(endDate.getMonth() + 1);
                        
                        calculatedEndDate.textContent = `End Date: ${formatDate(endDate)}`;
                        dueDateDisplay.style.display = 'block';
                    } else if (dueDateDisplay) {
                        dueDateDisplay.style.display = 'none';
                    }
                });
            });

            // Form validation - no need to validate end date since it's auto-calculated
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const startDate = this.querySelector('input[name="start_date"]');
                    
                    if (startDate && startDate.value) {
                        const start = new Date(startDate.value);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0); // Reset time for comparison
                        
                        if (start < today) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Invalid Start Date',
                                text: 'Start date cannot be in the past.',
                                confirmButtonColor: 'var(--primary)'
                            });
                            return false;
                        }
                    }
                });
            });

            // Login modal focus
            const loginModal = document.getElementById('loginModal');
            if (loginModal) {
                loginModal.addEventListener('shown.bs.modal', function () {
                    const usernameInput = document.getElementById('username');
                    if (usernameInput) usernameInput.focus();
                });
            }

            // Show success message
            <?php if (!empty($success)): ?>
                setTimeout(function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Request Submitted!',
                        text: 'Your rental request has been sent successfully. The requested unit is now temporarily disabled until your request is processed.',
                        confirmButtonColor: 'var(--success)',
                        timer: 5000
                    });
                }, 500);
            <?php endif; ?>

            // Show pending requests notification
            <?php if ($logged_in && !empty($pending_requests)): ?>
                setTimeout(function() {
                    Swal.fire({
                        icon: 'info',
                        title: 'Pending Requests',
                        text: 'You have <?= count($pending_requests) ?> pending rental request(s). Those units are temporarily disabled.',
                        confirmButtonColor: 'var(--warning)',
                        timer: 4000
                    });
                }, 1000);
            <?php endif; ?>

            // Date input improvements
            document.querySelectorAll('input[type="date"]').forEach(input => {
                // Set minimum date to today
                input.min = new Date().toISOString().split('T')[0];
            });

            // Enhanced card interactions for available units only
            document.querySelectorAll('.rental-card:not(.pending-request)').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Form submission loading states (prevent double submission)
            document.querySelectorAll('.request-btn:not(:disabled)').forEach(btn => {
                btn.form.addEventListener('submit', function(e) {
                    // Check if already submitted to prevent double submission
                    if (btn.classList.contains('loading') || btn.disabled) {
                        e.preventDefault();
                        return false;
                    }
                    
                    if (e.defaultPrevented) return;
                    
                    btn.classList.add('loading');
                    btn.disabled = true;
                    
                    // Reset after 10 seconds as fallback
                    setTimeout(() => {
                        btn.classList.remove('loading');
                        btn.disabled = false;
                    }, 10000);
                });
            });

            // Disable interaction for pending cards
            document.querySelectorAll('.rental-card.pending-request').forEach(card => {
                const form = card.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Request Already Pending',
                            text: 'You have already submitted a request for this unit.',
                            confirmButtonColor: 'var(--warning)'
                        });
                    });
                }
            });

            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });

        // Auto-show login modal for non-logged-in users
        <?php if (isset($show_login_modal) && $show_login_modal): ?>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                    loginModal.show();
                }, 1000);
            });
        <?php endif; ?>

        // Utility functions
        function formatDate(date) {
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function calculateDays(startDate, endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        }

        // Enhanced mobile experience
        function isMobile() {
            return window.innerWidth <= 768;
        }

        window.addEventListener('resize', function() {
            if (isMobile()) {
                // Adjust modal sizes for mobile
                document.querySelectorAll('.modal-dialog').forEach(dialog => {
                    dialog.classList.add('modal-fullscreen-sm-down');
                });
            }
        });

        // Prevent back button issues after form submission
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Reset any loading states if page is loaded from cache
                document.querySelectorAll('.loading').forEach(el => {
                    el.classList.remove('loading');
                });
                document.querySelectorAll('.request-btn').forEach(btn => {
                    btn.disabled = false;
                });
            }
        });
    </script>
</body>
</html>