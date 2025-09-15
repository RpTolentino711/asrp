<?php
require '../database/database.php';
session_start();

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

$msg = "";

// --- Fetch ALL Data for Display ---
$clients = $db->getAllClientsWithAssignedUnit();
$units = $db->getAllUnitsWithRenterInfo();

// --- Handle POST Actions ---

// Rename Space Unit
if (isset($_POST['rename_unit']) && isset($_POST['space_id'], $_POST['new_name'])) {
    $sid = intval($_POST['space_id']);
    $new_name = trim($_POST['new_name']);
    if ($new_name === '') {
        $msg = '<div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Unit name cannot be empty.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else if ($db->renameUnit($sid, $new_name)) {
        $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Unit name updated!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error: Could not update unit name.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

// Nuke (PERMA DELETE) - This will delete the client, their records, and set any rented unit as available again
if (isset($_POST['nuke_client']) && isset($_POST['client_id'])) {
    $cid = intval($_POST['client_id']);
    // Find ALL units assigned to this client (supporting multi-unit clients)
    $client_unit_ids = [];
    foreach ($clients as $cl) {
        if ($cl['Client_ID'] == $cid && !empty($cl['Space_ID'])) {
            $client_unit_ids[] = $cl['Space_ID'];
        }
    }
    // Free all spaces BEFORE deleting the client
    foreach ($client_unit_ids as $space_id) {
        $db->setUnitAvailable($space_id);
    }
    // Now nuke the client (permanent delete)
    if ($db->hardDeleteClient($cid)) {
        $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Client and all associated records PERMANENTLY deleted. Any rented space is now available.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error: Could not nuke client (delete client and free space).
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

if (isset($_POST['delete_client']) && isset($_POST['client_id'])) {
    $cid = intval($_POST['client_id']);
    $client_has_unit = false;
    foreach ($clients as $cl) {
        if ($cl['Client_ID'] == $cid && !empty($cl['SpaceName'])) {
            $client_has_unit = true;
            break;
        }
    }
    if ($client_has_unit) {
        $msg = '<div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Cannot set inactive: Client still has a rented unit assigned.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        if ($db->updateClientStatus($cid, 'inactive')) {
            $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Client set as inactive!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        } else {
            $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error: Could not set client as inactive.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
}

if (isset($_POST['activate_client']) && isset($_POST['client_id'])) {
    $cid = intval($_POST['client_id']);
    if ($db->updateClientStatus($cid, 'active')) {
        $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Client reactivated!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error: Could not reactivate client.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

if (isset($_POST['update_price']) && isset($_POST['space_id'], $_POST['new_price'])) {
    $sid = intval($_POST['space_id']);
    $price = floatval($_POST['new_price']);
    if ($db->updateUnit_price($sid, $price)) {
        $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Unit price updated!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error: Could not update unit price.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

// FIXED UNIT DELETION - Added debug information and better error handling
if (isset($_POST['delete_unit']) && isset($_POST['space_id'])) {
    $sid = intval($_POST['space_id']);
    
    // Debug: Log what we're trying to delete
    error_log("Attempting to delete unit ID: $sid");
    
    // Check if unit is rented using a more comprehensive check
    $isRented = $db->isUnitRented($sid);
    error_log("Unit $sid - isRented check result: " . ($isRented ? 'true' : 'false'));
    
    // Additional debug: Check active rentals directly
    $activeRenters = $db->runQuery(
        "SELECT Client_ID FROM clientspace WHERE Space_ID = ? AND active = 1",
        [$sid],
        true
    );
    error_log("Unit $sid - Active renters found: " . count($activeRenters));
    
    if ($isRented) {
        $msg = '<div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Cannot delete: This unit currently has a renter assigned. (Active renters: ' . count($activeRenters) . ')
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        // Attempt deletion
        $deleteResult = $db->hardDeleteUnit($sid);
        error_log("Unit $sid - hardDeleteUnit result: " . ($deleteResult ? 'success' : 'failed'));
        
        if ($deleteResult) {
            $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Unit deleted successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        } else {
            $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error: Could not delete unit. Check error logs for details.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
    
    // Refresh data after deletion attempt
    $clients = $db->getAllClientsWithAssignedUnit();
    $units = $db->getAllUnitsWithRenterInfo();
}

// ALTERNATIVE: Force delete unit (ignores rental status)
if (isset($_POST['force_delete_unit']) && isset($_POST['space_id'])) {
    $sid = intval($_POST['space_id']);
    
    error_log("Force deleting unit ID: $sid");
    
    if ($db->hardDeleteUnit($sid)) {
        $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Unit force deleted successfully! All associated records have been removed.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
        
        // Refresh data after deletion
        $clients = $db->getAllClientsWithAssignedUnit();
        $units = $db->getAllUnitsWithRenterInfo();
    } else {
        $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error: Could not force delete unit. Check error logs for details.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

if (isset($_POST['hard_delete_client']) && isset($_POST['client_id'])) {
    $cid = intval($_POST['client_id']);
    $client_has_unit = false;
    foreach ($clients as $cl) {
        if ($cl['Client_ID'] == $cid && !empty($cl['SpaceName'])) {
            $client_has_unit = true;
            break;
        }
    }
    if ($client_has_unit) {
        $msg = '<div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Cannot hard delete: Client has a rented unit assigned.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        if ($db->hardDeleteClient($cid)) {
            $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Client and all associated records have been permanently deleted.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        } else {
            $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error: Could not delete client and their records.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User & Unit Management | ASRT Management</title>
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
            vertical-align: middle;
        }
        
        .custom-table tr:last-child td {
            border-bottom: none;
        }
        
        .custom-table tr:hover {
            background-color: #f9fafb;
        }
        
        /* Form Elements */
        .form-control-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.875rem;
            border-radius: var(--border-radius);
            border: 1px solid #d1d5db;
            transition: var(--transition);
        }
        
        .form-control-sm:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        
        /* Button Styling */
        .btn-action {
            padding: 0.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
        }
        
        .btn-deactivate {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .btn-deactivate:hover {
            background: #f59e0b;
            color: white;
        }
        
        .btn-activate {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .btn-activate:hover {
            background: #10b981;
            color: white;
        }
        
        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .btn-delete:hover {
            background: #ef4444;
            color: white;
        }
        
        .btn-nuke {
            background: rgba(55, 65, 81, 0.1);
            color: #374151;
            border: 1px solid rgba(55, 65, 81, 0.2);
        }
        
        .btn-nuke:hover {
            background: #374151;
            color: white;
        }
        
        .btn-update {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .btn-update:hover {
            background: #6366f1;
            color: white;
        }
        
        .btn-force-delete {
            background: rgba(220, 38, 127, 0.1);
            color: #dc2626;
            border: 1px solid rgba(220, 38, 127, 0.2);
        }
        
        .btn-force-delete:hover {
            background: #dc2626;
            color: white;
        }
        
        /* Status Badges */
        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
        /* Action Group */
        .action-group {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Price Update Form */
        .price-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .price-input {
            width: 100px;
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
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .custom-table {
                font-size: 0.875rem;
            }
            
            .action-group {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .btn-action {
                width: 32px;
                height: 32px;
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
        
        /* Tooltip */
        .tooltip-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .tooltip-wrapper .tooltip-text {
            visibility: hidden;
            width: 120px;
            background-color: #374151;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.75rem;
        }
        
        .tooltip-wrapper:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
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
                <a href="manage_user.php" class="nav-link active">
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
                    <i class="fas fa-users-cog"></i>
                </div>
                <div>
                    <h1>User & Unit Management</h1>
                    <p class="text-muted mb-0">Manage clients, units, and their relationships</p>
                </div>
            </div>
        </div>
        
        <?= $msg ?>
        
        <!-- Clients Card -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-users"></i>
                <span>Clients</span>
                <span class="badge bg-primary ms-2"><?= count($clients) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($clients)): ?>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Username</th>
                                    <th>Status</th>
                                    <th>Rented Unit</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $c): ?>
                                <?php $client_has_unit = !empty($c['SpaceName']); ?>
                                <tr>
                                    <td>
                                        <span class="fw-medium">#<?= htmlspecialchars($c['Client_ID']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?= htmlspecialchars($c['Client_fn'].' '.$c['Client_ln']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($c['Client_Email']) ?></td>
                                    <td><?= htmlspecialchars($c['C_username']) ?></td>
                                    <td>
                                        <?php if (strtolower($c['Status']) === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $client_has_unit ? htmlspecialchars($c['SpaceName']) : '<span class="text-muted">None</span>' ?>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="client_id" value="<?= $c['Client_ID'] ?>">
                                                <?php if (strtolower($c['Status']) === 'active'): ?>
                                                    <div class="tooltip-wrapper">
                                                        <button type="submit" name="delete_client" class="btn-action btn-deactivate"
                                                            <?= $client_has_unit ? 'disabled' : '' ?>>
                                                            <i class="fas fa-user-slash"></i>
                                                        </button>
                                                        <span class="tooltip-text"><?= $client_has_unit ? 'Cannot deactivate: has rented unit' : 'Deactivate' ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="tooltip-wrapper">
                                                        <button type="submit" name="activate_client" class="btn-action btn-activate">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                        <span class="tooltip-text">Reactivate</span>
                                                    </div>
                                                <?php endif; ?>
                                            </form>
                                            
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="client_id" value="<?= $c['Client_ID'] ?>">
                                                <div class="tooltip-wrapper">
                                                    <button type="submit" name="hard_delete_client" class="btn-action btn-delete"
                                                        <?= $client_has_unit ? 'disabled' : '' ?>>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <span class="tooltip-text"><?= $client_has_unit ? 'Cannot delete: has rented unit' : 'Delete' ?></span>
                                                </div>
                                            </form>
                                            
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="client_id" value="<?= $c['Client_ID'] ?>">
                                                <div class="tooltip-wrapper">
                                                    <button type="submit" name="nuke_client" class="btn-action btn-nuke">
                                                        <i class="fas fa-bomb"></i>
                                                    </button>
                                                    <span class="tooltip-text">Nuke (Delete + Free Unit)</span>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h4>No clients found</h4>
                        <p>There are no clients in the system</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Units Card -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-home"></i>
                <span>Units</span>
                <span class="badge bg-primary ms-2"><?= count($units) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($units)): ?>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Type ID</th>
                                    <th>Price</th>
                                    <th>Renter</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($units as $u): 
                                    $has_renter = !empty($u['Client_fn']);
                                    $renter_name = $has_renter ? htmlspecialchars($u['Client_fn'] . ' ' . $u['Client_ln']) : '';
                                ?>
                                <tr>
                                    <td>
                                        <span class="fw-medium">#<?= htmlspecialchars($u['Space_ID']) ?></span>
                                    </td>
                                    <td>
                                        <form method="post" class="d-inline-flex align-items-center" style="gap:0.5rem;">
                                            <input type="hidden" name="space_id" value="<?= $u['Space_ID'] ?>">
                                            <input type="text" name="new_name" value="<?= htmlspecialchars($u['Name']) ?>" class="form-control form-control-sm" style="width:120px;" required>
                                            <button type="submit" name="rename_unit" class="btn-action btn-update" title="Rename Unit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td><?= htmlspecialchars($u['SpaceType_ID']) ?></td>
                                    <td>
                                        <form method="post" class="price-form">
                                            <input type="hidden" name="space_id" value="<?= $u['Space_ID'] ?>">
                                            <input type="number" min="0" step="0.01" name="new_price" value="<?= htmlspecialchars($u['Price']) ?>" class="form-control form-control-sm price-input" required>
                                            <button type="submit" name="update_price" class="btn-action btn-update">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <?= $has_renter ? $renter_name : '<span class="text-muted">None</span>' ?>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="space_id" value="<?= $u['Space_ID'] ?>">
                                                <div class="tooltip-wrapper">
                                                    <button type="submit" name="delete_unit" class="btn-action btn-delete" <?= $has_renter ? 'disabled' : '' ?>>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <span class="tooltip-text"><?= $has_renter ? 'Cannot delete: has renter' : 'Delete Unit' ?></span>
                                                </div>
                                            </form>
                                            
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="space_id" value="<?= $u['Space_ID'] ?>">
                                                <div class="tooltip-wrapper">
                                                    <button type="submit" name="force_delete_unit" class="btn-action btn-force-delete">
                                                        <i class="fas fa-skull"></i>
                                                    </button>
                                                    <span class="tooltip-text">Force Delete (Ignores Renters)</span>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-home"></i>
                        <h4>No units found</h4>
                        <p>There are no units in the system</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Confirmations for destructive actions
        document.querySelectorAll('form').forEach(form => {
            if (form.querySelector('[name="nuke_client"]')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('!!! NUKE !!!\nPERMANENTLY DELETE this client and ALL their records.\nAny space they rent will be set to available! THIS CANNOT BE UNDONE. Are you SURE?')) {
                        e.preventDefault();
                    }
                });
            }
            
            if (form.querySelector('[name="hard_delete_client"]')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('PERMANENTLY DELETE this client and all their records? This cannot be undone!')) {
                        e.preventDefault();
                    }
                });
            }
            
            if (form.querySelector('[name="delete_unit"]')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Permanently delete this unit and all its records? This cannot be undone!')) {
                        e.preventDefault();
                    }
                });
            }
            
            if (form.querySelector('[name="force_delete_unit"]')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('!!! FORCE DELETE !!!\nThis will PERMANENTLY DELETE the unit and ALL associated records, INCLUDING any renter relationships.\nThis CANNOT BE UNDONE and will likely orphan client records!\n\nAre you absolutely certain?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>