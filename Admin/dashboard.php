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

// Get statistics - UPDATED VERSION
// Real-time counts for dashboard widgets (no date filter)
$counts = $db->getAdminDashboardCounts();

// Monthly stats for the selected period
$monthlyStats = $db->getMonthlyEarningsStats($startDate, $endDate);
$chartData = $db->getAdminMonthChartData($startDate, $endDate);

// Monthly breakdowns for the summary section
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

// For AJAX real-time updates
$new_maintenance_requests = $counts['new_maintenance_requests'] ?? 0;
$unseen_rentals = $counts['unseen_rentals'] ?? 0;

// Soft delete logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soft_delete_msg_id'])) {
    $msg_id = intval($_POST['soft_delete_msg_id']);
    $db->executeStatement("UPDATE free_message SET is_deleted = 1 WHERE Message_ID = ?", [$msg_id]);
    
    // If it's an AJAX request, return JSON response
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message_id' => $msg_id]);
        exit();
    } else {
        // Traditional form submission
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
            --sidebar-width: 280px;
            --header-height: 70px;
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
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            color: #374151;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
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
            font-size: 1.5rem;
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
            font-size: 1.1rem;
            color: var(--dark);
        }
        
        /* Sidebar Styling */
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
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: var(--transition);
        }
        
        /* Header */
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
        
        /* Month Picker Card */
        .month-picker-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        
        .period-badge {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
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
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.25rem;
            transition: var(--transition);
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1);
        }
        
        .stat-card.rentals .stat-icon { background: rgba(99, 102, 241, 0.1); color: var(--primary); }
        .stat-card.maintenance .stat-icon { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stat-card.invoices .stat-icon { background: rgba(6, 182, 212, 0.1); color: var(--info); }
        .stat-card.overdue .stat-icon { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .stat-card.earnings .stat-icon { background: rgba(16, 185, 129, 0.1); color: var(--secondary); }
        
        .stat-value {
            font-size: 2rem;
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
        }
        
        .stat-subtext {
            font-size: 0.8rem;
            color: #9ca3af;
            margin-top: 0.25rem;
        }
        
        /* Monthly Summary Section */
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
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .monthly-stat {
            text-align: center;
            position: relative;
            cursor: help;
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
            cursor: help;
            position: relative;
        }

        /* Tooltip Styles */
        .tooltip-wrapper {
            position: relative;
            display: inline-block;
        }

        .tooltip-hover {
            position: relative;
        }

        .tooltip-hover:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .tooltip-hover:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            margin-bottom: -5px;
        }
        
        /* Dashboard Cards */
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
            position: relative;
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
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
        
        /* Filter Buttons */
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
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

        /* Mobile Card Layout */
        .mobile-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            padding: 1rem;
            border-left: 4px solid var(--primary);
        }

        .mobile-card-header {
            font-weight: 600;
            font-size: 1rem;
            color: var(--dark);
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mobile-card-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
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
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .summary-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-top: 3px solid var(--primary);
        }

        .summary-card.warning { border-top-color: var(--warning); }
        .summary-card.info { border-top-color: var(--info); }
        
        /* Notification Styles */
        .notification-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .new-request-indicator {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            animation: bounce 0.5s ease-in-out;
        }
        
        @keyframes bounce {
            0%, 20%, 60%, 100% { transform: translateY(0); }
            40% { transform: translateY(-5px); }
            80% { transform: translateY(-2px); }
        }

        .bell-shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-15deg); }
            75% { transform: rotate(15deg); }
        }

        .tools-shake {
            animation: toolsShake 0.5s ease-in-out;
        }

        @keyframes toolsShake {
            0%, 100% { transform: rotate(0deg) scale(1); }
            25% { transform: rotate(-10deg) scale(1.1); }
            50% { transform: rotate(10deg) scale(1.1); }
            75% { transform: rotate(-5deg) scale(1.05); }
        }

        .new-request-flash {
            animation: highlight 3s ease-in-out;
        }

        @keyframes highlight {
            0% { background-color: rgba(34, 197, 94, 0.1); }
            50% { background-color: rgba(34, 197, 94, 0.3); }
            100% { background-color: transparent; }
        }

        .maintenance-highlight {
            animation: maintenancePulse 3s ease-in-out;
        }

        @keyframes maintenancePulse {
            0% { background-color: rgba(245, 158, 11, 0.1); }
            50% { background-color: rgba(245, 158, 11, 0.3); }
            100% { background-color: transparent; }
        }
        
        /* Mobile Responsive */
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
                margin-bottom: 1.5rem;
            }

            .welcome-text h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
                margin-bottom: 0.75rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .stat-label {
                font-size: 0.8rem;
            }

            .monthly-summary {
                padding: 1.5rem;
            }

            .monthly-amount {
                font-size: 2rem;
            }

            .monthly-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .card-body {
                padding: 1rem;
            }

            .card-header {
                padding: 1rem;
                font-size: 1rem;
            }

            .chart-container {
                height: 250px;
            }

            .summary-cards {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
            }

            .summary-card {
                padding: 0.75rem;
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
                height: 200px;
            }

            .message-board {
                max-height: 300px;
            }

            .message-item {
                padding: 0.75rem;
            }

            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
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
                font-size: 1.3rem;
            }

            .dashboard-card {
                border-radius: 8px;
            }

            .btn {
                font-size: 0.9rem;
                padding: 0.75rem 1.5rem;
            }

            .form-control, .form-select {
                padding: 0.75rem;
            }

            .monthly-summary {
                padding: 1rem;
            }

            .monthly-amount {
                font-size: 1.75rem;
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

            .tooltip-hover::after {
                display: none;
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
                        <span class="badge badge-notification bg-danger notification-badge" id="sidebarRentalBadge"><?= $pending ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="manage_maintenance.php" class="nav-link">
                    <i class="fas fa-tools"></i>
                    <span>Maintenance</span>
                    <?php if ($pending_maintenance > 0): ?>
                        <span class="badge badge-notification bg-warning" id="sidebarMaintenanceBadge"><?= $pending_maintenance ?></span>
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
        
        <!-- Month/Year Picker Card -->
        <div class="month-picker-card animate-fade-in">
            <div class="row align-items-center">
                <div class="col-md-8">
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
                <div class="col-md-4 text-md-end">
                    <div class="text-muted small">Period: <?= date('M d', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?></div>
                    <div class="text-success fw-bold mt-1">₱<?= number_format($total_earnings, 2) ?> Revenue</div>
                </div>
            </div>
        </div>
        
        <!-- Monthly Earnings Summary -->
        <div class="monthly-summary animate-fade-in">
            <div class="monthly-summary-content">
                <div class="monthly-title">Monthly Revenue - <?= $monthName ?></div>
                <div class="monthly-amount">₱<?= number_format($total_earnings, 2) ?></div>
                <div class="monthly-stats">
                    <div class="monthly-stat">
                        <div class="monthly-stat-value"><?= $rentalRequestsData['total'] ?? 0 ?></div>
                        <div class="monthly-stat-label">Rental Requests</div>
                        <div class="monthly-stat-subtext tooltip-hover" 
                             data-tooltip="P: (Pending) | A: Accepted (Approved requests) | R: Rejected (Declined requests)">
                            P:<?= $rentalRequestsData['pending'] ?? 0 ?> 
                            A:<?= $rentalRequestsData['accepted'] ?? 0 ?> 
                            R:<?= $rentalRequestsData['rejected'] ?? 0 ?>
                        </div>
                    </div>
                    <div class="monthly-stat">
                        <div class="monthly-stat-value"><?= $maintenanceRequestsData['total'] ?? 0 ?></div>
                        <div class="monthly-stat-label">Maintenance</div>
                        <div class="monthly-stat-subtext tooltip-hover" 
                             data-tooltip="S: Submitted (New requests) | IP: In Progress (Being worked on) | C: Completed (Finished requests)">
                            S:<?= $maintenanceRequestsData['submitted'] ?? 0 ?> 
                            IP:<?= $maintenanceRequestsData['in_progress'] ?? 0 ?> 
                            C:<?= $maintenanceRequestsData['completed'] ?? 0 ?>
                        </div>
                    </div>
                    <div class="monthly-stat">
                        <div class="monthly-stat-value"><?= $new_messages_count ?></div>
                        <div class="monthly-stat-label">Messages</div>
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
                        <h6 class="text-primary mb-3">Summary for <?= date('M d, Y', strtotime($startDate)) ?> to <?= date('M d, Y', strtotime($endDate)) ?></h6>
                        <div class="d-flex flex-wrap gap-4">
                            <div class="bg-primary-light p-3 rounded">
                                <div class="fw-bold fs-5"><?= array_sum($chartData['new_rentals']) ?></div>
                                <div class="text-muted small">New Rentals</div>
                            </div>
                            <div class="bg-warning-light p-3 rounded">
                                <div class="fw-bold fs-5"><?= array_sum($chartData['new_maintenance']) ?></div>
                                <div class="text-muted small">Maintenance Requests</div>
                            </div>
                            <div class="bg-info-light p-3 rounded">
                                <div class="fw-bold fs-5"><?= array_sum($chartData['new_messages']) ?></div>
                                <div class="text-muted small">Messages</div>
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
        
        <!-- Requests Section -->
        <div class="row">
            <!-- Rental Requests Card -->
            <div class="col-lg-6">
                <div class="dashboard-card h-100 animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-list-alt"></i>
                        <span>Latest Rental Requests</span>
                        <span class="badge bg-primary ms-2" id="latestRequestsBadge"><?= $pending ?></span>
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
            
            <!-- Maintenance Requests Card -->
            <div class="col-lg-6">
                <div class="dashboard-card h-100 animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-tools"></i>
                        <span>Latest Maintenance Requests</span>
                        <span class="badge bg-warning ms-2" id="latestMaintenanceBadge"><?= $pending_maintenance ?></span>
                    </div>
                    <div class="card-body p-0" id="latestMaintenanceContainer">
                        <!-- Maintenance requests will be loaded here via AJAX -->
                        <div class="text-center p-4 text-muted">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading maintenance requests...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Messages Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="dashboard-card animate-fade-in">
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

    // --- LIVE ADMIN: Real-time Notification System ---
    let lastPendingCount = <?= $pending ?>;
    let lastMaintenanceCount = <?= $pending_maintenance ?>;
    let lastUnseenRentals = <?= $unseen_rentals ?>;
    let lastNewMaintenanceCount = <?= $new_maintenance_requests ?>;
    let isFirstLoad = true;
    let isTabActive = true;
    let notificationCooldown = false;

    // Stop polling when tab is not visible
    document.addEventListener('visibilitychange', function() {
        isTabActive = !document.hidden;
        if (isTabActive) {
            // Refresh immediately when tab becomes active
            fetchDashboardCounts();
            fetchLatestRequests();
            fetchLatestMaintenance();
        }
    });

    function showNewRequestNotification(newRequestsCount) {
        if (notificationCooldown) return;
        
        notificationCooldown = true;
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'alert alert-success alert-dismissible fade show';
        notification.style.cssText = `
            position: fixed; 
            top: 20px; 
            right: 20px; 
            z-index: 9999; 
            min-width: 320px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-left: 4px solid #10b981;
        `;
        notification.innerHTML = `
            <div class="d-flex align-items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-bell text-success fs-4 me-3 bell-shake"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="alert-heading mb-1">🏠 New Rental Request!</h6>
                    <p class="mb-2">You have <strong>${newRequestsCount}</strong> new pending request${newRequestsCount > 1 ? 's' : ''} to review.</p>
                    <div class="d-flex gap-2 mt-2">
                        <a href="view_rental_requests.php" class="btn btn-sm btn-success">
                            <i class="fas fa-eye me-1"></i>View Requests
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="alert">
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 8 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 8000);
        
        // Reset cooldown after 10 seconds
        setTimeout(() => {
            notificationCooldown = false;
        }, 10000);
    }

    function showNewMaintenanceNotification(newRequestsCount) {
        if (notificationCooldown) return;
        
        notificationCooldown = true;
        
        const notification = document.createElement('div');
        notification.className = 'alert alert-warning alert-dismissible fade show';
        notification.style.cssText = `
            position: fixed; 
            top: 80px; 
            right: 20px; 
            z-index: 9999; 
            min-width: 320px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-left: 4px solid #f59e0b;
        `;
        notification.innerHTML = `
            <div class="d-flex align-items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-tools text-warning fs-4 me-3 tools-shake"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="alert-heading mb-1">🔧 New Maintenance Request!</h6>
                    <p class="mb-2">You have <strong>${newRequestsCount}</strong> new maintenance request${newRequestsCount > 1 ? 's' : ''} to review.</p>
                    <div class="d-flex gap-2 mt-2">
                        <a href="manage_maintenance.php" class="btn btn-sm btn-warning text-white">
                            <i class="fas fa-tools me-1"></i>View Maintenance
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="alert">
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 8 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 8000);
        
        // Reset cooldown after 10 seconds
        setTimeout(() => {
            notificationCooldown = false;
        }, 10000);
    }

    function updateBadgeAnimation(badgeElement, newCount, oldCount) {
        if (newCount > oldCount && !isFirstLoad) {
            badgeElement.classList.add('notification-badge');
            setTimeout(() => {
                badgeElement.classList.remove('notification-badge');
            }, 3000);
        }
    }

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

                    // Update counts on dashboard
                    document.getElementById('pendingRentalsCount').textContent = currentPending;
                    document.getElementById('pendingMaintenanceCount').textContent = currentMaintenance;
                    document.getElementById('unpaidInvoicesCount').textContent = currentUnpaid;
                    document.getElementById('overdueInvoicesCount').textContent = currentOverdue;

                    // Check for new rental requests (using unseen count for notifications)
                    if (!isFirstLoad && currentUnseenRentals > lastUnseenRentals) {
                        const newRequests = currentUnseenRentals - lastUnseenRentals;
                        showNewRequestNotification(newRequests);
                        updateSidebarBadge(currentPending);
                    }
                    
                    // Check for new maintenance requests
                    if (!isFirstLoad && currentNewMaintenance > lastNewMaintenanceCount) {
                        const newMaintenance = currentNewMaintenance - lastNewMaintenanceCount;
                        showNewMaintenanceNotification(newMaintenance);
                        updateMaintenanceSidebarBadge(currentMaintenance);
                    }
                    
                    lastPendingCount = currentPending;
                    lastMaintenanceCount = currentMaintenance;
                    lastUnseenRentals = currentUnseenRentals;
                    lastNewMaintenanceCount = currentNewMaintenance;
                    isFirstLoad = false;
                }
            })
            .catch(err => console.log('Error fetching dashboard counts:', err));
    }

    function updateSidebarBadge(currentCount) {
        const sidebarBadge = document.getElementById('sidebarRentalBadge');
        if (sidebarBadge) {
            const oldCount = parseInt(sidebarBadge.textContent);
            sidebarBadge.textContent = currentCount;
            updateBadgeAnimation(sidebarBadge, currentCount, oldCount);
        } else {
            // Create badge if it doesn't exist
            const rentalLink = document.querySelector('a[href="view_rental_requests.php"]');
            if (rentalLink) {
                const newBadge = document.createElement('span');
                newBadge.id = 'sidebarRentalBadge';
                newBadge.className = 'badge badge-notification bg-danger notification-badge';
                newBadge.textContent = currentCount;
                rentalLink.appendChild(newBadge);
            }
        }
    }

    function updateMaintenanceSidebarBadge(currentCount) {
        const sidebarBadge = document.getElementById('sidebarMaintenanceBadge');
        if (sidebarBadge) {
            const oldCount = parseInt(sidebarBadge.textContent);
            sidebarBadge.textContent = currentCount;
            updateBadgeAnimation(sidebarBadge, currentCount, oldCount);
        } else {
            // Create badge if it doesn't exist
            const maintenanceLink = document.querySelector('a[href="manage_maintenance.php"]');
            if (maintenanceLink) {
                const newBadge = document.createElement('span');
                newBadge.id = 'sidebarMaintenanceBadge';
                newBadge.className = 'badge badge-notification bg-warning notification-badge';
                newBadge.textContent = currentCount;
                maintenanceLink.appendChild(newBadge);
            }
        }
    }

    function fetchLatestRequests() {
        if (!isTabActive) return;
        
        fetch('../AJAX/ajax_admin_dashboard_latest_requests.php')
            .then(res => res.text())
            .then(html => {
                const container = document.getElementById('latestRequestsContainer');
                container.innerHTML = html;
                
                // Update the badge count
                const countElement = container.querySelector('[data-count]');
                if (countElement) {
                    const currentCount = parseInt(countElement.getAttribute('data-count'));
                    const badge = document.getElementById('latestRequestsBadge');
                    if (badge) {
                        const oldCount = parseInt(badge.textContent);
                        badge.textContent = currentCount;
                        updateBadgeAnimation(badge, currentCount, oldCount);
                    }
                }
            })
            .catch(err => {
                console.error('Error fetching latest requests:', err);
                document.getElementById('latestRequestsContainer').innerHTML = 
                    '<div class="text-center p-4 text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error loading requests</p></div>';
            });
    }

    function fetchLatestMaintenance() {
        if (!isTabActive) return;
        
        fetch('../AJAX/ajax_admin_dashboard_latest_maintenance.php')
            .then(res => res.text())
            .then(html => {
                const container = document.getElementById('latestMaintenanceContainer');
                container.innerHTML = html;
                
                // Update the badge count
                const countElement = container.querySelector('[data-count]');
                if (countElement) {
                    const currentCount = parseInt(countElement.getAttribute('data-count'));
                    const badge = document.getElementById('latestMaintenanceBadge');
                    if (badge) {
                        const oldCount = parseInt(badge.textContent);
                        badge.textContent = currentCount;
                        updateBadgeAnimation(badge, currentCount, oldCount);
                    }
                }
            })
            .catch(err => {
                console.error('Error fetching maintenance requests:', err);
                document.getElementById('latestMaintenanceContainer').innerHTML = 
                    '<div class="text-center p-4 text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error loading maintenance requests</p></div>';
            });
    }

    let messageFilter = 'recent';
    function fetchMessages() {
        if (!isTabActive) return;
        
        fetch('../AJAX/ajax_admin_dashboard_messages.php?filter=' + messageFilter)
            .then(res => res.text())
            .then(html => {
                document.getElementById('messageBoardContainer').innerHTML = html;
                
                // Add event listeners to delete buttons after loading messages
                document.querySelectorAll('.delete-message-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const messageId = this.getAttribute('data-message-id');
                        handleMessageDelete(messageId);
                    });
                });
            })
            .catch(err => {
                console.error('Error fetching messages:', err);
                document.getElementById('messageBoardContainer').innerHTML = 
                    '<div class="text-center p-4 text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error loading messages</p></div>';
            });
    }

    // Handle message deletion
    function handleMessageDelete(messageId) {
        if (!confirm('Are you sure you want to delete this message?')) {
            return;
        }

        const formData = new FormData();
        formData.append('soft_delete_msg_id', messageId);

        fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Remove the message from the UI
                const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
                if (messageElement) {
                    messageElement.style.opacity = '0';
                    setTimeout(() => {
                        messageElement.remove();
                        // Refresh the messages to update the count
                        fetchMessages();
                    }, 300);
                }
                // Show success message
                showNotification('Message deleted successfully', 'success');
            }
        })
        .catch(err => {
            console.error('Error deleting message:', err);
            showNotification('Error deleting message', 'error');
        });
    }

    // Notification function
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                <span>${message}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 5000);
    }

    // Initial load and set up polling
    document.addEventListener('DOMContentLoaded', () => {
        fetchDashboardCounts();
        fetchLatestRequests();
        fetchLatestMaintenance();
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

    // Poll every 10 seconds for real-time updates
    setInterval(() => {
        if (isTabActive) {
            fetchDashboardCounts();
            fetchLatestRequests();
            fetchLatestMaintenance();
            fetchMessages();
        }
    }, 10000); // 10 seconds

    // Chart initialization
    const chartData = <?= json_encode($chartData) ?>;
    const ctx = document.getElementById('activityChart').getContext('2d');

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
                        padding: window.innerWidth <= 768 ? 10 : 20,
                        font: {
                            size: window.innerWidth <= 768 ? 10 : 12
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
                    padding: 10,
                    cornerRadius: 8
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
                            size: window.innerWidth <= 768 ? 10 : 11
                        }
                    }
                },
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' },
                    ticks: { 
                        color: '#6b7280',
                        font: {
                            size: window.innerWidth <= 768 ? 10 : 11
                        }
                    }
                }
            },
            elements: {
                point: {
                    radius: window.innerWidth <= 768 ? 2 : 4,
                    hoverRadius: window.innerWidth <= 768 ? 4 : 6
                }
            }
        }
    });

    // Handle window resize for chart responsiveness
    window.addEventListener('resize', () => {
        if (activityChart) {
            const isMobile = window.innerWidth <= 768;
            activityChart.options.plugins.legend.labels.padding = isMobile ? 10 : 20;
            activityChart.options.plugins.legend.labels.font.size = isMobile ? 10 : 12;
            activityChart.options.scales.x.ticks.font.size = isMobile ? 10 : 11;
            activityChart.options.scales.y.ticks.font.size = isMobile ? 10 : 11;
            activityChart.options.elements.point.radius = isMobile ? 2 : 4;
            activityChart.options.elements.point.hoverRadius = isMobile ? 4 : 6;
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

    // Tooltip functionality for status breakdowns
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Dashboard loaded with live rental & maintenance notifications');
    });
    </script>
</body>
</html>