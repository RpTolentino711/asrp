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

// --- Fetch All Active Renters ---
$all_renters = $db->getAllActiveRenters();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Rental Management</title>
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

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .status-overdue {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-due-today {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .status-current {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-unpaid {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-paid {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
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

        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .filter-tab {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            background: white;
            color: #6b7280;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
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
    <div class="toggle-btn" style="position: fixed; top: 20px; left: 20px; z-index: 1001; background: var(--primary); color: white; padding: 10px; border-radius: 5px; cursor: pointer; display: none;">
        <i class="fas fa-bars"></i>
    </div>

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
                <span>Rental Management</span>
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
                <h1>Rental Management</h1>
                <p>View all active renters and manage overdue accounts</p>
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

        <!-- Statistics Row -->
        <div class="stats-row animate-fade-in">
            <?php
            $total_renters = count($all_renters);
            $overdue_count = 0;
            $due_today_count = 0;
            $current_count = 0;
            
            foreach($all_renters as $renter) {
                if ($renter['DaysOverdue'] > 0) {
                    $overdue_count++;
                } elseif ($renter['DaysOverdue'] == 0) {
                    $due_today_count++;
                } else {
                    $current_count++;
                }
            }
            ?>
            <div class="stat-card">
                <div class="stat-number text-primary"><?= $total_renters ?></div>
                <div class="stat-label">Total Renters</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-danger"><?= $overdue_count ?></div>
                <div class="stat-label">Overdue</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-warning"><?= $due_today_count ?></div>
                <div class="stat-label">Due Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-success"><?= $current_count ?></div>
                <div class="stat-label">Current</div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs animate-fade-in">
            <div class="filter-tab active" onclick="filterTable('all')">All Renters</div>
            <div class="filter-tab" onclick="filterTable('overdue')">Overdue Only</div>
            <div class="filter-tab" onclick="filterTable('due_today')">Due Today</div>
            <div class="filter-tab" onclick="filterTable('current')">Current</div>
        </div>

        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-users"></i>
                <span>All Active Renters</span>
                <span class="badge bg-primary ms-2"><?= $total_renters ?> Total</span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($all_renters)): ?>
                    <div class="table-container">
                        <table class="custom-table" id="rentersTable">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Unit</th>
                                    <th>Invoice Date</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Days Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($all_renters as $row): ?>
                                    <tr class="renter-row" data-status="<?= $row['DaysOverdue'] > 0 ? 'overdue' : ($row['DaysOverdue'] == 0 ? 'due_today' : 'current') ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($row['Client_fn'] . ' ' . $row['Client_ln']) ?></div>
                                                    <div class="text-muted small">ID: <?= $row['Client_ID'] ?></div>
                                                    <?php if (!empty($row['Client_Email'])): ?>
                                                        <div class="text-muted small"><?= htmlspecialchars($row['Client_Email']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($row['SpaceName']) ?></div>
                                            <div class="text-muted small">ID: <?= $row['Space_ID'] ?></div>
                                            <?php if (!empty($row['Street'])): ?>
                                                <div class="text-muted small"><?= htmlspecialchars($row['Street'] . ', ' . $row['Brgy']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['InvoiceDate']) ?></td>
                                        <td><?= htmlspecialchars($row['EndDate']) ?></td>
                                        <td>
                                            <div class="fw-semibold">₱<?= number_format($row['InvoiceTotal'], 2) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($row['Status'] == 'unpaid'): ?>
                                                <span class="status-badge status-unpaid">Unpaid</span>
                                            <?php else: ?>
                                                <span class="status-badge status-paid">Paid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['DaysOverdue'] > 0): ?>
                                                <span class="status-badge status-overdue"><?= $row['DaysOverdue'] ?> days overdue</span>
                                            <?php elseif ($row['DaysOverdue'] == 0): ?>
                                                <span class="status-badge status-due-today">Due today</span>
                                            <?php else: ?>
                                                <span class="status-badge status-current"><?= abs($row['DaysOverdue']) ?> days remaining</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['Status'] == 'unpaid' && $row['DaysOverdue'] >= 0): ?>
                                                <form method="post" onsubmit="return confirmAction(this);">
                                                    <input type="hidden" name="kick_invoice_id" value="<?= $row['Invoice_ID'] ?>">
                                                    <input type="hidden" name="kick_client_id" value="<?= $row['Client_ID'] ?>">
                                                    <input type="hidden" name="kick_space_id" value="<?= $row['Space_ID'] ?>">
                                                    <input type="hidden" name="kick_request_id" value="<?= $row['Request_ID'] ?? '' ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-user-slash me-1"></i>Kick & Free Unit
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    <i class="fas fa-check me-1"></i>Current
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="bg-info-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-users text-info" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="text-info mb-2">No Active Renters</h4>
                        <p class="text-muted">No active rental agreements found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar for mobile
        document.querySelector('.toggle-btn').addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Show toggle button on mobile
        if (window.innerWidth <= 992) {
            document.querySelector('.toggle-btn').style.display = 'block';
        }

        // Filter table functionality
        function filterTable(status) {
            // Update active tab
            document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            const rows = document.querySelectorAll('.renter-row');
            
            rows.forEach(row => {
                if (status === 'all') {
                    row.style.display = '';
                } else {
                    if (row.dataset.status === status) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }

        function confirmAction(form, clientName, daysOverdue, status) {
            let title, html, confirmButtonText, warningLevel;
            
            // Determine the type of kick and set appropriate warnings
            if (status === 'unpaid' && parseInt(daysOverdue) >= 0) {
                // Standard overdue kick
                title = 'Kick Overdue Client?';
                html = `<strong>${clientName}</strong> is ${daysOverdue} days overdue.<br><br>This will:
                        <ul class='text-start'>
                        <li>Terminate the client's rental agreement</li>
                        <li>Make the unit available for new rentals</li>
                        <li>Mark the invoice as 'kicked'</li>
                        <li>Log the eviction for audit purposes</li>
                        </ul>This action cannot be undone.`;
                confirmButtonText = 'Yes, kick this client';
                warningLevel = 'warning';
            } else if (status === 'unpaid') {
                // Force kick for early/future due dates
                title = 'Force Kick Client Early?';
                html = `<strong>⚠️ WARNING: ${clientName} is NOT overdue yet!</strong><br>
                        Payment is due in ${Math.abs(parseInt(daysOverdue))} days.<br><br>
                        <div class="alert alert-warning">
                        <strong>This is an early termination!</strong><br>
                        Consider sending a payment reminder first.
                        </div>
                        This will:
                        <ul class='text-start'>
                        <li>Terminate the rental agreement early</li>
                        <li>Make the unit available immediately</li>
                        <li>Mark the invoice as 'kicked'</li>
                        <li>Log the early eviction</li>
                        </ul>This action cannot be undone.`;
                confirmButtonText = 'Yes, force kick early';
                warningLevel = 'error';
            } else {
                // Admin kick for paid clients
                title = 'Admin Override Kick?';
                html = `<strong>⚠️ CRITICAL WARNING: ${clientName} has PAID their rent!</strong><br><br>
                        <div class="alert alert-danger">
                        <strong>This client is current with payments!</strong><br>
                        This action may have legal implications.
                        </div>
                        Admin override will:
                        <ul class='text-start'>
                        <li>Terminate a paid rental agreement</li>
                        <li>Make the unit available immediately</li>
                        <li>Mark the invoice as 'kicked' (admin override)</li>
                        <li>Create an audit log of admin action</li>
                        </ul>
                        <strong>Are you absolutely certain this is necessary?</strong>`;
                confirmButtonText = 'Yes, admin override kick';
                warningLevel = 'error';
            }

            Swal.fire({
                title: title,
                html: html,
                icon: warningLevel,
                showCancelButton: true,
                confirmButtonColor: warningLevel === 'error' ? '#dc2626' : '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: confirmButtonText,
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'me-2',
                    cancelButton: 'ms-2'
                },
                // Extra confirmation for admin overrides
                ...(status === 'paid' && {
                    input: 'text',
                    inputPlaceholder: 'Type "CONFIRM OVERRIDE" to proceed',
                    inputValidator: (value) => {
                        if (value !== 'CONFIRM OVERRIDE') {
                            return 'You must type "CONFIRM OVERRIDE" exactly to proceed with admin override';
                        }
                    }
                })
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });

            return false;
        }

        // Responsive handling
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 992) {
                document.querySelector('.toggle-btn').style.display = 'block';
            } else {
                document.querySelector('.toggle-btn').style.display = 'none';
                document.querySelector('.sidebar').classList.remove('active');
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Add the new database method to your Database class
class Database {
    // ... existing methods ...
    
    /**
     * Get all active renters with their rental status and due dates
     */
    public function getAllActiveRenters() {
        $sql = "SELECT DISTINCT 
                    i.Invoice_ID,
                    i.Client_ID,
                    i.Space_ID,
                    i.InvoiceDate,
                    i.EndDate,
                    i.InvoiceTotal,
                    i.Status,
                    i.Flow_Status,
                    c.Client_fn,
                    c.Client_ln,
                    c.Client_Email,
                    c.Client_Phone,
                    s.Name as SpaceName,
                    s.Street,
                    s.Brgy,
                    s.City,
                    r.Request_ID,
                    cs.CS_ID,
                    DATEDIFF(CURDATE(), i.EndDate) as DaysOverdue,
                    CASE 
                        WHEN DATEDIFF(CURDATE(), i.EndDate) > 0 THEN 'overdue'
                        WHEN DATEDIFF(CURDATE(), i.EndDate) = 0 THEN 'due_today' 
                        ELSE 'current'
                    END as RentalStatus
                FROM invoice i
                JOIN client c ON i.Client_ID = c.Client_ID
                JOIN space s ON i.Space_ID = s.Space_ID
                JOIN clientspace cs ON i.Client_ID = cs.Client_ID 
                    AND i.Space_ID = cs.Space_ID 
                    AND cs.active = 1
                LEFT JOIN rentalrequest r ON i.Client_ID = r.Client_ID 
                    AND i.Space_ID = r.Space_ID 
                    AND r.Status = 'Accepted'
                WHERE i.Flow_Status = 'new'
                    AND c.Status = 'Active'
                ORDER BY 
                    CASE 
                        WHEN i.Status = 'unpaid' AND DATEDIFF(CURDATE(), i.EndDate) > 0 THEN 1
                        WHEN i.Status = 'unpaid' AND DATEDIFF(CURDATE(), i.EndDate) = 0 THEN 2
                        WHEN i.Status = 'unpaid' THEN 3
                        ELSE 4
                    END,
                    DATEDIFF(CURDATE(), i.EndDate) DESC,
                    i.EndDate ASC";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all active renters: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Updated kick overdue client method
     */
    public function kickOverdueClient($invoice_id, $client_id, $space_id, $request_id) {
        // Validate input parameters
        if (!is_numeric($invoice_id) || !is_numeric($client_id) || !is_numeric($space_id)) {
            error_log("Invalid parameters for kickOverdueClient");
            return false;
        }
        
        $this->pdo->beginTransaction();
        try {
            // 1. Deactivate client-space relationship (soft delete instead of hard delete)
            $stmt1 = $this->pdo->prepare("UPDATE clientspace SET active = 0 WHERE Client_ID = ? AND Space_ID = ? AND active = 1");
            $stmt1->execute([$client_id, $space_id]);

            // 2. Set spaceavailability to 'Available' and set EndDate to today
            $stmt2 = $this->pdo->prepare("UPDATE spaceavailability 
                         SET Status = 'Available', EndDate = CURDATE() 
                         WHERE Space_ID = ? AND Status = 'Occupied'");
            $stmt2->execute([$space_id]);

            // 3. Mark the invoice as 'kicked' and set Flow_Status to 'done'
            $stmt3 = $this->pdo->prepare("UPDATE invoice 
                         SET Status = 'kicked', Flow_Status = 'done' 
                         WHERE Invoice_ID = ? AND Client_ID = ?");
            $stmt3->execute([$invoice_id, $client_id]);

            // 4. Mark the rental request as 'Rejected' (with null check)
            if ($request_id && is_numeric($request_id)) {
                $stmt4 = $this->pdo->prepare("UPDATE rentalrequest 
                             SET Status = 'Rejected' 
                             WHERE Request_ID = ? AND Client_ID = ? AND Space_ID = ?");
                $stmt4->execute([$request_id, $client_id, $space_id]);
            }

            // 5. Set the space as available in the flow (Flow_Status: 'new')
            $stmt5 = $this->pdo->prepare("UPDATE space SET Flow_Status = 'new' WHERE Space_ID = ?");
            $stmt5->execute([$space_id]);

            // 6. Ensure there is an 'Available' record in spaceavailability for this space (avoid duplicates)
            $existsStmt = $this->pdo->prepare("SELECT COUNT(*) FROM spaceavailability WHERE Space_ID = ? AND Status = 'Available'");
            $existsStmt->execute([$space_id]);
            
            if ($existsStmt->fetchColumn() == 0) {
                $insertStmt = $this->pdo->prepare("INSERT INTO spaceavailability (Space_ID, Status) VALUES (?, 'Available')");
                $insertStmt->execute([$space_id]);
            }

            // 7. Log the eviction for audit trail
            try {
                $message = "Client evicted due to overdue payment. Invoice: #{$invoice_id}";
                $logStmt = $this->pdo->prepare("INSERT INTO invoice_chat (Invoice_ID, Sender_Type, Message, Created_At) 
                             VALUES (?, 'system', ?, NOW())");
                $logStmt->execute([$invoice_id, $message]);
            } catch (PDOException $e) {
                // Don't fail transaction for logging issues
                error_log("Failed to log eviction: " . $e->getMessage());
            }

            $this->pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Failed to kick client (Invoice #{$invoice_id}): " . $e->getMessage());
            return false;
        }
    }
}
?>