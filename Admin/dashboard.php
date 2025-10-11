<?php
session_start();
require '../database/database.php';

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// Get selected month/year from request or use current month
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($selectedMonth < 1 || $selectedMonth > 12) $selectedMonth = date('m');
if ($selectedYear < 2020 || $selectedYear > 2030) $selectedYear = date('Y');

// Get date range for the selected month/year
$startDate = "$selectedYear-" . str_pad($selectedMonth, 2, "0", STR_PAD_LEFT) . "-01";
$endDate = date("Y-m-t", strtotime($startDate));
$monthName = date('F Y', strtotime($startDate));

// Get statistics
$counts = $db->getAdminDashboardCounts();
$monthlyStats = $db->getMonthlyEarningsStats($startDate, $endDate);
$chartData = $db->getAdminMonthChartData($startDate, $endDate);
$rentalRequestsData = $db->getTotalRentalRequests($startDate, $endDate);
$maintenanceRequestsData = $db->getTotalMaintenanceRequests($startDate, $endDate);

// Extract values
$pending = $counts['pending_rentals'] ?? 0;
$pending_maintenance = $counts['pending_maintenance'] ?? 0;
$unpaid_invoices = $counts['unpaid_invoices'] ?? 0;
$overdue_invoices = $counts['overdue_invoices'] ?? 0;
$total_earnings = $monthlyStats['total_earnings'] ?? 0;
$paid_invoices_count = $monthlyStats['paid_invoices_count'] ?? 0;
$new_messages_count = $monthlyStats['new_messages_count'] ?? 0;
$new_maintenance_requests = $counts['new_maintenance_requests'] ?? 0;
$unseen_rentals = $counts['unseen_rentals'] ?? 0;

// Soft delete logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soft_delete_msg_id'])) {
    $msg_id = intval($_POST['soft_delete_msg_id']);
    $db->executeStatement("UPDATE free_message SET is_deleted = 1 WHERE Message_ID = ?", [$msg_id]);
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message_id' => $msg_id]);
        exit();
    } else {
        $filterParam = isset($_GET['filter']) ? 'filter=' . urlencode($_GET['filter']) : '';
        header("Location: dashboard.php" . ($filterParam ? "?$filterParam" : ""));
        exit();
    }
}

$latest_requests = $db->getLatestPendingRequests(5);

// Message filter logic
$filter = $_GET['filter'] ?? 'recent';
if ($filter === 'all') {
    $free_messages = $db->getAllFreeMessages();
} else {
    $free_messages = $db->getRecentFreeMessages(5);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | ASRT Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --sidebar-width: 280px;
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #374151;
            min-height: 100vh;
        }

        /* Mobile Menu */
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .mobile-overlay.active {
            display: block;
        }

        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            z-index: 1001;
            padding: 0 1rem;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .mobile-menu-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            padding: 0.5rem;
            border-radius: 8px;
        }

        .mobile-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--dark);
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--dark), var(--darker));
            color: white;
            padding: 1.5rem 1rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: var(--transition);
            overflow-y: auto;
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
            font-size: 0.95rem;
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
        
        .badge-notification {
            margin-left: auto;
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }
        
        /* Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
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
        
        .period-badge {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Control Panel */
        .control-panel {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .month-picker-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }

        .export-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card.rentals::before { background: var(--primary); }
        .stat-card.maintenance::before { background: var(--warning); }
        .stat-card.invoices::before { background: var(--info); }
        .stat-card.overdue::before { background: var(--danger); }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }
        
        .stat-card.rentals .stat-icon { background: rgba(99, 102, 241, 0.1); color: var(--primary); }
        .stat-card.maintenance .stat-icon { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stat-card.invoices .stat-icon { background: rgba(6, 182, 212, 0.1); color: var(--info); }
        .stat-card.overdue .stat-icon { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .stat-label {
            color: #6b7280;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .stat-subtext {
            font-size: 0.8rem;
            color: #9ca3af;
            margin-top: 0.25rem;
        }
        
        /* Monthly Summary */
        .monthly-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .monthly-summary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.1);
        }
        
        .monthly-summary-content {
            position: relative;
            z-index: 2;
        }
        
        .monthly-title {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .monthly-amount {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .monthly-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .monthly-stat {
            text-align: center;
        }
        
        .monthly-stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .monthly-stat-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .monthly-stat-subtext {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        /* Dashboard Cards */
        .dashboard-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
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
        
        /* Activity Chart */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Table Styling */
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
        
        /* Message Board */
        .message-board {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .message-item {
            padding: 1rem;
            border-left: 3px solid transparent;
            border-bottom: 1px solid #f3f4f6;
            transition: var(--transition);
        }
        
        .message-item:hover {
            background-color: #f9fafb;
            border-left-color: var(--primary);
        }
        
        .message-user {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .message-meta {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .message-content {
            color: #4b5563;
            margin-bottom: 0.75rem;
        }

        .message-actions {
            text-align: right;
        }
        
        /* Filter Buttons */
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            border: 1px solid #e5e7eb;
            background: white;
            color: #6b7280;
            cursor: pointer;
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Animations */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .control-panel {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }

            .mobile-header {
                display: flex;
            }
            
            .main-content {
                margin-left: 0;
                margin-top: 60px;
                padding: 1rem;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .monthly-stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .monthly-stats {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        <div class="mobile-brand">
            ASRT Admin
        </div>
        <div></div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-crown"></i>
                <span>ASRT Admin</span>
            </a>
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="manage_user.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Manage Users & Units</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="view_rental_requests.php" class="nav-link">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Rental Requests</span>
                    <?php if ($pending > 0): ?>
                        <span class="badge badge-notification bg-danger"><?= $pending ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="manage_maintenance.php" class="nav-link">
                    <i class="fas fa-tools"></i>
                    <span>Maintenance</span>
                    <?php if ($pending_maintenance > 0): ?>
                        <span class="badge badge-notification bg-warning"><?= $pending_maintenance ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="generate_invoice.php" class="nav-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
                    <?php if ($unpaid_invoices > 0): ?>
                        <span class="badge badge-notification bg-info"><?= $unpaid_invoices ?></span>
                    <?php endif; ?>
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
                <a href="admin_kick_unpaid.php" class="nav-link">
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
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="welcome-text">
                <h1>Welcome back, Admin</h1>
                <p>Here's what's happening with your properties</p>
            </div>
            <div class="header-actions">
                <span class="period-badge"><?= $monthName ?></span>
            </div>
        </div>
        
        <!-- Control Panel -->
        <div class="control-panel">
            <!-- Month Picker Card -->
            <div class="month-picker-card animate-fade-in">
                <h5 class="mb-3">Select Period for Statistics</h5>
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-4 col-6">
                        <label for="month" class="form-label">Month</label>
                        <select id="month" name="month" class="form-select">
                            <?php for($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= ($selectedMonth == $m ? 'selected' : '') ?>>
                                    <?= date('F', mktime(0,0,0,$m,1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4 col-6">
                        <label for="year" class="form-label">Year</label>
                        <select id="year" name="year" class="form-select">
                            <?php for($y = date('Y'); $y >= 2023; $y--): ?>
                                <option value="<?= $y ?>" <?= ($selectedYear == $y ? 'selected' : '') ?>>
                                    <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="fas fa-filter me-1"></i> Apply Filter
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary" title="Current Month">
                                <i class="fas fa-sync"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Export Card -->
            <div class="export-card animate-fade-in">
                <h6 class="mb-2">Export Report</h6>
                <p class="text-muted small mb-3">Generate <?= $monthName ?> report</p>
                <div class="d-flex gap-2">
                    <a href="export_report.php?month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>&type=excel" 
                       class="btn btn-success btn-sm flex-fill">
                        <i class="fas fa-file-excel me-1"></i>Excel
                    </a>
                    <a href="export_report.php?month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>&type=pdf" 
                       class="btn btn-danger btn-sm flex-fill">
                        <i class="fas fa-file-pdf me-1"></i>PDF
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card rentals animate-fade-in">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-value" id="pendingRentalsCount"><?= $pending ?></div>
                <div class="stat-label">Pending Rentals</div>
                <div class="stat-subtext">Awaiting approval</div>
            </div>
            <div class="stat-card maintenance animate-fade-in">
                <div class="stat-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-value" id="pendingMaintenanceCount"><?= $pending_maintenance ?></div>
                <div class="stat-label">Maintenance Requests</div>
                <div class="stat-subtext">Need attention</div>
            </div>
            <div class="stat-card invoices animate-fade-in">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-value" id="unpaidInvoicesCount"><?= $unpaid_invoices ?></div>
                <div class="stat-label">Unpaid Invoices</div>
                <div class="stat-subtext">Total outstanding</div>
            </div>
            <div class="stat-card overdue animate-fade-in">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value" id="overdueInvoicesCount"><?= $overdue_invoices ?></div>
                <div class="stat-label">Overdue Invoices</div>
                <div class="stat-subtext">Past due date</div>
            </div>
        </div>
        
        <!-- Monthly Summary -->
        <div class="monthly-summary animate-fade-in">
            <div class="monthly-summary-content">
                <div class="monthly-title">Monthly Performance - <?= $monthName ?></div>
                <div class="monthly-amount">₱<?= number_format($total_earnings, 2) ?></div>
                <div class="monthly-stats">
                    <div class="monthly-stat">
                        <div class="monthly-stat-value"><?= $rentalRequestsData['total'] ?? 0 ?></div>
                        <div class="monthly-stat-label">Rental Requests</div>
                        <div class="monthly-stat-subtext">
                            P:<?= $rentalRequestsData['pending'] ?? 0 ?> 
                            A:<?= $rentalRequestsData['accepted'] ?? 0 ?> 
                            R:<?= $rentalRequestsData['rejected'] ?? 0 ?>
                        </div>
                    </div>
                    <div class="monthly-stat">
                        <div class="monthly-stat-value"><?= $maintenanceRequestsData['total'] ?? 0 ?></div>
                        <div class="monthly-stat-label">Maintenance</div>
                        <div class="monthly-stat-subtext">
                            S:<?= $maintenanceRequestsData['submitted'] ?? 0 ?> 
                            IP:<?= $maintenanceRequestsData['in_progress'] ?? 0 ?> 
                            C:<?= $maintenanceRequestsData['completed'] ?? 0 ?>
                        </div>
                    </div>
                    <div class="monthly-stat">
                        <div class="monthly-stat-value"><?= $new_messages_count ?></div>
                        <div class="monthly-stat-label">Messages</div>
                        <div class="monthly-stat-subtext">New inquiries</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Activity Chart -->
                <div class="dashboard-card animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i>
                        <span>Activity Overview - <?= $monthName ?></span>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Quick Stats -->
                <div class="dashboard-card animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-chart-bar"></i>
                        <span>Quick Stats</span>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="fw-bold text-primary fs-4"><?= array_sum($chartData['new_rentals']) ?></div>
                                <div class="text-muted small">New Rentals</div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="fw-bold text-warning fs-4"><?= array_sum($chartData['new_maintenance']) ?></div>
                                <div class="text-muted small">Maintenance</div>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold text-info fs-4"><?= array_sum($chartData['new_messages']) ?></div>
                                <div class="text-muted small">Messages</div>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold text-success fs-4"><?= $paid_invoices_count ?></div>
                                <div class="text-muted small">Paid Invoices</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Requests Section -->
        <div class="row mt-3">
            <!-- Rental Requests -->
            <div class="col-lg-6">
                <div class="dashboard-card h-100 animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-list-alt"></i>
                        <span>Latest Rental Requests</span>
                        <span class="badge bg-primary ms-2" id="latestRequestsBadge"><?= $pending ?></span>
                    </div>
                    <div class="card-body p-0" id="latestRequestsContainer">
                        <div class="text-center p-4 text-muted">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading rental requests...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Maintenance Requests -->
            <div class="col-lg-6">
                <div class="dashboard-card h-100 animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-tools"></i>
                        <span>Latest Maintenance Requests</span>
                        <span class="badge bg-warning ms-2" id="latestMaintenanceBadge"><?= $pending_maintenance ?></span>
                    </div>
                    <div class="card-body p-0" id="latestMaintenanceContainer">
                        <div class="text-center p-4 text-muted">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading maintenance requests...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Messages Section -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="dashboard-card animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-comments"></i>
                        <span>Message Requests</span>
                    </div>
                    <div class="card-body">
                        <div class="filter-buttons">
                            <button type="button" class="filter-btn active" id="filterRecentBtn">Recent</button>
                            <button type="button" class="filter-btn" id="filterAllBtn">All Messages</button>
                        </div>
                        <div class="message-board" id="messageBoardContainer">
                            <div class="text-center p-4 text-muted">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Loading messages...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Mobile menu functionality
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const mobileOverlay = document.getElementById('mobileOverlay');

    function toggleMobileMenu() {
        sidebar.classList.toggle('active');
        mobileOverlay.classList.toggle('active');
    }

    mobileMenuBtn.addEventListener('click', toggleMobileMenu);
    mobileOverlay.addEventListener('click', toggleMobileMenu);

    // Close mobile menu when clicking on nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                sidebar.classList.remove('active');
                mobileOverlay.classList.remove('active');
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            sidebar.classList.remove('active');
            mobileOverlay.classList.remove('active');
        }
    });

    // Real-time updates
    let isTabActive = true;

    document.addEventListener('visibilitychange', function() {
        isTabActive = !document.hidden;
        if (isTabActive) {
            fetchDashboardCounts();
            fetchLatestRequests();
            fetchLatestMaintenance();
        }
    });

    function fetchDashboardCounts() {
        if (!isTabActive) return;
        
        fetch('../AJAX/ajax_admin_dashboard_counts.php')
            .then(res => res.json())
            .then(data => {
                if (data && !data.error) {
                    document.getElementById('pendingRentalsCount').textContent = data.pending_rentals ?? 0;
                    document.getElementById('pendingMaintenanceCount').textContent = data.pending_maintenance ?? 0;
                    document.getElementById('unpaidInvoicesCount').textContent = data.unpaid_invoices ?? 0;
                    document.getElementById('overdueInvoicesCount').textContent = data.overdue_invoices ?? 0;
                }
            })
            .catch(err => console.log('Error fetching counts:', err));
    }

    function fetchLatestRequests() {
        if (!isTabActive) return;
        
        fetch('../AJAX/ajax_admin_dashboard_latest_requests.php?mark_seen=true')
            .then(res => res.text())
            .then(html => {
                document.getElementById('latestRequestsContainer').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('latestRequestsContainer').innerHTML = 
                    '<div class="text-center p-4 text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error loading requests</p></div>';
            });
    }

    function fetchLatestMaintenance() {
        if (!isTabActive) return;
        
        fetch('../AJAX/ajax_admin_dashboard_latest_maintenance.php?mark_seen=true')
            .then(res => res.text())
            .then(html => {
                document.getElementById('latestMaintenanceContainer').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('latestMaintenanceContainer').innerHTML = 
                    '<div class="text-center p-4 text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error loading maintenance</p></div>';
            });
    }

    let messageFilter = 'recent';
    function fetchMessages() {
        if (!isTabActive) return;
        
        fetch('../AJAX/ajax_admin_dashboard_messages.php?filter=' + messageFilter)
            .then(res => res.text())
            .then(html => {
                document.getElementById('messageBoardContainer').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('messageBoardContainer').innerHTML = 
                    '<div class="text-center p-4 text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error loading messages</p></div>';
            });
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', () => {
        fetchDashboardCounts();
        fetchLatestRequests();
        fetchLatestMaintenance();
        fetchMessages();
        
        // Filter buttons
        document.getElementById('filterRecentBtn').addEventListener('click', function() {
            messageFilter = 'recent';
            this.classList.add('active');
            document.getElementById('filterAllBtn').classList.remove('active');
            fetchMessages();
        });
        
        document.getElementById('filterAllBtn').addEventListener('click', function() {
            messageFilter = 'all';
            this.classList.add('active');
            document.getElementById('filterRecentBtn').classList.remove('active');
            fetchMessages();
        });
    });

    // Poll every 15 seconds
    setInterval(() => {
        if (isTabActive) {
            fetchDashboardCounts();
            fetchLatestRequests();
            fetchLatestMaintenance();
            fetchMessages();
        }
    }, 15000);

    // Chart initialization
    const chartData = <?= json_encode($chartData) ?>;
    const ctx = document.getElementById('activityChart').getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'New Rentals',
                    data: chartData.new_rentals,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Maintenance',
                    data: chartData.new_maintenance,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Messages',
                    data: chartData.new_messages,
                    borderColor: '#06b6d4',
                    backgroundColor: 'rgba(6, 182, 212, 0.1)',
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    position: 'top',
                }
            },
            scales: {
                x: { 
                    grid: { display: false }
                },
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                }
            }
        }
    });
    </script>
</body>
</html>