<?php
session_start();
require '../database/database.php';

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

$message = '';
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_request'])) {
    $request_id = intval($_POST['request_id']);
    $status = $_POST['status'];
    $handyman_id = $_POST['handyman_id'] !== "" ? intval($_POST['handyman_id']) : null;

    if ($db->updateMaintenanceRequest($request_id, $status, $handyman_id)) {
        $message = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        Request #' . htmlspecialchars($request_id) . ' updated successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
    } else {
        $message = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to update request #' . htmlspecialchars($request_id) . '.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
    }
}

$active_requests = $db->getActiveMaintenanceRequests();
$handyman_list = $db->getAllHandymenWithJobTypes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, minimum-scale=1.0, maximum-scale=5.0">
    <title>Maintenance Requests | ASRT Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            color: #374151;
            min-height: 100vh;
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
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .page-title h1 {
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 0;
        }

        .page-title p {
            font-size: 0.9rem;
        }
        
        .title-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            font-size: 1.25rem;
        }
        
        /* Dashboard Card */
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
        
        /* Table Styling */
        .table-container {
            overflow-x: auto;
            border-radius: var(--border-radius);
        }
        
        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 900px;
        }
        
        .custom-table th {
            background-color: #f9fafb;
            padding: 0.75rem 1rem;
            font-weight: 600;
            text-align: left;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
        }
        
        .custom-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .custom-table tr:last-child td {
            border-bottom: none;
        }
        
        .custom-table tr:hover {
            background-color: #f9fafb;
        }
        
        /* Form Elements */
        .form-select-sm {
            padding: 0.35rem 2.25rem 0.35rem 0.75rem;
            font-size: 0.875rem;
            border-radius: var(--border-radius);
            border: 1px solid #d1d5db;
            transition: var(--transition);
        }
        
        .form-select-sm:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        
        /* Button Styling */
        .btn-save {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .btn-save:hover {
            background: #0da271;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Status Badges */
        .badge-status {
            padding: 0.35rem 0.65rem;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
        .badge-submitted {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .badge-progress {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .badge-completed {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        /* Handyman Info */
        .handyman-info {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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

        .mobile-card.submitted {
            border-left-color: #3b82f6;
        }

        .mobile-card.progress {
            border-left-color: var(--warning);
        }

        .mobile-card.completed {
            border-left-color: var(--secondary);
        }

        .mobile-card-header {
            font-weight: 600;
            font-size: 1rem;
            color: var(--dark);
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .mobile-card-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            align-items: flex-start;
        }

        .mobile-card-detail .label {
            font-weight: 500;
            color: #6b7280;
            min-width: 80px;
        }

        .mobile-card-detail .value {
            color: var(--dark);
            text-align: right;
            flex: 1;
        }

        .mobile-form {
            background: #f9fafb;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-top: 1rem;
        }

        .mobile-form-group {
            margin-bottom: 1rem;
        }

        .mobile-form-group:last-child {
            margin-bottom: 0;
        }

        .mobile-form label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #374151;
            font-size: 0.9rem;
        }

        .mobile-form select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
        }

        .mobile-form select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        .mobile-save-btn {
            width: 100%;
            padding: 0.75rem;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .mobile-save-btn:hover {
            background: #0da271;
        }

        .current-handyman {
            background: rgba(16, 185, 129, 0.1);
            border-radius: 6px;
            padding: 0.5rem;
            font-size: 0.8rem;
            color: var(--secondary);
            margin-top: 0.5rem;
        }

        /* Hide desktop table on mobile */
        .table-mobile {
            display: none;
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

            .page-title h1 {
                font-size: 1.5rem;
            }

            .title-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .custom-table {
                display: none;
            }

            .table-mobile {
                display: block;
            }

            .card-body {
                padding: 1rem;
            }

            .card-header {
                padding: 1rem;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 0.75rem;
            }

            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .mobile-form select {
                font-size: 16px;
            }

            .mobile-card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .page-title h1 {
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
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn-save, .nav-link, .mobile-menu-btn, .mobile-save-btn {
                min-height: 44px;
                min-width: 44px;
            }

            .btn-save:hover {
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
                <a href="dashboard.php" class="nav-link">
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
                </a>
            </div>
            
            <div class="nav-item">
                <a href="manage_maintenance.php" class="nav-link active">
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
            <div class="page-title">
                <div class="title-icon">
                    <i class="fas fa-screwdriver-wrench"></i>
                </div>
                <div>
                    <h1>Maintenance Requests</h1>
                    <p class="text-muted mb-0">Manage and assign maintenance requests to handymen</p>
                </div>
            </div>
        </div>
        
        <?= $message ?>
        
        <!-- Maintenance Requests Table -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-list-alt"></i>
                <span>Active Maintenance Requests</span>
                <span class="badge bg-primary ms-2"><?= count($active_requests) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($active_requests)): ?>
                    <!-- Desktop Table -->
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Unit</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Assign Handyman</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($active_requests as $row): ?>
                                <tr>
                                    <form method="post">
                                        <input type="hidden" name="request_id" value="<?= (int)$row['Request_ID'] ?>">
                                        <td>
                                            <span class="fw-medium">#<?= $row['Request_ID'] ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($row['Client_fn'] . " " . $row['Client_ln']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($row['SpaceName']) ?></td>
                                        <td>
                                            <div class="text-muted"><?= htmlspecialchars($row['RequestDate']) ?></div>
                                        </td>
                                        <td>
                                            <select name="status" class="form-select form-select-sm">
                                                <option value="Submitted" <?= $row['Status'] === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                                                <option value="In Progress" <?= $row['Status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                                <option value="Completed" <?= $row['Status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="handyman_id" class="form-select form-select-sm mb-2">
                                                <option value="">-- Select Handyman --</option>
                                                <?php foreach ($handyman_list as $h): ?>
                                                    <option value="<?= (int)$h['Handyman_ID'] ?>" <?= $row['Handyman_ID'] == $h['Handyman_ID'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($h['Handyman_fn'] . ' ' . $h['Handyman_ln']) ?>
                                                        <?php if($h['JobTypes']): ?> (<?= htmlspecialchars($h['JobTypes']) ?>)<?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if ($row['Handyman_fn']): ?>
                                                <div class="handyman-info">
                                                    Currently assigned: <?= htmlspecialchars($row['Handyman_fn'] . ' ' . $row['Handyman_ln']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="submit" name="update_request" class="btn-save">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                        </td>
                                    </form>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card Layout -->
                    <div class="table-mobile">
                        <?php foreach($active_requests as $row): 
                            $statusClass = strtolower(str_replace(' ', '', $row['Status']));
                            if ($statusClass === 'inprogress') $statusClass = 'progress';
                            $cardClass = 'mobile-card ' . $statusClass;
                        ?>
                        <div class="<?= $cardClass ?>">
                            <form method="post">
                                <input type="hidden" name="request_id" value="<?= (int)$row['Request_ID'] ?>">
                                
                                <div class="mobile-card-header">
                                    <div>
                                        <strong><?= htmlspecialchars($row['Client_fn'] . " " . $row['Client_ln']) ?></strong>
                                        <span class="badge bg-primary ms-2">#<?= $row['Request_ID'] ?></span>
                                    </div>
                                    <div>
                                        <?php
                                        $statusBadgeClass = 'badge-submitted';
                                        if ($row['Status'] === 'In Progress') $statusBadgeClass = 'badge-progress';
                                        elseif ($row['Status'] === 'Completed') $statusBadgeClass = 'badge-completed';
                                        ?>
                                        <span class="badge-status <?= $statusBadgeClass ?>"><?= htmlspecialchars($row['Status']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Unit:</span>
                                    <span class="value"><?= htmlspecialchars($row['SpaceName']) ?></span>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Date:</span>
                                    <span class="value"><?= htmlspecialchars($row['RequestDate']) ?></span>
                                </div>

                                <?php if ($row['Handyman_fn']): ?>
                                <div class="current-handyman">
                                    <i class="fas fa-user-tie me-1"></i>
                                    Currently assigned: <?= htmlspecialchars($row['Handyman_fn'] . ' ' . $row['Handyman_ln']) ?>
                                </div>
                                <?php endif; ?>

                                <div class="mobile-form">
                                    <div class="mobile-form-group">
                                        <label for="status_<?= $row['Request_ID'] ?>">Status</label>
                                        <select name="status" id="status_<?= $row['Request_ID'] ?>">
                                            <option value="Submitted" <?= $row['Status'] === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                                            <option value="In Progress" <?= $row['Status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="Completed" <?= $row['Status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mobile-form-group">
                                        <label for="handyman_<?= $row['Request_ID'] ?>">Assign Handyman</label>
                                        <select name="handyman_id" id="handyman_<?= $row['Request_ID'] ?>">
                                            <option value="">-- Select Handyman --</option>
                                            <?php foreach ($handyman_list as $h): ?>
                                                <option value="<?= (int)$h['Handyman_ID'] ?>" <?= $row['Handyman_ID'] == $h['Handyman_ID'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($h['Handyman_fn'] . ' ' . $h['Handyman_ln']) ?>
                                                    <?php if($h['JobTypes']): ?> (<?= htmlspecialchars($h['JobTypes']) ?>)<?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" name="update_request" class="mobile-save-btn">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tools"></i>
                        <h4>No active maintenance requests</h4>
                        <p>All maintenance requests have been processed</p>
                    </div>
                <?php endif; ?>
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

        // Auto-hide alerts after 5 seconds
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

        // Prevent double form submission
        document.querySelectorAll('form').forEach(form => {
            let isSubmitting = false;
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
                
                // Show loading state on button
                const submitBtn = form.querySelector('[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    submitBtn.disabled = true;
                    
                    // Re-enable after 5 seconds in case of error
                    setTimeout(() => {
                        isSubmitting = false;
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 5000);
                }
            });
        });

        // Add confirmation for status changes
        document.addEventListener('change', function(e) {
            if (e.target.name === 'status' && e.target.value === 'Completed') {
                if (!confirm('Mark this maintenance request as completed? This action will finalize the request.')) {
                    // Reset to previous value if cancelled
                    e.target.selectedIndex = 0;
                }
            }
        });
    </script>
</body>
</html>