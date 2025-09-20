<?php
session_start();
require '../database/database.php';

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// --- Month/Year Picker Helper ---
function getMonthYearRange($month, $year) {
    if ($month && $year) {
        $start = "$year-" . str_pad($month, 2, "0", STR_PAD_LEFT) . "-01";
        $end = date("Y-m-t", strtotime($start));
    } elseif ($year) {
        $start = "$year-01-01";
        $end = "$year-12-31";
    } else {
        $start = date("Y-m-01");
        $end = date("Y-m-t");
    }
    return [$start, $end];
}

$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : null;
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : null;
list($startDate, $endDate) = getMonthYearRange($selectedMonth, $selectedYear);

// Chart Data for activities (no invoice)
$chartData = $db->getAdminMonthChartData($startDate, $endDate);

// Soft delete logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soft_delete_msg_id'])) {
    $msg_id = intval($_POST['soft_delete_msg_id']);
    $db->executeStatement("UPDATE free_message SET is_deleted = 1 WHERE Message_ID = ?", [$msg_id]);
    $filterParam = isset($_GET['filter']) ? 'filter=' . urlencode($_GET['filter']) : '';
    header("Location: dashboard.php" . ($filterParam ? "?$filterParam" : ""));
    exit();
}

// Dashboard counts, rental requests, etc.
$counts = $db->getAdminDashboardCounts();
$pending = $counts['pending_rentals'] ?? 0;
$pending_maintenance = $counts['pending_maintenance'] ?? 0;
$unpaid_invoices = $counts['unpaid_invoices'] ?? 0;
$unpaid_due_invoices = $counts['unpaid_due_invoices'] ?? 0;
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
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card.rentals { border-left-color: var(--primary); }
        .stat-card.maintenance { border-left-color: var(--warning); }
        .stat-card.invoices { border-left-color: var(--info); }
        .stat-card.overdue { border-left-color: var(--danger); }
        
        .stat-icon {
            width: 50px;
            height: 50px;
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
        }
        
        .stat-label {
            color: #6b7280;
            font-weight: 500;
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
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 1rem;
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
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-crown"></i>
                <span>Admin</span>
            </a>
        </div>
        
        <div class="sidebar-nav">
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
                    <?php if ($unpaid_due_invoices > 0): ?>
                        <span class="badge badge-notification bg-danger">Due: <?= $unpaid_due_invoices ?></span>
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
                <p>Here's what's happening with your properties today</p>
            </div>
            <div class="header-actions">
                <span class="text-muted"><?= date('l, F j, Y') ?></span>
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
            </div>
            <div class="stat-card maintenance animate-fade-in" style="animation-delay: 0.1s;">
                <div class="stat-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-value" id="pendingMaintenanceCount"><?= $pending_maintenance ?></div>
                <div class="stat-label">Maintenance Requests</div>
            </div>
            <div class="stat-card invoices animate-fade-in" style="animation-delay: 0.2s;">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-value" id="unpaidInvoicesCount"><?= $unpaid_invoices ?></div>
                <div class="stat-label">Unpaid Invoices</div>
            </div>
            <div class="stat-card overdue animate-fade-in" style="animation-delay: 0.3s;">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value" id="overdueInvoicesCount"><?= $unpaid_due_invoices ?></div>
                <div class="stat-label">Overdue Invoices</div>
            </div>
        </div>
        
        <!-- Activity Overview Card -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-chart-line"></i>
                <span>Activity Overview</span>
            </div>
            <div class="card-body">
                <form class="row g-3 align-items-center mb-4" method="get">
                    <div class="col-md-3">
                        <label for="month" class="form-label">Month</label>
                        <select id="month" name="month" class="form-select">
                            <option value="">All Months</option>
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
                            <option value="">All Years</option>
                            <?php for($y = date('Y'); $y >= 2023; $y--): ?>
                                <option value="<?= $y ?>" <?= ($selectedYear == $y ? 'selected' : '') ?>>
                                    <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">&nbsp;</label>
                        <button class="btn btn-primary" type="submit">Apply Filter</button>
                    </div>
                </form>
                
                <div class="row mb-4">
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
                    <div class="card-body p-0">
                        <?php if (!empty($latest_requests)): ?>
                            <div class="table-container">
                                <table class="custom-table">
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Client</th>
                                            <th>Unit</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($latest_requests as $r): ?>
                                            <tr>
                                                <td>#<?= htmlspecialchars($r['Request_ID']) ?></td>
                                                <td><?= htmlspecialchars($r['Client_fn'] . ' ' . $r['Client_ln']) ?></td>
                                                <td><?= htmlspecialchars($r['UnitName'] ?? $r['Name'] ?? 'N/A') ?></td>
                                                <td><?= date('M j, Y', strtotime($r['Requested_At'] ?? '')) ?></td>
                                                <td>
                                                    <span class="badge bg-warning-light text-warning">
                                                        <?= htmlspecialchars($r['Status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>No pending rental requests</p>
                            </div>
                        <?php endif; ?>
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
                            <form method="get" class="d-inline">
                                <input type="hidden" name="filter" value="recent">
                                <button type="submit" class="filter-btn <?= $filter==='recent'?'active':'bg-light' ?>">
                                    Recent
                                </button>
                            </form>
                            <form method="get" class="d-inline">
                                <input type="hidden" name="filter" value="all">
                                <button type="submit" class="filter-btn <?= $filter==='all'?'active':'bg-light' ?>">
                                    All Messages
                                </button>
                            </form>
                        </div>
                        
                        <div class="message-board">
                            <?php if (empty($free_messages)): ?>
                                <div class="text-center p-4 text-muted">
                                    <i class="fas fa-envelope-open fa-2x mb-2"></i>
                                    <p>No messages yet</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($free_messages as $msg): ?>
                                    <div class="message-item <?= ($msg['is_deleted'] ? 'deleted' : '') ?>">
                                        <div class="message-user">
                                            <?= htmlspecialchars($msg['Client_Name']) ?>
                                        </div>
                                        <div class="message-meta">
                                            <?= htmlspecialchars(date('M d, Y H:i', strtotime($msg['Sent_At']))) ?>
                                            • <?= timeAgo($msg['Sent_At']) ?>
                                            <?php if ($filter === 'all' && $msg['is_deleted']): ?>
                                                <span class="badge bg-danger ms-2">Deleted</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-content">
                                            <div class="mb-1">
                                                <strong>Email:</strong> <?= htmlspecialchars($msg['Client_Email']) ?>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Phone:</strong> <?= htmlspecialchars($msg['Client_Phone'] ?? 'N/A') ?>
                                            </div>
                                            <div>
                                                <?= nl2br(htmlspecialchars($msg['Message_Text'])) ?>
                                            </div>
                                        </div>
                                        <?php if (empty($msg['is_deleted']) || $msg['is_deleted'] == 0): ?>
                                            <form method="post" class="mt-2">
                                                <input type="hidden" name="soft_delete_msg_id" value="<?= $msg['Message_ID'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this message?')">
                                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // --- LIVE ADMIN: AJAX Polling for Dashboard Stats ---
    function fetchDashboardCounts() {
        fetch('../AJAX/ajax_admin_dashboard_counts.php')
            .then(res => res.json())
            .then(data => {
                if (data) {
                    document.getElementById('pendingRentalsCount').textContent = data.pending_rentals ?? 0;
                    document.getElementById('pendingMaintenanceCount').textContent = data.pending_maintenance ?? 0;
                    document.getElementById('unpaidInvoicesCount').textContent = data.unpaid_invoices ?? 0;
                    document.getElementById('overdueInvoicesCount').textContent = data.unpaid_due_invoices ?? 0;
                }
            });
    }
    setInterval(fetchDashboardCounts, 10000); // every 10s
    document.addEventListener('DOMContentLoaded', fetchDashboardCounts);
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
                            padding: 20
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
                        ticks: { color: '#6b7280' }
                    },
                    y: { 
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: { color: '#6b7280' }
                    }
                },
                elements: {
                    point: {
                        radius: 4,
                        hoverRadius: 6
                    }
                }
            }
        });

        // Click anywhere outside the chart to reset to showing all datasets
        document.addEventListener('click', function(e) {
            const chartBox = ctx.canvas.getBoundingClientRect();
            // Detect if click is within the chart area
            if (
                e.target === ctx.canvas ||
                (e.clientX >= chartBox.left && e.clientX <= chartBox.right && 
                 e.clientY >= chartBox.top && e.clientY <= chartBox.bottom)
            ) {
                // Click was inside chart area, do nothing
                return;
            }
            // Click was outside, reset if focused
            if (lastFocusedIndex !== null) {
                activityChart.data.datasets.forEach((ds, i) => {
                    activityChart.setDatasetVisibility(i, true);
                });
                lastFocusedIndex = null;
                activityChart.update();
            }
        });
    </script>
</body>
</html>