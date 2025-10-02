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

// Get statistics for the selected period
$counts = $db->getAdminDashboardCounts();
$monthlyStats = $db->getMonthlyEarningsStats($startDate, $endDate);
$chartData = $db->getAdminMonthChartData($startDate, $endDate);

// Extract values
$pending = $counts['pending_rentals'] ?? 0;
$pending_maintenance = $counts['pending_maintenance'] ?? 0;
$unpaid_invoices = $counts['unpaid_invoices'] ?? 0;
$overdue_invoices = $counts['overdue_invoices'] ?? 0;
$total_earnings = $monthlyStats['total_earnings'] ?? 0;
$paid_invoices_count = $monthlyStats['paid_invoices_count'] ?? 0;
$new_messages_count = $monthlyStats['new_messages_count'] ?? 0;

// Soft delete logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soft_delete_msg_id'])) {
    $msg_id = intval($_POST['soft_delete_msg_id']);
    $db->executeStatement("UPDATE free_message SET is_deleted = 1 WHERE Message_ID = ?", [$msg_id]);
    $filterParam = isset($_GET['filter']) ? 'filter=' . urlencode($_GET['filter']) : '';
    header("Location: dashboard.php" . ($filterParam ? "?$filterParam" : ""));
    exit();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, minimum-scale=1.0, maximum-scale=5.0">
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
            --sidebar-width: 260px;
            --header-height: 70px;
            --border-radius: 10px;
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
            overflow-x: hidden;
            position: relative;
            font-size: 14px;
        }

        /* Mobile Menu Overlay */
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

        /* Mobile Header */
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
            font-size: 1.25rem;
            color: var(--dark);
            padding: 0.5rem;
            border-radius: 8px;
            transition: var(--transition);
        }

        .mobile-menu-btn:hover {
            background: rgba(0,0,0,0.1);
        }

        .mobile-brand {
            font-weight: 700;
            font-size: 1rem;
            color: var(--dark);
        }
        
        /* Sidebar Styling */
        .sidebar {
            position: fixed;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--dark), var(--darker));
            color: white;
            padding: 1.25rem 0.75rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: var(--transition);
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 0 0 1.25rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1.25rem;
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            text-decoration: none;
            padding: 0 0.5rem;
        }
        
        .sidebar-brand i {
            color: var(--primary);
            font-size: 1.25rem;
        }
        
        .nav-item {
            margin-bottom: 0.25rem;
            position: relative;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.6rem 0.75rem;
            color: rgba(255, 255, 255, 0.85);
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 0.6rem;
            font-size: 0.9rem;
        }
        
        .badge-notification {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.65rem;
            padding: 0.2rem 0.4rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            transition: var(--transition);
        }
        
        /* Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .welcome-text h1 {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .welcome-text p {
            color: #6b7280;
            font-size: 0.85rem;
        }
        
        /* Stats Summary Button */
        .stats-summary-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
            width: 100%;
            text-align: left;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stats-summary-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stats-summary-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.1);
        }
        
        .stats-summary-content {
            position: relative;
            z-index: 2;
        }
        
        .stats-summary-title {
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
            opacity: 0.9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stats-summary-amount {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stats-summary-period {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-bottom: 0.75rem;
        }
        
        .stats-summary-toggle {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            font-size: 0.8rem;
        }
        
        .stats-summary-toggle.collapsed i {
            transform: rotate(-90deg);
        }
        
        /* Stats Dropdown Content */
        .stats-dropdown-content {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.25rem;
        }
        
        .month-picker-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.25rem;
        }
        
        .period-badge {
            background: var(--primary);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            border-left: 4px solid var(--primary);
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
            opacity: 0;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card.rentals { border-left-color: var(--primary); }
        .stat-card.maintenance { border-left-color: var(--warning); }
        .stat-card.invoices { border-left-color: var(--info); }
        .stat-card.overdue { border-left-color: var(--danger); }
        .stat-card.earnings { border-left-color: var(--secondary); }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.05);
        }
        
        .stat-card.rentals .stat-icon { background: rgba(99, 102, 241, 0.1); color: var(--primary); }
        .stat-card.maintenance .stat-icon { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stat-card.invoices .stat-icon { background: rgba(6, 182, 212, 0.1); color: var(--info); }
        .stat-card.overdue .stat-icon { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .stat-card.earnings .stat-icon { background: rgba(16, 185, 129, 0.1); color: var(--secondary); }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            transition: var(--transition);
        }
        
        .stat-card:hover .stat-value {
            color: var(--primary);
        }
        
        .stat-label {
            color: #6b7280;
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .stat-subtext {
            font-size: 0.7rem;
            color: #9ca3af;
            margin-top: 0.25rem;
        }
        
        /* Monthly Stats */
        .monthly-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .monthly-stat {
            text-align: center;
        }
        
        .monthly-stat-value {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .monthly-stat-label {
            font-size: 0.7rem;
            opacity: 0.8;
        }
        
        /* Dashboard Cards */
        .dashboard-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1rem 1.25rem;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        
        .card-header i {
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* Activity Chart */
        .chart-container {
            position: relative;
            height: 250px;
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
            font-size: 0.8rem;
        }
        
        .custom-table th {
            background-color: #f9fafb;
            padding: 0.6rem 0.8rem;
            font-weight: 600;
            text-align: left;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.75rem;
        }
        
        .custom-table td {
            padding: 0.8rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.8rem;
        }
        
        .custom-table tr:last-child td {
            border-bottom: none;
        }
        
        .custom-table tr:hover {
            background-color: #f9fafb;
        }
        
        /* Message Board */
        .message-board {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .message-item {
            padding: 0.8rem;
            border-left: 3px solid transparent;
            border-bottom: 1px solid #f3f4f6;
            transition: var(--transition);
        }
        
        .message-item:hover {
            background-color: #f9fafb;
            border-left-color: var(--primary);
        }
        
        .message-item.deleted {
            opacity: 0.6;
        }
        
        .message-user {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.2rem;
            font-size: 0.8rem;
        }
        
        .message-meta {
            font-size: 0.7rem;
            color: #6b7280;
            margin-bottom: 0.4rem;
        }
        
        .message-content {
            color: #4b5563;
            margin-bottom: 0.6rem;
            font-size: 0.8rem;
        }
        
        /* Filter Buttons */
        .filter-buttons {
            display: flex;
            gap: 0.4rem;
            margin-bottom: 0.8rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.4rem 0.8rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.75rem;
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

        /* Mobile Card Layout */
        .mobile-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 0.8rem;
            padding: 0.8rem;
            border-left: 4px solid var(--primary);
        }

        .mobile-card-header {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--dark);
            margin-bottom: 0.6rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mobile-card-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.4rem;
            font-size: 0.75rem;
        }

        .mobile-card-detail .label {
            font-weight: 500;
            color: #6b7280;
        }

        .mobile-card-detail .value {
            color: var(--dark);
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.8rem;
            margin-bottom: 1.25rem;
        }

        .summary-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 0.8rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-top: 3px solid var(--primary);
        }

        .summary-card.warning { border-top-color: var(--warning); }
        .summary-card.info { border-top-color: var(--info); }
        
        /* Mobile Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
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
                gap: 0.8rem;
                margin-bottom: 1.25rem;
            }

            .welcome-text h1 {
                font-size: 1.3rem;
            }

            .stats-summary-btn {
                padding: 1rem 1.25rem;
            }

            .stats-summary-amount {
                font-size: 1.75rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.8rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
                margin-bottom: 0.6rem;
            }

            .stat-value {
                font-size: 1.25rem;
            }

            .stat-label {
                font-size: 0.75rem;
            }

            .monthly-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .card-body {
                padding: 1rem;
            }

            .card-header {
                padding: 0.8rem 1rem;
                font-size: 0.9rem;
            }

            .chart-container {
                height: 200px;
            }

            .summary-cards {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
            }

            .summary-card {
                padding: 0.6rem;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 0.75rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 180px;
            }

            .message-board {
                max-height: 250px;
            }

            .message-item {
                padding: 0.6rem;
            }

            .form-control, .form-select {
                font-size: 16px;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                justify-content: center;
            }

            .filter-btn {
                flex: 1;
                text-align: center;
            }

            .monthly-stats {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .welcome-text h1 {
                font-size: 1.1rem;
            }

            .dashboard-card {
                border-radius: 8px;
            }

            .btn {
                font-size: 0.8rem;
                padding: 0.6rem 1.25rem;
            }

            .form-control, .form-select {
                padding: 0.6rem;
                font-size: 14px;
            }

            .stats-summary-btn {
                padding: 0.8rem 1rem;
            }

            .stats-summary-amount {
                font-size: 1.5rem;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .filter-btn, .nav-link, .mobile-menu-btn {
                min-height: 44px;
                min-width: 44px;
            }

            .stat-card:hover {
                transform: none;
            }
        }
        
        /* Animations */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Utilities */
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
            padding: 0.3rem 0.5rem;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
        }

        .form-control, .form-select {
            font-size: 0.8rem;
            padding: 0.5rem 0.75rem;
        }

        .form-label {
            font-size: 0.8rem;
            margin-bottom: 0.4rem;
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
                <span>Admin</span>
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
                    <?php if ($overdue_invoices > 0): ?>
                        <span class="badge badge-notification bg-danger">Overdue: <?= $overdue_invoices ?></span>
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
            <div class="header-actions d-none d-md-block">
                <span class="period-badge"><?= $monthName ?></span>
            </div>
        </div>
        
        <!-- Stats Summary Button -->
        <button class="stats-summary-btn animate-fade-in" type="button" data-bs-toggle="collapse" data-bs-target="#statsDropdownContent">
            <div class="stats-summary-content">
                <div class="stats-summary-title">
                    <span>Monthly Revenue - <?= $monthName ?></span>
                    <button class="stats-summary-toggle collapsed" type="button">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div class="stats-summary-amount">₱<?= number_format($total_earnings, 2) ?></div>
                <div class="stats-summary-period">Period: <?= date('M d', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?></div>
                
                <div class="monthly-stats">
                    <div class="monthly-stat">
                        <div class="monthly-stat-value"><?= $paid_invoices_count ?></div>
                        <div class="monthly-stat-label">Paid Invoices</div>
                    </div>
                    <div class="monthly-stat">
                        <div class="monthly-stat-value"><?= $pending_maintenance ?></div>
                        <div class="monthly-stat-label">Maintenance</div>
                    </div>
                    <div class="monthly-stat">
                        <div class="monthly-stat-value"><?= $new_messages_count ?></div>
                        <div class="monthly-stat-label">Messages</div>
                    </div>
                </div>
            </div>
        </button>

        <!-- Stats Dropdown Content -->
        <div class="collapse" id="statsDropdownContent">
            <div class="stats-dropdown-content animate-fade-in">
                <div class="month-picker-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-3" style="font-size: 1rem;">Select Period for Statistics</h5>
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
                                        <a href="generate_monthly_report.php?month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" 
                                           class="btn btn-danger" target="_blank" title="Export PDF Report">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="dashboard.php" class="btn btn-outline-secondary" title="Current Month">
                                            <i class="fas fa-sync"></i>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="text-muted small">Current Selection</div>
                            <div class="text-success fw-bold mt-1">₱<?= number_format($total_earnings, 2) ?> Revenue</div>
                        </div>
                    </div>
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
            <div class="stat-card maintenance animate-fade-in" style="animation-delay: 0.1s;">
                <div class="stat-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-value" id="pendingMaintenanceCount"><?= $pending_maintenance ?></div>
                <div class="stat-label">Maintenance Requests</div>
                <div class="stat-subtext">Need attention</div>
            </div>
            <div class="stat-card invoices animate-fade-in" style="animation-delay: 0.2s;">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-value" id="unpaidInvoicesCount"><?= $unpaid_invoices ?></div>
                <div class="stat-label">Unpaid Invoices</div>
                <div class="stat-subtext">Total outstanding</div>
            </div>
            <div class="stat-card overdue animate-fade-in" style="animation-delay: 0.3s;">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value" id="overdueInvoicesCount"><?= $overdue_invoices ?></div>
                <div class="stat-label">Overdue Invoices</div>
                <div class="stat-subtext">Past due date</div>
            </div>
        </div>
        
        <!-- Activity Overview Card -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-chart-line"></i>
                <span>Activity Overview - <?= $monthName ?></span>
            </div>
            <div class="card-body">
                <!-- Mobile Summary Cards -->
                <div class="summary-cards d-md-none">
                    <div class="summary-card">
                        <div class="fw-bold fs-6"><?= array_sum($chartData['new_rentals']) ?></div>
                        <div class="text-muted small">New Rentals</div>
                    </div>
                    <div class="summary-card warning">
                        <div class="fw-bold fs-6"><?= array_sum($chartData['new_maintenance']) ?></div>
                        <div class="text-muted small">Maintenance</div>
                    </div>
                    <div class="summary-card info">
                        <div class="fw-bold fs-6"><?= array_sum($chartData['new_messages']) ?></div>
                        <div class="text-muted small">Messages</div>
                    </div>
                </div>

                <!-- Desktop Summary -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3" style="font-size: 0.9rem;">Summary for <?= date('M d, Y', strtotime($startDate)) ?> to <?= date('M d, Y', strtotime($endDate)) ?></h6>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="bg-primary-light p-2 rounded" style="font-size: 0.8rem;">
                                <div class="fw-bold"><?= array_sum($chartData['new_rentals']) ?></div>
                                <div class="text-muted">New Rentals</div>
                            </div>
                            <div class="bg-warning-light p-2 rounded" style="font-size: 0.8rem;">
                                <div class="fw-bold"><?= array_sum($chartData['new_maintenance']) ?></div>
                                <div class="text-muted">Maintenance</div>
                            </div>
                            <div class="bg-info-light p-2 rounded" style="font-size: 0.8rem;">
                                <div class="fw-bold"><?= array_sum($chartData['new_messages']) ?></div>
                                <div class="text-muted">Messages</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Mobile Chart -->
                <div class="d-md-none">
                    <div class="chart-container">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Rental Requests Card -->
            <div class="col-lg-6">
                <div class="dashboard-card h-100 animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-list-alt"></i>
                        <span>Latest Rental Requests</span>
                    </div>
                    <div class="card-body p-0" id="latestRequestsContainer">
                        <!-- Latest requests will be loaded here via AJAX -->
                        <noscript>
                        <div class="text-center p-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p>No pending rental requests</p>
                        </div>
                        </noscript>
                    </div>
                </div>
            </div>
            
            <!-- Message Board Card -->
            <div class="col-lg-6">
                <div class="dashboard-card h-100 animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-comments"></i>
                        <span>Message Requests</span>
                    </div>
                    <div class="card-body">
                        <div class="filter-buttons">
                            <button type="button" class="filter-btn" id="filterRecentBtn">Recent</button>
                            <button type="button" class="filter-btn" id="filterAllBtn">All Messages</button>
                        </div>
                        <div class="message-board" id="messageBoardContainer">
                            <!-- Messages will be loaded here via AJAX -->
                            <noscript>
                            <div class="text-center p-4 text-muted">
                                <i class="fas fa-envelope-open fa-2x mb-2"></i>
                                <p>No messages yet</p>
                            </div>
                            </noscript>
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

    // Stats summary toggle animation
    const statsToggle = document.querySelector('.stats-summary-toggle');
    if (statsToggle) {
        statsToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('collapsed');
        });
    }

    // --- LIVE ADMIN: AJAX Polling for Dashboard Stats, Latest Requests, and Messages ---
    function fetchDashboardCounts() {
        fetch('../AJAX/ajax_admin_dashboard_counts.php')
            .then(res => res.json())
            .then(data => {
                if (data) {
                    document.getElementById('pendingRentalsCount').textContent = data.pending_rentals ?? 0;
                    document.getElementById('pendingMaintenanceCount').textContent = data.pending_maintenance ?? 0;
                    document.getElementById('unpaidInvoicesCount').textContent = data.unpaid_invoices ?? 0;
                    document.getElementById('overdueInvoicesCount').textContent = data.overdue_invoices ?? 0;
                }
            })
            .catch(err => console.log('Error fetching dashboard counts:', err));
    }

    function fetchLatestRequests() {
        fetch('../AJAX/ajax_admin_dashboard_latest_requests.php')
            .then(res => res.text())
            .then(html => {
                document.getElementById('latestRequestsContainer').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('latestRequestsContainer').innerHTML = 
                    '<div class="text-center p-4 text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error loading requests</p></div>';
            });
    }

    let messageFilter = 'recent';
    function fetchMessages() {
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

    // Initial load and set up polling
    document.addEventListener('DOMContentLoaded', () => {
        fetchDashboardCounts();
        fetchLatestRequests();
        fetchMessages();
        
        // Set up filter buttons
        const recentBtn = document.getElementById('filterRecentBtn');
        const allBtn = document.getElementById('filterAllBtn');
        
        recentBtn.addEventListener('click', function() {
            messageFilter = 'recent';
            this.classList.add('active');
            allBtn.classList.remove('active');
            fetchMessages();
        });
        
        allBtn.addEventListener('click', function() {
            messageFilter = 'all';
            this.classList.add('active');
            recentBtn.classList.remove('active');
            fetchMessages();
        });
        
        // Set initial filter button state
        recentBtn.classList.add('active');
    });

    // Poll every 10 seconds
    setInterval(() => {
        fetchDashboardCounts();
        fetchLatestRequests();
        fetchMessages();
    }, 10000);

    // Chart initialization
    const chartData = <?= json_encode($chartData) ?>;
    const ctx = document.getElementById('activityChart').getContext('2d');

    let lastFocusedIndex = null;
    const activityChart = new Chart(ctx, {
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
                    label: 'Maintenance Requests',
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
                    labels: { 
                        color: '#374151',
                        usePointStyle: true,
                        padding: window.innerWidth <= 768 ? 8 : 15,
                        font: {
                            size: window.innerWidth <= 768 ? 9 : 11
                        }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1f2937',
                    bodyColor: '#374151',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    padding: 8,
                    cornerRadius: 6,
                    titleFont: {
                        size: 11
                    },
                    bodyFont: {
                        size: 10
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            scales: {
                x: { 
                    grid: { display: false },
                    ticks: { 
                        color: '#6b7280',
                        font: {
                            size: window.innerWidth <= 768 ? 9 : 10
                        }
                    }
                },
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' },
                    ticks: { 
                        color: '#6b7280',
                        font: {
                            size: window.innerWidth <= 768 ? 9 : 10
                        }
                    }
                }
            },
            elements: {
                point: {
                    radius: window.innerWidth <= 768 ? 2 : 3,
                    hoverRadius: window.innerWidth <= 768 ? 3 : 5
                }
            }
        }
    });

    // Handle window resize for chart responsiveness
    window.addEventListener('resize', () => {
        if (activityChart) {
            const isMobile = window.innerWidth <= 768;
            activityChart.options.plugins.legend.labels.padding = isMobile ? 8 : 15;
            activityChart.options.plugins.legend.labels.font.size = isMobile ? 9 : 11;
            activityChart.options.scales.x.ticks.font.size = isMobile ? 9 : 10;
            activityChart.options.scales.y.ticks.font.size = isMobile ? 9 : 10;
            activityChart.options.elements.point.radius = isMobile ? 2 : 3;
            activityChart.options.elements.point.hoverRadius = isMobile ? 3 : 5;
            activityChart.update();
        }
    });

    // Auto-hide notifications after 5 seconds
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 300);
            }
        }, 5000);
    });
    </script>
</body>
</html>