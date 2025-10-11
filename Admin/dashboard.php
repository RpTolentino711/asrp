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

function timeAgo($datetime) {
    $sent = new DateTime($datetime);
    $now = new DateTime();
    $diff = $now->diff($sent);

    if ($diff->days > 0) return $diff->days . ' days ago';
    if ($diff->h > 0) return $diff->h . ' hours ago';
    if ($diff->i > 0) return $diff->i . ' minutes ago';
    return 'Just now';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1e293b;
            --sidebar-width: 260px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #334155;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            width: var(--sidebar-width);
            height: 100vh;
            background: white;
            border-right: 1px solid #e2e8f0;
            z-index: 1000;
            transition: transform 0.3s;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-section {
            padding: 1rem 0;
        }

        .nav-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #64748b;
            text-decoration: none;
            transition: all 0.2s;
            position: relative;
        }
        
        .nav-link:hover {
            background: #f1f5f9;
            color: var(--primary);
        }
        
        .nav-link.active {
            background: #eef2ff;
            color: var(--primary);
            font-weight: 500;
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--primary);
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1rem;
        }
        
        .nav-badge {
            margin-left: auto;
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Mobile */
        .mobile-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .mobile-overlay.active { display: block; }

        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            z-index: 1001;
            padding: 0 1rem;
            align-items: center;
            justify-content: space-between;
        }

        .mobile-menu-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }

        /* Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 0.95rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            transition: all 0.2s;
        }

        .stat-card:hover {
            border-color: var(--primary-light);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
        }

        .stat-card.primary .stat-icon { background: #eef2ff; color: var(--primary); }
        .stat-card.warning .stat-icon { background: #fef3c7; color: var(--warning); }
        .stat-card.info .stat-icon { background: #dbeafe; color: var(--info); }
        .stat-card.danger .stat-icon { background: #fee2e2; color: var(--danger); }
        .stat-card.success .stat-icon { background: #d1fae5; color: var(--secondary); }

        /* Period Selector */
        .period-selector {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .period-selector .form-select {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            backdrop-filter: blur(10px);
        }

        .period-selector .form-select:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: white;
            color: white;
        }

        .period-selector .form-select option {
            background: var(--primary);
            color: white;
        }

        .period-selector .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            font-size: 0.875rem;
        }

        .period-selector .btn-light {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            backdrop-filter: blur(10px);
        }

        .period-selector .btn-light:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: white;
            color: white;
        }

        .period-selector .btn-outline-light {
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: white;
        }

        .period-selector .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: white;
        }

        .period-selector .btn-success {
            background: var(--secondary);
            border: none;
            color: white;
        }

        .period-selector .btn-success:hover {
            background: #059669;
        }

        /* Chart */
        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #64748b;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            background: none;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary);
            border-color: transparent;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary);
            border-color: var(--primary);
            background: none;
        }

        /* Request Items */
        .request-item {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        .request-item:last-child {
            border-bottom: none;
        }

        .request-item:hover {
            background: #f8fafc;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }

        .request-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .request-meta {
            font-size: 0.875rem;
            color: #64748b;
        }

        .badge {
            padding: 0.35rem 0.75rem;
            font-weight: 600;
            border-radius: 6px;
            font-size: 0.75rem;
        }

        .badge-primary { background: #eef2ff; color: var(--primary); }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-danger { background: #fee2e2; color: #dc2626; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            border: 1px solid #e2e8f0;
            color: #64748b;
            background: white;
        }

        .btn-outline-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .btn-success {
            background: var(--secondary);
            color: white;
        }

        /* Notification Badge */
        .notification-badge {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .page-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-value {
                font-size: 1.75rem;
            }

            .period-selector {
                padding: 1rem;
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
        <div class="fw-bold">ASRT Admin</div>
        <div></div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-crown"></i>
            <span>ASRT Admin</span>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Overview</div>
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <a href="manage_user.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>Users & Units</span>
            </a>
            <a href="add_unit.php" class="nav-link">
                <i class="fas fa-building"></i>
                <span>Add Unit</span>
            </a>
            <a href="admin_add_handyman.php" class="nav-link">
                <i class="fas fa-user-plus"></i>
                <span>Add Handyman</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Requests</div>
            <a href="view_rental_requests.php" class="nav-link">
                <i class="fas fa-clipboard-check"></i>
                <span>Rentals</span>
                <?php if ($pending > 0): ?>
                    <span class="nav-badge bg-danger text-white notification-badge" id="sidebarRentalBadge"><?= $pending ?></span>
                <?php endif; ?>
            </a>
            <a href="manage_maintenance.php" class="nav-link">
                <i class="fas fa-tools"></i>
                <span>Maintenance</span>
                <?php if ($pending_maintenance > 0): ?>
                    <span class="nav-badge bg-warning text-white" id="sidebarMaintenanceBadge"><?= $pending_maintenance ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Financial</div>
            <a href="generate_invoice.php" class="nav-link">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Invoices</span>
                <?php if ($unpaid_invoices > 0): ?>
                    <span class="nav-badge bg-info text-white"><?= $unpaid_invoices ?></span>
                <?php endif; ?>
            </a>
            <a href="admin_kick_unpaid.php" class="nav-link">
                <i class="fas fa-exclamation-circle"></i>
                <span>Overdue</span>
                <?php if ($overdue_invoices > 0): ?>
                    <span class="nav-badge bg-danger text-white"><?= $overdue_invoices ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="nav-section">
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back! Here's your property overview</p>
        </div>

        <!-- Period Selector -->
        <div class="period-selector">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="month" class="form-label">Month</label>
                    <select id="month" name="month" class="form-select">
                        <?php for($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= ($selectedMonth == $m ? 'selected' : '') ?>>
                                <?= date('F', mktime(0,0,0,$m,1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="year" class="form-label">Year</label>
                    <select id="year" name="year" class="form-select">
                        <?php for($y = date('Y'); $y >= 2023; $y--): ?>
                            <option value="<?= $y ?>" <?= ($selectedYear == $y ? 'selected' : '') ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                        <button type="submit" class="btn btn-light">
                            <i class="fas fa-filter me-2"></i>Apply
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-light">
                            <i class="fas fa-sync"></i>
                        </a>
                        <a href="export_monthly_data.php?month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>&type=excel" 
                           class="btn btn-success">
                            <i class="fas fa-file-excel me-2"></i>Export
                        </a>
                    </div>
                </div>
            </form>
            <div class="mt-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="opacity: 0.9;"><?= $monthName ?></span>
                    <span class="fw-bold fs-5">₱<?= number_format($total_earnings, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-header">
                    <span class="stat-label">Pending Rentals</span>
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                </div>
                <div class="stat-value" id="pendingRentalsCount"><?= $pending ?></div>
            </div>

            <div class="stat-card warning">
                <div class="stat-header">
                    <span class="stat-label">Maintenance</span>
                    <div class="stat-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                </div>
                <div class="stat-value" id="pendingMaintenanceCount"><?= $pending_maintenance ?></div>
            </div>

            <div class="stat-card info">
                <div class="stat-header">
                    <span class="stat-label">Unpaid Invoices</span>
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                </div>
                <div class="stat-value" id="unpaidInvoicesCount"><?= $unpaid_invoices ?></div>
            </div>

            <div class="stat-card danger">
                <div class="stat-header">
                    <span class="stat-label">Overdue</span>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-value" id="overdueInvoicesCount"><?= $overdue_invoices ?></div>
            </div>

            <div class="stat-card success">
                <div class="stat-header">
                    <span class="stat-label">Revenue (<?= date('M', strtotime($startDate)) ?>)</span>
                    <div class="stat-icon">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                </div>
                <div class="stat-value">₱<?= number_format($total_earnings / 1000, 1) ?>k</div>
            </div>
        </div>

        <!-- Activity Chart -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i>
                Activity Trend - <?= $monthName ?>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Requests Section -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clipboard-list"></i>
                        Recent Rental Requests
                        <span class="badge badge-primary ms-2" id="latestRequestsBadge"><?= $pending ?></span>
                    </div>
                    <div class="card-body p-0" id="latestRequestsContainer">
                        <div class="empty-state">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-wrench"></i>
                        Recent Maintenance
                        <span class="badge badge-warning ms-2" id="latestMaintenanceBadge"><?= $pending_maintenance ?></span>
                    </div>
                    <div class="card-body p-0" id="latestMaintenanceContainer">
                        <div class="empty-state">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-envelope"></i>
                Messages
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <button class="nav-link active" id="filterRecentBtn">Recent</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="filterAllBtn">All Messages</button>
                    </li>
                </ul>
                <div id="messageBoardContainer">
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading messages...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Mobile menu
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const mobileOverlay = document.getElementById('mobileOverlay');

    function toggleMobileMenu() {
        sidebar.classList.toggle('active');
        mobileOverlay.classList.toggle('active');
    }

    mobileMenuBtn?.addEventListener('click', toggleMobileMenu);
    mobileOverlay?.addEventListener('click', toggleMobileMenu);

    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                sidebar.classList.remove('active');
                mobileOverlay.classList.remove('active');
            }
        });
    });

    // Real-time updates
    let lastPendingCount = <?= $pending ?>;
    let lastMaintenanceCount = <?= $pending_maintenance ?>;
    let lastUnseenRentals = <?= $unseen_rentals ?>;
    let lastNewMaintenanceCount = <?= $new_maintenance_requests ?>;
    let isFirstLoad = true;
    let isTabActive = true;

    document.addEventListener('visibilitychange', function() {
        isTabActive = !document.hidden;
        if (isTabActive) {
            fetchDashboardCounts();
            fetchLatestRequests();
            fetchLatestMaintenance();
            fetchMessages();
        }
    });

    function fetchDashboardCounts() {
        if (!isTabActive) return;
        
        fetch('../AJAX/ajax_admin_dashboard_counts.php')
            .then(res => res.json())
            .then(data => {
                if (data && !data.error) {
                    const currentPending = data.pending_rentals ?? 0;
                    const currentMaintenance = data.pending_maintenance ?? 0;
                    const currentUnpaid = data.unpaid_invoices ?? 0;
                    const currentOverdue = data.overdue_invoices ?? 0;
                    const currentUnseenRentals = data.unseen_rentals ?? 0;
                    const currentNewMaintenance = data.new_maintenance_requests ?? 0;

                    document.getElementById('pendingRentalsCount').textContent = currentPending;
                    document.getElementById('pendingMaintenanceCount').textContent = currentMaintenance;
                    document.getElementById('unpaidInvoicesCount').textContent = currentUnpaid;
                    document.getElementById('overdueInvoicesCount').textContent = currentOverdue;

                    if (!isFirstLoad && currentUnseenRentals > lastUnseenRentals) {
                        const newRequests = currentUnseenRentals - lastUnseenRentals;
                        showNotification(`${newRequests} new rental request(s)`, 'primary');
                        updateSidebarBadge(currentPending);
                    }
                    
                    if (!isFirstLoad && currentNewMaintenance > lastNewMaintenanceCount) {
                        const newMaintenance = currentNewMaintenance - lastNewMaintenanceCount;
                        showNotification(`${newMaintenance} new maintenance request(s)`, 'warning');
                        updateMaintenanceSidebarBadge(currentMaintenance);
                    }
                    
                    lastPendingCount = currentPending;
                    lastMaintenanceCount = currentMaintenance;
                    lastUnseenRentals = currentUnseenRentals;
                    lastNewMaintenanceCount = currentNewMaintenance;
                    isFirstLoad = false;
                }
            })
            .catch(err => console.log('Error:', err));
    }

    function updateSidebarBadge(count) {
        const badge = document.getElementById('sidebarRentalBadge');
        if (badge) {
            badge.textContent = count;
            badge.classList.add('notification-badge');
        } else if (count > 0) {
            const rentalLink = document.querySelector('a[href="view_rental_requests.php"]');
            if (rentalLink) {
                const newBadge = document.createElement('span');
                newBadge.id = 'sidebarRentalBadge';
                newBadge.className = 'nav-badge bg-danger text-white notification-badge';
                newBadge.textContent = count;
                rentalLink.appendChild(newBadge);
            }
        }
    }

    function updateMaintenanceSidebarBadge(count) {
        const badge = document.getElementById('sidebarMaintenanceBadge');
        if (badge) {
            badge.textContent = count;
            badge.classList.add('notification-badge');
        } else if (count > 0) {
            const maintenanceLink = document.querySelector('a[href="manage_maintenance.php"]');
            if (maintenanceLink) {
                const newBadge = document.createElement('span');
                newBadge.id = 'sidebarMaintenanceBadge';
                newBadge.className = 'nav-badge bg-warning text-white notification-badge';
                newBadge.textContent = count;
                maintenanceLink.appendChild(newBadge);
            }
        }
    }

    function fetchLatestRequests() {
        if (!isTabActive) return;
        
        fetch('../AJAX/ajax_admin_dashboard_latest_requests.php?mark_seen=true')
            .then(res => res.text())
            .then(html => {
                document.getElementById('latestRequestsContainer').innerHTML = html;
                
                const containerDiv = document.querySelector('#latestRequestsContainer [data-count]');
                if (containerDiv) {
                    const currentCount = parseInt(containerDiv.getAttribute('data-count'));
                    const badge = document.getElementById('latestRequestsBadge');
                    if (badge) {
                        badge.textContent = currentCount;
                    }
                }
            })
            .catch(err => {
                document.getElementById('latestRequestsContainer').innerHTML = 
                    '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading requests</p></div>';
            });
    }

    function fetchLatestMaintenance() {
        if (!isTabActive) return;
        
        fetch('../AJAX/ajax_admin_dashboard_latest_maintenance.php?mark_seen=true')
            .then(res => res.text())
            .then(html => {
                document.getElementById('latestMaintenanceContainer').innerHTML = html;
                
                const containerDiv = document.querySelector('#latestMaintenanceContainer [data-count]');
                if (containerDiv) {
                    const currentCount = parseInt(containerDiv.getAttribute('data-count'));
                    const badge = document.getElementById('latestMaintenanceBadge');
                    if (badge) {
                        badge.textContent = currentCount;
                    }
                }
            })
            .catch(err => {
                document.getElementById('latestMaintenanceContainer').innerHTML = 
                    '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading maintenance</p></div>';
            });
    }

    let messageFilter = 'recent';
    function fetchMessages() {
        if (!isTabActive) return;
        
        fetch('../AJAX/ajax_admin_dashboard_messages.php?filter=' + messageFilter)
            .then(res => res.text())
            .then(html => {
                document.getElementById('messageBoardContainer').innerHTML = html;
                
                document.querySelectorAll('.delete-message-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const messageId = this.getAttribute('data-message-id');
                        handleMessageDelete(messageId);
                    });
                });
            })
            .catch(err => {
                document.getElementById('messageBoardContainer').innerHTML = 
                    '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading messages</p></div>';
            });
    }

    function handleMessageDelete(messageId) {
        if (!confirm('Delete this message?')) return;

        const formData = new FormData();
        formData.append('soft_delete_msg_id', messageId);

        fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
                if (messageElement) {
                    messageElement.style.opacity = '0';
                    setTimeout(() => {
                        messageElement.remove();
                        fetchMessages();
                    }, 300);
                }
                showNotification('Message deleted', 'success');
            }
        })
        .catch(err => showNotification('Error deleting message', 'danger'));
    }

    function showNotification(message, type = 'info') {
        const colors = {
            primary: '#4f46e5',
            warning: '#f59e0b',
            success: '#10b981',
            danger: '#ef4444',
            info: '#3b82f6'
        };

        const icons = {
            primary: 'info-circle',
            warning: 'exclamation-triangle',
            success: 'check-circle',
            danger: 'times-circle',
            info: 'info-circle'
        };

        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-left: 4px solid ${colors[type]};
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            min-width: 300px;
            animation: slideIn 0.3s ease;
            transition: all 0.3s ease;
        `;
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-${icons[type]}" 
                   style="color: ${colors[type]}; font-size: 1.25rem;"></i>
                <span style="color: #334155; font-weight: 500;">${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        fetchDashboardCounts();
        fetchLatestRequests();
        fetchLatestMaintenance();
        fetchMessages();
        
        const recentBtn = document.getElementById('filterRecentBtn');
        const allBtn = document.getElementById('filterAllBtn');
        
        recentBtn?.addEventListener('click', function() {
            messageFilter = 'recent';
            this.classList.add('active');
            allBtn.classList.remove('active');
            fetchMessages();
        });
        
        allBtn?.addEventListener('click', function() {
            messageFilter = 'all';
            this.classList.add('active');
            recentBtn.classList.remove('active');
            fetchMessages();
        });
    });

    // Poll every 10 seconds
    setInterval(() => {
        if (isTabActive) {
            fetchDashboardCounts();
            fetchLatestRequests();
            fetchLatestMaintenance();
            fetchMessages();
        }
    }, 10000);

    // Chart
    const chartData = <?= json_encode($chartData) ?>;
    const ctx = document.getElementById('activityChart').getContext('2d');

    const activityChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Rentals',
                    data: chartData.new_rentals,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Maintenance',
                    data: chartData.new_maintenance,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Messages',
                    data: chartData.new_messages,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
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
                    labels: { 
                        usePointStyle: true,
                        padding: 15,
                        font: { size: 12, family: 'Inter' }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1e293b',
                    bodyColor: '#64748b',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                x: { 
                    grid: { display: false },
                    ticks: { color: '#64748b', font: { size: 11 } }
                },
                y: { 
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { color: '#64748b', font: { size: 11 } }
                }
            }
        }
    });

    // Handle window resize for chart
    window.addEventListener('resize', () => {
        if (activityChart && window.innerWidth <= 768) {
            activityChart.options.plugins.legend.labels.padding = 10;
            activityChart.options.plugins.legend.labels.font.size = 10;
            activityChart.update();
        }
    });
    </script>
</body>
</html>