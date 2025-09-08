<?php
require 'database/database.php';
session_start();

$db = new Database();

$logged_in = isset($_SESSION['client_id']);
$client_id = $logged_in ? $_SESSION['client_id'] : null;

if (!$logged_in) {
    $show_login_modal = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logged_in && isset($_POST['space_id'], $_POST['start_date'], $_POST['end_date'], $_POST['confirm_price'])) {
    $space_id = intval($_POST['space_id']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    if ($db->createRentalRequest($client_id, $space_id, $start_date, $end_date)) {
        $success = "Your rental request has been sent to the admin!";
    }
}

$id = $_GET["space_id"];
$available_units = $db->getAvailableUnitsForRental($id);
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

        .rental-card-header {
            background: linear-gradient(135deg, var(--light) 0%, var(--lighter) 100%);
            padding: 1.5rem 2rem 1rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .rental-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 1rem;
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

        .request-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        .request-btn i {
            margin-right: 0.5rem;
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
                        <div class="col-lg-6 col-xl-4 mb-4 animate-on-scroll">
                            <div class="rental-card">
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
                                    <form method="post" action="" onsubmit="return showConfirmModal(this, <?= htmlspecialchars(json_encode($space['Price'])) ?>, '<?= htmlspecialchars($space['Name']) ?>');">
                                        <input type="hidden" name="space_id" value="<?= htmlspecialchars($space['Space_ID']) ?>">
                                        <input type="hidden" name="confirm_price" value="1">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="bi bi-calendar-plus"></i>
                                                Start Date
                                            </label>
                                            <input type="date" name="start_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="bi bi-calendar-check"></i>
                                                End Date
                                            </label>
                                            <input type="date" name="end_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                                        </div>
                                        
                                        <div class="rental-notes">
                                            <p>
                                                <i class="bi bi-info-circle me-2"></i>
                                                Choose your preferred rental period. Our team will review your application and contact you within 24 hours.
                                            </p>
                                        </div>
                                        
                                        <button type="submit" class="request-btn">
                                            <i class="bi bi-send"></i>
                                            Submit Request
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
                        <p class="mb-0">
                            Are you sure you want to submit this rental request? 
                            The price is fixed and non-negotiable.
                        </p>
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
            pendingForm = form;
            document.getElementById('modalPriceText').textContent = "₱" + Number(price).toLocaleString() + " per month";
            document.getElementById('modalUnitName').textContent = unitName;
            
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

            // Form validation
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const startDate = this.querySelector('input[name="start_date"]');
                    const endDate = this.querySelector('input[name="end_date"]');
                    
                    if (startDate && endDate && startDate.value && endDate.value) {
                        const start = new Date(startDate.value);
                        const end = new Date(endDate.value);
                        
                        if (end <= start) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Invalid Date Range',
                                text: 'End date must be after the start date.',
                                confirmButtonColor: 'var(--primary)'
                            });
                            return false;
                        }
                        
                        // Calculate rental period
                        const diffTime = Math.abs(end - start);
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        
                        if (diffDays < 30) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'warning',
                                title: 'Minimum Rental Period',
                                text: 'Minimum rental period is 30 days. Please adjust your dates.',
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
                        text: 'Your rental request has been sent successfully. Our team will contact you within 24 hours.',
                        confirmButtonColor: 'var(--success)',
                        timer: 5000
                    });
                }, 500);
            <?php endif; ?>

            // Date input improvements
            document.querySelectorAll('input[type="date"]').forEach(input => {
                // Set minimum date to today
                input.min = new Date().toISOString().split('T')[0];
                
                // Add change event for start date to update end date minimum
                if (input.name === 'start_date') {
                    input.addEventListener('change', function() {
                        const endDateInput = this.form.querySelector('input[name="end_date"]');
                        if (endDateInput) {
                            endDateInput.min = this.value;
                            // Clear end date if it's before the new start date
                            if (endDateInput.value && endDateInput.value <= this.value) {
                                endDateInput.value = '';
                            }
                        }
                    });
                }
            });

            // Enhanced card interactions
            document.querySelectorAll('.rental-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Form submission loading states
            document.querySelectorAll('.request-btn').forEach(btn => {
                btn.form.addEventListener('submit', function(e) {
                    if (e.defaultPrevented) return;
                    
                    btn.classList.add('loading');
                    btn.disabled = true;
                    
                    // Reset after 5 seconds as fallback
                    setTimeout(() => {
                        btn.classList.remove('loading');
                        btn.disabled = false;
                    }, 5000);
                });
            });
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
    </script>
</body>
</html>