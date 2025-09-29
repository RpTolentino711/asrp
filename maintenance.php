<?php
require 'database/database.php';
session_start();

// Ensure the Database class is properly instantiated.
// It's good practice to wrap database operations in try-catch blocks in a real application,
// especially during development, to catch connection errors or query issues.
try {
    $db = new Database();
} catch (Exception $e) {
    // Log the error and display a user-friendly message, or redirect to an error page.
    error_log("Database connection failed: " . $e->getMessage());
    // In a production environment, you might display a generic error.
    die("A system error occurred. Please try again later.");
}

date_default_timezone_set('Asia/Manila');

$is_logged_in = isset($_SESSION['C_username']) && isset($_SESSION['client_id']);

$spaces = [];
$requests = [];
$message = '';
$pending_space_ids = []; // ✅ This is correctly defined and initialized

// Initialize $show_success_modal even if not logged in to avoid potential undefined variable warnings later.
$show_success_modal = false;

if ($is_logged_in) {
    $client_id = $_SESSION['client_id'];

    // --- Handle success message from last submission
    if (isset($_SESSION['maintenance_success'])) {
        $message = "<div class='alert alert-success'>Request submitted successfully!</div>";
        unset($_SESSION['maintenance_success']);
        $show_success_modal = true; // ✅ Flag for SweetAlert in JS
    }

    // --- Handle Form Submission
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_request'])) {
        // Add CSRF protection here for security
        // Example:
        // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        //     $message = "<div class='alert alert-danger'>Invalid request. Please try again.</div>";
        //     // Potentially log this as a security incident
        // } else {
            $space_id = filter_input(INPUT_POST, 'space_id', FILTER_VALIDATE_INT); // Safer way to get and validate integer input

            if ($space_id === false || $space_id === null) { // filter_input returns false on failure, null if not set
                $message = "<div class='alert alert-danger'>Invalid unit selected. Please try again.</div>";
            } else {
                // IMPORTANT: Ensure your Database class uses prepared statements for ALL queries
                // to prevent SQL injection. This is assumed for methods like hasPendingMaintenanceRequest
                // and createMaintenanceRequest.

                if ($db->hasPendingMaintenanceRequest($client_id, $space_id)) {
                    $message = "<div class='alert alert-danger'>You already have a pending maintenance request for this unit. Please wait until it is completed.</div>";
                } else {
                    if ($db->createMaintenanceRequest($client_id, $space_id)) {
                        $_SESSION['maintenance_success'] = true;
                        // Always include exit() after header() redirects
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        // More detailed error logging for debugging might be helpful here.
                        error_log("Failed to create maintenance request for client_id: {$client_id}, space_id: {$space_id}");
                        $message = "<div class='alert alert-danger'>Failed to submit request. Please try again.</div>";
                    }
                }
            }
        // } // End of CSRF check
    }

    // --- Fetch Data
    // Ensure these methods also use prepared statements and handle potential database errors.
    $spaces = $db->getClientSpacesForMaintenance($client_id);
    $requests = $db->getClientMaintenanceHistory($client_id);

    foreach ($requests as $req) {
        // Consider using a consistent column name for status or a specific flag in the DB
        // to simplify checking for 'pending' states.
        if (in_array($req['Status'], ['Submitted', 'In Progress'])) {
            $pending_space_ids[] = $req['Space_ID'];
        }
    }

    // CSRF token generation (for the form) - place it inside the logged-in block
    // $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}
// No need for an else block here for $show_success_modal as it's initialized to false.
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
        /* Modern Alert Styles (example - you'd integrate this with your existing CSS) */
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

        /* Status Badge Styles (example) */
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
        .status-submitted { background-color: #0d6efd; /* Blue */ }
        .status-in-progress { background-color: #ffc107; /* Yellow */ color: #343a40;}
        .status-completed { background-color: #198754; /* Green */ }
        .status-cancelled { background-color: #dc3545; /* Red */ }
        .status-pending { background-color: #6c757d; /* Gray */ }

        /* Other styles as in your original code */
        .maintenance-header {
            background-color: var(--primary); /* Or a specific color */
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
            overflow: hidden; /* For table border-radius */
        }
        .request-card-header, .history-card-header {
            background-color: var(--light); /* A light background */
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
            background-color: var(--dark-primary); /* Darker shade of primary */
            color: white;
        }
        .submit-btn i {
            margin-right: 0.5rem;
        }
        .pending-warning {
            color: #dc3545; /* Red for warning */
            font-size: 0.9em;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
        }
        .pending-warning i {
            margin-right: 0.5rem;
        }
        .history-table-container {
            overflow-x: auto; /* For responsive tables */
            padding: 0 1.5rem 1.5rem; /* Padding for the container, not table itself */
        }
        .history-table {
            margin-bottom: 0;
            border-collapse: separate; /* To allow border-radius on cells */
            border-spacing: 0;
            width: 100%;
        }
        .history-table th, .history-table td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
            border-top: 1px solid #e9ecef;
            white-space: nowrap; /* Prevent wrapping in cells */
        }
        .history-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #343a40;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        /* Rounded corners for the table, if desired */
        .history-table thead tr:first-child th:first-child { border-top-left-radius: 0.75rem; }
        .history-table thead tr:first-child th:last-child { border-top-right-radius: 0.75rem; }
        .history-table tbody tr:last-child td:first-child { border-bottom-left-radius: 0.75rem; }
        .history-table tbody tr:last-child td:last-child { border-bottom-right-radius: 0.75rem; }

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
            min-height: 40vh; /* Adjust as needed */
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
            color: var(--info); /* Or a brand color */
            margin-bottom: 1.5rem;
        }
        .login-card .btn {
            font-weight: 600;
        }

        /* Basic scroll animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-on-scroll {
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0; /* Hidden by default */
        }
        /* You might use an Intersection Observer API for more advanced scroll animations */

        /* Define CSS Custom Properties (Variables) */
        :root {
            --primary: #007bff; /* Example: Bootstrap primary blue */
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --dark-primary: #0056b3; /* A darker shade of primary for hover */
            /* Add any other specific colors from your design system */
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
                    <!-- The str_replace is a creative way to apply custom alert classes.
                         Ensure 'modern-alert' and specific color classes (alert-success, alert-danger)
                         are defined in your CSS. -->
                    <?= str_replace(
                        ['alert-success', 'alert-danger', 'alert-warning'],
                        ['modern-alert alert-success', 'modern-alert alert-danger', 'modern-alert alert-warning'],
                        $message
                    ) ?>
                </div>
            <?php endif; ?>

            <?php if ($is_logged_in): ?>
                <?php if (!empty($spaces)): ?>
                    <!-- ✅ Request Form Section -->
                    <div class="request-form-section animate-on-scroll">
                        <div class="request-card">
                            <div class="request-card-header">
                                <h5><i class="bi bi-plus-circle"></i> Submit New Maintenance Request</h5>
                            </div>
                            <div class="request-card-body">
                                <form method="post" id="maintenanceForm">
                                    <?php // if ($is_logged_in): ?>
                                    <!-- CSRF Token (uncomment if you implement CSRF protection) -->
                                    <!-- <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"> -->
                                    <?php // endif; ?>
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
                                    <button type="submit" name="submit_request" class="submit-btn" id="submitBtn">
                                        <i class="bi bi-send"></i> Submit Maintenance Request
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- ✅ No Units Alert -->
                    <div class="no-units-alert animate-on-scroll">
                        <i class="bi bi-house-x no-units-icon"></i>
                        <h4 class="fw-bold mb-3">No Units Available</h4>
                        <p class="mb-0">You currently do not have any rented units.</p>
                    </div>
                <?php endif; ?>

                <!-- ✅ History Section -->
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
                                                <th>Last Updated</th>
                                                <th>Handyman</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requests as $req): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($req['RequestDate']))) ?>
// Displays as: Sep 30, 2025 2:35 PM</td>
                                                    <td><strong><?= htmlspecialchars($req['SpaceName']) ?></strong></td>
                                                    <td>
                                                        <?php
                                                        $status_class = 'status-' . strtolower(str_replace(' ', '-', $req['Status']));
                                                        ?>
                                                        <span class="status-badge <?= $status_class ?>">
                                                            <?= htmlspecialchars($req['Status']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($req['LastStatusDate']) ?></td>
                                                    <td>
                                                        <?php if (!empty($req['Handyman_fn'])): // Use !empty for better check ?>
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
                <!-- ✅ Login Required Section -->
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

    <?php require('footer.php'); ?>

    <!-- ✅ Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Success modal
            // The condition in PHP `!empty($show_success_modal)` correctly renders this block.
            <?php if ($show_success_modal): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Request Submitted!',
                    text: 'Your request has been submitted successfully.',
                    confirmButtonColor: 'var(--success)' // Uses CSS variable for consistency
                });
            <?php endif; ?>

            // Pending unit warning
            const spaceSelect = document.getElementById('space_id');
            const pendingInfo = document.getElementById('pendingInfo');
            const submitBtn = document.getElementById('submitBtn');

            if (spaceSelect && pendingInfo && submitBtn) { // Add null checks for robustness
                // Initial check in case a pending unit is pre-selected (though not likely with current logic)
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
        });
    </script>
</body>
</html>