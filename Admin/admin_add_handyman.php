<?php
session_start();
require_once '../database/database.php';

$db = new Database();

// --- Authentication ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$edit = false;
$edit_data = ['Handyman_ID' => '', 'Handyman_fn' => '', 'Handyman_ln' => '', 'Phone' => '', 'JobType_ID' => ''];
$msg = '';

// --- Handle DELETE Request ---
if (isset($_GET['delete'])) {
    $hid = intval($_GET['delete']);
    if ($db->deleteHandyman($hid)) {
        header("Location: admin_add_handyman.php?msg=deleted");
    } else {
        header("Location: admin_add_handyman.php?msg=error");
    }
    exit;
}

// --- Handle POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add a new job type
    if (isset($_POST['add_jobtype'])) {
        $new_jobtype = trim($_POST['NewJobType'] ?? '');
        if (!empty($new_jobtype)) {
            if ($db->addJobType($new_jobtype)) {
                header("Location: admin_add_handyman.php?msg=jobtype_added");
                exit;
            } else {
                $msg = "Failed to add new job type.";
            }
        } else {
            $msg = "Job type name cannot be empty.";
        }
    } else {
        // Handle adding/updating a handyman
        $fn = trim($_POST['Handyman_fn'] ?? '');
        $ln = trim($_POST['Handyman_ln'] ?? '');
        $phone = trim($_POST['Phone'] ?? '');
        $jobtype_id = intval($_POST['JobType_ID'] ?? 0);

        // Use strict checks to avoid "" and 0
        if ($fn !== '' && $ln !== '' && $phone !== '' && $jobtype_id > 0) {
            if (isset($_POST['handyman_id']) && !empty($_POST['handyman_id'])) {
                // UPDATE
                $id = intval($_POST['handyman_id']);
                if ($db->updateHandyman($id, $fn, $ln, $phone, $jobtype_id)) {
                    header("Location: admin_add_handyman.php?msg=updated");
                    exit;
                } else { $msg = "Failed to update handyman."; }
            } else {
                // ADD
                if ($db->addHandyman($fn, $ln, $phone, $jobtype_id)) {
                    header("Location: admin_add_handyman.php?msg=added");
                    exit;
                } else { $msg = "Failed to add handyman."; }
            }
        } else {
            $msg = "All handyman fields are required.";
        }
    }
}

// --- Handle EDIT Request (to populate the form) ---
if (isset($_GET['edit'])) {
    $edit = true;
    $hid = intval($_GET['edit']);
    $data = $db->getHandymanById($hid);
    if ($data) {
        $edit_data = $data;
    }
}

// --- Fetch Data for Display ---
$jobtypes = $db->getAllJobTypes();
$handymen_list = $db->getAllHandymenWithJob();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, minimum-scale=1.0, maximum-scale=5.0">
    <title>Handyman Management | ASRT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --sidebar-width: 280px;
            --header-height: 60px;
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
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .mobile-overlay.active {
            display: block;
            opacity: 1;
        }

        /* Mobile Header */
        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
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
            min-width: 44px;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
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

        .sidebar-close {
            display: none;
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            font-size: 1rem;
            transition: var(--transition);
        }

        .sidebar-close:hover {
            background: rgba(255, 255, 255, 0.2);
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
            min-height: 100vh;
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
            margin: 0;
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
        
        /* Table Styling */
        .table-container {
            overflow-x: auto;
            border-radius: var(--border-radius);
        }
        
        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 700px;
        }
        
        .custom-table th {
            background-color: #f9fafb;
            padding: 0.75rem 1rem;
            font-weight: 600;
            text-align: left;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
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

        /* Mobile Card Layout */
        .mobile-handyman-cards {
            display: none;
        }

        .handyman-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            padding: 1rem;
            border-left: 4px solid var(--primary);
        }

        .handyman-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .handyman-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
        }

        .handyman-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .handyman-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .handyman-detail i {
            width: 16px;
            color: #6b7280;
        }

        .handyman-actions {
            display: flex;
            gap: 0.5rem;
            padding-top: 0.75rem;
            border-top: 1px solid #f3f4f6;
        }

        .handyman-actions .btn {
            flex: 1;
        }
        
        /* Form Styling */
        .form-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            border: 1px solid #e5e7eb;
            transition: var(--transition);
            font-size: 0.95rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        /* Button Styling */
        .btn {
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-height: 44px;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--secondary);
            border-color: var(--secondary);
        }

        .btn-success:hover {
            background: #0da271;
            border-color: #0da271;
        }

        .btn-warning {
            background: var(--warning);
            border-color: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            border-color: #d97706;
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background: #dc2626;
            border-color: #dc2626;
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
        
        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.75rem;
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
        
        /* Mobile Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-close {
                display: block;
            }

            .mobile-header {
                display: flex;
            }
            
            .main-content {
                margin-left: 0;
                margin-top: var(--header-height);
                padding: 1.5rem;
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

            .table-container {
                display: none;
            }

            .mobile-handyman-cards {
                display: block;
            }

            .form-section {
                padding: 1rem;
            }

            .card-body {
                padding: 1rem;
            }

            .card-header {
                padding: 1rem;
                font-size: 1rem;
            }

            .btn {
                font-size: 0.9rem;
            }

            .btn-sm {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .row .col-lg-8, .row .col-lg-4 {
                margin-bottom: 1rem;
            }

            .handyman-actions {
                flex-direction: column;
            }

            .handyman-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .welcome-text h1 {
                font-size: 1.3rem;
            }

            .form-section {
                border-radius: 8px;
                padding: 0.75rem;
            }

            .handyman-card {
                padding: 0.75rem;
            }

            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn, .mobile-menu-btn, .nav-link {
                min-height: 44px;
                min-width: 44px;
            }

            .btn-primary:hover, .btn-success:hover {
                transform: none;
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
        <button class="sidebar-close" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>

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
                <a href="admin_add_handyman.php" class="nav-link active">
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
                <h1>Handyman Management</h1>
                <p>Manage your handymen and job types</p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if ($msg): ?>
            <div class="alert alert-danger animate-fade-in"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Handyman List Card -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-list"></i>
                <span>Handyman List</span>
                <span class="badge bg-primary ms-2"><?= count($handymen_list) ?></span>
            </div>
            <div class="card-body p-0">
                <!-- Desktop Table -->
                <div class="table-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Phone</th>
                                <th>Job Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($handymen_list)): ?>
                            <?php foreach($handymen_list as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['Handyman_fn']) ?></td>
                                    <td><?= htmlspecialchars($r['Handyman_ln']) ?></td>
                                    <td><?= htmlspecialchars($r['Phone']) ?></td>
                                    <td><?= htmlspecialchars($r['JobType_Name'] ?? '—') ?></td>
                                    <td>
                                        <a href="?edit=<?= $r['Handyman_ID'] ?>" class="btn btn-sm btn-warning me-1">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <a href="#" class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $r['Handyman_ID'] ?>)">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h4>No handymen found</h4>
                                        <p>Add your first handyman using the form below.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="mobile-handyman-cards">
                    <?php if (!empty($handymen_list)): ?>
                        <?php foreach($handymen_list as $r): ?>
                            <div class="handyman-card">
                                <div class="handyman-card-header">
                                    <div class="handyman-name">
                                        <?= htmlspecialchars($r['Handyman_fn'] . ' ' . $r['Handyman_ln']) ?>
                                    </div>
                                </div>
                                
                                <div class="handyman-details">
                                    <div class="handyman-detail">
                                        <i class="fas fa-phone"></i>
                                        <span><?= htmlspecialchars($r['Phone']) ?></span>
                                    </div>
                                    <div class="handyman-detail">
                                        <i class="fas fa-briefcase"></i>
                                        <span><?= htmlspecialchars($r['JobType_Name'] ?? 'No job type assigned') ?></span>
                                    </div>
                                </div>

                                <div class="handyman-actions">
                                    <a href="?edit=<?= $r['Handyman_ID'] ?>" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <button class="btn btn-danger" onclick="confirmDelete(<?= $r['Handyman_ID'] ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h4>No handymen found</h4>
                            <p>Add your first handyman using the form below.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Add/Edit Handyman Form -->
            <div class="col-lg-8">
                <div class="form-section animate-fade-in" style="animation-delay: 0.1s;">
                    <h4 class="mb-4"><i class="fas fa-user-plus me-2 text-primary"></i><?= $edit ? 'Edit Handyman' : 'Add New Handyman' ?></h4>
                    
                    <form method="POST">
                        <?php if ($edit): ?>
                            <input type="hidden" name="handyman_id" value="<?= $edit_data['Handyman_ID'] ?>">
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name*</label>
                                <input type="text" name="Handyman_fn" class="form-control" required
                                    value="<?= htmlspecialchars($edit_data['Handyman_fn']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name*</label>
                                <input type="text" name="Handyman_ln" class="form-control" required
                                    value="<?= htmlspecialchars($edit_data['Handyman_ln']) ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone*</label>
                                <input type="text" name="Phone" class="form-control" required
                                    value="<?= htmlspecialchars($edit_data['Phone']) ?>" placeholder="e.g., +31 6 12345678">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Job Type*</label>
                                <select name="JobType_ID" class="form-select" required>
                                    <option value="">-- Select Job Type --</option>
                                    <?php foreach ($jobtypes as $jt): ?>
                                        <option value="<?= $jt['JobType_ID'] ?>"
                                            <?= $jt['JobType_ID'] == $edit_data['JobType_ID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($jt['JobType_Name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas <?= $edit ? 'fa-save' : 'fa-plus-circle' ?>"></i><?= $edit ? 'Update' : 'Add' ?> Handyman
                            </button>
                            <?php if ($edit): ?>
                                <a href="admin_add_handyman.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>Cancel Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Add Job Type Form -->
            <div class="col-lg-4">
                <div class="form-section animate-fade-in" style="animation-delay: 0.2s;">
                    <h4 class="mb-4"><i class="fas fa-briefcase me-2 text-primary"></i>Add New Job Type</h4>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Job Type Name*</label>
                            <input type="text" name="NewJobType" class="form-control" required 
                                placeholder="e.g., General Cleaning, Plumbing, Electrical">
                        </div>
                        <button type="submit" name="add_jobtype" class="btn btn-success w-100">
                            <i class="fas fa-plus-circle"></i>Add Job Type
                        </button>
                    </form>

                    <!-- Current Job Types List -->
                    <div class="mt-4">
                        <h6 class="text-muted mb-3">Current Job Types:</h6>
                        <?php if (!empty($jobtypes)): ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach($jobtypes as $jt): ?>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($jt['JobType_Name']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small">No job types available. Add one above.</p>
                        <?php endif; ?>
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
        const sidebarClose = document.getElementById('sidebarClose');

        function toggleMobileMenu() {
            sidebar.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
        }

        function closeMobileMenu() {
            sidebar.classList.remove('active');
            mobileOverlay.classList.remove('active');
        }

        // Only toggle menu when clicking the menu button
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMobileMenu();
        });

        // Close menu when clicking overlay or close button
        mobileOverlay.addEventListener('click', function(e) {
            e.stopPropagation();
            closeMobileMenu();
        });

        sidebarClose.addEventListener('click', function(e) {
            e.stopPropagation();
            closeMobileMenu();
        });

        // Close mobile menu when clicking on nav links only
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                if (window.innerWidth <= 992) {
                    closeMobileMenu();
                }
            });
        });

        // Prevent sidebar clicks from bubbling up
        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Close menu on main content click, but not form interactions
        document.querySelector('.main-content').addEventListener('click', function(e) {
            // Don't close if clicking on form elements or buttons
            if (e.target.closest('form') || 
                e.target.closest('button') || 
                e.target.closest('select') || 
                e.target.closest('input') ||
                e.target.closest('.handyman-card') ||
                e.target.closest('.form-section')) {
                return;
            }
            
            if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
                closeMobileMenu();
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                closeMobileMenu();
            }
        });

        // Success/Error message handling
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const msg = urlParams.get('msg');
            let title = 'Success!';
            let text = '';
            let icon = 'success';
            
            if (msg === 'added') text = 'Handyman successfully added.';
            if (msg === 'updated') text = 'Handyman successfully updated.';
            if (msg === 'deleted') text = 'Handyman successfully deleted.';
            if (msg === 'jobtype_added') text = 'New job type added successfully.';
            if (msg === 'error') { 
                title = 'Error!'; 
                text = 'An operation could not be completed.'; 
                icon = 'error';
            }

            if (text) {
                Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        // Delete confirmation
        function confirmDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete the handyman and their job assignment.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete=' + id;
                }
            });
        }

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
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
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

        // Form validation feedback
        document.querySelectorAll('input[required], select[required]').forEach(field => {
            field.addEventListener('invalid', function(e) {
                e.preventDefault();
                this.classList.add('is-invalid');
                
                // Remove invalid class on input
                this.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                }, { once: true });
            });
        });

        // Phone number formatting (basic)
        document.querySelector('input[name="Phone"]')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            if (value.length > 0) {
                // Basic Dutch phone formatting
                if (value.startsWith('31')) {
                    value = '+' + value;
                } else if (value.startsWith('6') && value.length === 9) {
                    value = '+31 ' + value;
                }
            }
            // Don't force format to allow international numbers
        });

        // Touch gestures for mobile sidebar (swipe)
        let startX = 0;
        let currentX = 0;
        let isDragging = false;
        
        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            isDragging = true;
        });
        
        document.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            currentX = e.touches[0].clientX;
            const diffX = currentX - startX;
            
            // Swipe right to open sidebar (from left edge)
            if (diffX > 50 && startX < 50 && window.innerWidth <= 992) {
                toggleMobileMenu();
                isDragging = false;
            }
            
            // Swipe left to close sidebar
            if (diffX < -50 && sidebar.classList.contains('active')) {
                closeMobileMenu();
                isDragging = false;
            }
        });
        
        document.addEventListener('touchend', () => {
            isDragging = false;
        });
    </script>
</body>
</html>