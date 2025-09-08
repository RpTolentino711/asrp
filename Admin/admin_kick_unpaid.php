<?php
require_once '../database/database.php';
session_start();

// Create an instance of the Database class
$db = new Database();

// --- Authentication ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

$msg = "";
$error = "";

// --- Handle POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kick_invoice_id'])) {
    $invoice_id = intval($_POST['kick_invoice_id']);
    $client_id = intval($_POST['kick_client_id']);
    $space_id = intval($_POST['kick_space_id']);
    $request_id = intval($_POST['kick_request_id']);

    // Call the single, transactional method to perform the kick
    if ($db->kickOverdueClient($invoice_id, $client_id, $space_id, $request_id)) {
        $msg = "Client #{$client_id} was successfully kicked from unit #{$space_id}.";
    } else {
        $error = "An error occurred. The client could not be kicked. Please check server logs.";
    }
}

// --- Fetch Data for Display ---
$overdue_rentals = $db->getOverdueRentalsForKicking();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Kick Unpaid Clients</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --dark: #1f2937;
            --darker: #111827;
            --light: #f3f4f6;
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            color: #374151;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .sidebar {
            width: 250px;
            min-height: 100vh;
            background: linear-gradient(180deg, var(--dark), var(--darker));
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease-in-out;
            padding: 1.5rem 1rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 0 0 1.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1.5rem;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.35rem;
            color: white;
            text-decoration: none;
        }

        .sidebar-brand i {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.85);
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .nav-link i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .content {
            margin-left: 250px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .welcome-text h1 {
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .welcome-text p {
            color: #6b7280;
            font-size: 1rem;
        }

        .dashboard-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-header i {
            color: var(--primary);
        }

        .card-body {
            padding: 1.5rem;
        }

        .table-container {
            overflow-x: auto;
            border-radius: var(--border-radius);
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .custom-table th {
            background-color: #f9fafb;
            padding: 0.75rem 1rem;
            font-weight: 600;
            text-align: left;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }

        .custom-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .custom-table tr:last-child td {
            border-bottom: none;
        }

        .custom-table tr:hover {
            background-color: #f9fafb;
        }

        .badge-overdue {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .text-primary { color: var(--primary) !important; }
        .text-danger { color: var(--danger) !important; }
        .text-warning { color: var(--warning) !important; }
        .text-info { color: var(--info) !important; }
        .text-success { color: var(--secondary) !important; }

        .bg-primary-light { background: rgba(99, 102, 241, 0.1); }
        .bg-danger-light { background: rgba(239, 68, 68, 0.1); }
        .bg-warning-light { background: rgba(245, 158, 11, 0.1); }
        .bg-info-light { background: rgba(6, 182, 212, 0.1); }

        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.75rem;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.8rem;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="toggle-btn"><i class="fas fa-bars"></i></div>

    <div class="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-crown"></i>
                <span>ASRT Admin</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="manage_user.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="view_rental_requests.php" class="nav-link">
                <i class="fas fa-clipboard-check"></i>
                <span>Rental Requests</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="manage_maintenance.php" class="nav-link">
                <i class="fas fa-tools"></i>
                <span>Maintenance</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="generate_invoice.php" class="nav-link">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Invoices</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="add_unit.php" class="nav-link">
                <i class="fas fa-plus-square"></i>
                <span>Add Unit</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="admin_add_handyman.php" class="nav-link">
                <i class="fas fa-user-plus"></i>
                <span>Add Handyman</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="admin_kick_unpaid.php" class="nav-link active">
                <i class="fas fa-user-slash"></i>
                <span>Overdue Accounts</span>
            </a>
        </div>

        <div class="nav-item mt-4">
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="content">
        <div class="dashboard-header">
            <div class="welcome-text">
                <h1>Overdue Accounts Management</h1>
                <p>Manage clients with overdue payments</p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($msg) && !empty($msg)): ?>
            <div class="alert alert-success animate-fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger animate-fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <span>Overdue Accounts</span>
                <?php if (!empty($overdue_rentals)): ?>
                    <span class="badge bg-danger ms-2"><?= count($overdue_rentals) ?> Unpaid</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($overdue_rentals)): ?>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Unit</th>
                                    <th>Invoice Date</th>
                                    <th>Rental End</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($overdue_rentals as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($row['Client_fn'] . ' ' . $row['Client_ln']) ?></div>
                                                    <div class="text-muted small">ID: <?= $row['Client_ID'] ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['SpaceName']) ?> (ID: <?= $row['Space_ID'] ?>)</td>
                                        <td><?= htmlspecialchars($row['InvoiceDate']) ?></td>
                                        <td><?= htmlspecialchars($row['EndDate']) ?></td>
                                        <td>
                                            <form method="post" onsubmit="return confirmAction(this);">
                                                <input type="hidden" name="kick_invoice_id" value="<?= $row['Invoice_ID'] ?>">
                                                <input type="hidden" name="kick_client_id" value="<?= $row['Client_ID'] ?>">
                                                <input type="hidden" name="kick_space_id" value="<?= $row['Space_ID'] ?>">
                                                <input type="hidden" name="kick_request_id" value="<?= $row['Request_ID'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-user-slash me-1"></i>Kick & Free Unit
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="bg-success-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-check-circle text-success" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="text-success mb-2">No Overdue Accounts</h4>
                        <p class="text-muted">All clients are up to date with their payments.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('.toggle-btn').addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.content').classList.toggle('collapsed');
        });

        function confirmAction(form) {
            Swal.fire({
                title: 'Are you absolutely sure?',
                html: "This will:<br><ul class='text-start'>" +
                      "<li>Terminate the client's rental agreement</li>" +
                      "<li>Make the unit available for new rentals</li>" +
                      "<li>Send a notification to the client</li></ul>" +
                      "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, kick this client',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'me-2',
                    cancelButton: 'ms-2'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });

            // Prevent the default form submission, wait for SweetAlert
            return false;
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>