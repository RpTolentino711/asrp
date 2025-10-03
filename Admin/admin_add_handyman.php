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

// --- Handle DELETE Handyman Request ---
if (isset($_GET['delete'])) {
    $hid = intval($_GET['delete']);
    if ($db->deleteHandyman($hid)) {
        header("Location: admin_add_handyman.php?msg=deleted");
    } else {
        header("Location: admin_add_handyman.php?msg=error");
    }
    exit;
}

// --- Handle DELETE JobType Request ---
if (isset($_GET['delete_jobtype'])) {
    $jid = intval($_GET['delete_jobtype']);
    if ($db->deleteJobType($jid)) {
        header("Location: admin_add_handyman.php?msg=jobtype_deleted");
    } else {
        header("Location: admin_add_handyman.php?msg=jobtype_delete_error");
    }
    exit;
}

// --- Handle DELETE Icon Request ---
if (isset($_GET['delete_icon'])) {
    $jid = intval($_GET['delete_icon']);
    
    // Get job type details first
    $jobtype = $db->getJobTypeById($jid);
    if ($jobtype && !empty($jobtype['Icon'])) {
        $icon_path = '../uploads/jobtype_icons/' . $jobtype['Icon'];
        
        // Delete the icon file
        if (file_exists($icon_path)) {
            unlink($icon_path);
        }
        
        // Update database to remove icon reference
        if ($db->updateJobTypeIcon($jid, null)) {
            header("Location: admin_add_handyman.php?msg=icon_deleted");
        } else {
            header("Location: admin_add_handyman.php?msg=icon_delete_error");
        }
    } else {
        header("Location: admin_add_handyman.php?msg=icon_delete_error");
    }
    exit;
}

// --- Handle POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add a new job type with image upload
    if (isset($_POST['add_jobtype'])) {
        $new_jobtype = trim($_POST['NewJobType'] ?? '');
        
        if (!empty($new_jobtype)) {
            // Handle file upload
            if (isset($_FILES['JobIcon']) && $_FILES['JobIcon']['error'] === UPLOAD_ERR_OK) {
                if ($db->addJobTypeWithImage($new_jobtype, $_FILES['JobIcon'])) {
                    header("Location: admin_add_handyman.php?msg=jobtype_added");
                    exit;
                } else {
                    $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to add new job type. Please try again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                }
            } else {
                $msg = '<div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Please select an icon image for the job type.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
            }
        } else {
            $msg = '<div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Job type name cannot be empty.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    } 
    // Update job type icon
    elseif (isset($_POST['update_jobtype_icon'])) {
        $jobtype_id = intval($_POST['jobtype_id'] ?? 0);
        
        if ($jobtype_id > 0 && isset($_FILES['JobIcon']) && $_FILES['JobIcon']['error'] === UPLOAD_ERR_OK) {
            // Get old icon to delete it
            $old_jobtype = $db->getJobTypeById($jobtype_id);
            $old_icon_path = null;
            if ($old_jobtype && !empty($old_jobtype['Icon'])) {
                $old_icon_path = '../uploads/jobtype_icons/' . $old_jobtype['Icon'];
            }
            
            // Upload new icon
            if ($db->updateJobTypeWithImage($jobtype_id, $_FILES['JobIcon'])) {
                // Delete old icon file after successful upload
                if ($old_icon_path && file_exists($old_icon_path)) {
                    unlink($old_icon_path);
                }
                header("Location: admin_add_handyman.php?msg=icon_updated");
                exit;
            } else {
                $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to update job type icon. Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
            }
        }
    } else {
        // Handle adding/updating a handyman
        $fn = trim($_POST['Handyman_fn'] ?? '');
        $ln = trim($_POST['Handyman_ln'] ?? '');
        $phone = trim($_POST['Phone'] ?? '');
        $jobtype_id = intval($_POST['JobType_ID'] ?? 0);

        if ($fn !== '' && $ln !== '' && $phone !== '' && $jobtype_id > 0) {
            if (isset($_POST['handyman_id']) && !empty($_POST['handyman_id'])) {
                // UPDATE
                $id = intval($_POST['handyman_id']);
                if ($db->updateHandyman($id, $fn, $ln, $phone, $jobtype_id)) {
                    header("Location: admin_add_handyman.php?msg=updated");
                    exit;
                } else {
                    $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to update handyman.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                }
            } else {
                // ADD
                if ($db->addHandyman($fn, $ln, $phone, $jobtype_id)) {
                    header("Location: admin_add_handyman.php?msg=added");
                    exit;
                } else {
                    $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to add handyman.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                }
            }
        } else {
            $msg = '<div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    All handyman fields are required.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
}

// --- Handle EDIT Request ---
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

// Display success messages
if (isset($_GET['msg'])) {
    $msg_type = $_GET['msg'];
    $alert_messages = [
        'added' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'Handyman successfully added to the system!'],
        'updated' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'Handyman information has been updated!'],
        'deleted' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'Handyman has been removed from the system!'],
        'jobtype_added' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'New job type has been added successfully!'],
        'jobtype_deleted' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'Job type has been deleted successfully!'],
        'icon_deleted' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'Job type icon has been removed successfully!'],
        'icon_updated' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'Job type icon has been updated successfully!'],
        'error' => ['type' => 'danger', 'icon' => 'exclamation-circle', 'text' => 'An error occurred. Please try again.'],
        'jobtype_delete_error' => ['type' => 'danger', 'icon' => 'exclamation-circle', 'text' => 'Failed to delete job type. It may be assigned to handymen.'],
        'icon_delete_error' => ['type' => 'danger', 'icon' => 'exclamation-circle', 'text' => 'Failed to delete job type icon. Please try again.']
    ];
    
    if (isset($alert_messages[$msg_type])) {
        $alert = $alert_messages[$msg_type];
        $msg = '<div class="alert alert-' . $alert['type'] . ' alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-' . $alert['icon'] . ' me-2"></i>
                ' . $alert['text'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, minimum-scale=1.0, maximum-scale=5.0">
    <title>Handyman Management | ASRT Management</title>
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
            min-width: 800px;
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
        .form-control, .form-select {
            padding: 0.65rem 0.75rem;
            font-size: 0.9rem;
            border-radius: var(--border-radius);
            border: 1px solid #d1d5db;
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
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
            cursor: pointer;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
        }
        
        .btn-edit {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .btn-edit:hover {
            background: #f59e0b;
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
        
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-success {
            background: var(--secondary);
            border-color: var(--secondary);
        }
        
        /* Status Badges */
        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.75rem;
        }

        .badge-job {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Job Type Icon Styling */
        .jobtype-icon {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }

        .jobtype-icon-lg {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
        }

        .jobtype-icon-preview {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid var(--primary);
            margin-bottom: 10px;
        }
        
        /* Action Group */
        .action-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Small Action Buttons */
        .btn-action-sm {
            padding: 0.4rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .btn-update {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .btn-update:hover {
            background: #3b82f6;
            color: white;
        }
        
        .icon-actions {
            display: flex;
            gap: 0.3rem;
            justify-content: center;
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

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        .stat-card p {
            color: #6b7280;
            margin: 0.5rem 0 0 0;
            font-size: 0.9rem;
        }

        .stat-card.secondary {
            border-left-color: var(--secondary);
        }

        .stat-card.secondary h3 {
            color: var(--secondary);
        }

        /* File Upload Styling - FIXED */
        .file-upload-container {
            border: 2px dashed #d1d5db;
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            background: #f9fafb;
            position: relative;
        }

        .file-upload-container:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .file-upload-container.dragover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
        }

        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
        }

        .file-upload-label {
            cursor: pointer;
            display: block;
            position: relative;
            z-index: 5;
        }

        .file-upload-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .file-info {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #6b7280;
        }

        /* Update Icon Modal */
        .update-icon-modal .modal-dialog {
            max-width: 500px;
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
                font-size: 0.85rem;
            }

            .card-body {
                padding: 1rem;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .file-upload-container {
                padding: 1.5rem;
            }

            .icon-actions {
                flex-direction: column;
                align-items: center;
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
                    <i class="fas fa-user-cog"></i>
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
                    <i class="fas fa-user-cog"></i>
                </div>
                <div>
                    <h1>Handyman Management</h1>
                    <p class="text-muted mb-0">Manage handymen and their job types</p>
                </div>
            </div>
        </div>
        
        <?= $msg ?>

        <!-- Statistics -->
        <div class="stats-row animate-fade-in">
            <div class="stat-card">
                <h3><?= count($handymen_list) ?></h3>
                <p><i class="fas fa-users me-2"></i>Total Handymen</p>
            </div>
            <div class="stat-card secondary">
                <h3><?= count($jobtypes) ?></h3>
                <p><i class="fas fa-briefcase me-2"></i>Job Types Available</p>
            </div>
        </div>

        <!-- Handyman List -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-list"></i>
                <span>Handyman Directory</span>
                <span class="badge bg-primary ms-2"><?= count($handymen_list) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($handymen_list)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h4>No handymen found</h4>
                        <p>Start by adding your first handyman using the form below</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Phone</th>
                                    <th>Job Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($handymen_list as $r): ?>
                                <tr>
                                    <td><span class="fw-medium">#<?= htmlspecialchars($r['Handyman_ID']) ?></span></td>
                                    <td><strong><?= htmlspecialchars($r['Handyman_fn']) ?></strong></td>
                                    <td><strong><?= htmlspecialchars($r['Handyman_ln']) ?></strong></td>
                                    <td><i class="fas fa-phone-alt text-muted me-2"></i><?= htmlspecialchars($r['Phone']) ?></td>
                                    <td>
                                        <span class="badge-job">
                                         <?php 
$icon_path = '../uploads/jobtype_icons/' . $r['Icon'];
if ($r['Icon'] && file_exists($icon_path)): ?>
    <img src="../uploads/jobtype_icons/<?= htmlspecialchars($r['Icon']) ?>" 
         alt="<?= htmlspecialchars($r['JobType_Name'] ?? 'Job Type') ?>" 
         class="jobtype-icon me-2">
<?php else: ?>
    <i class="fas fa-wrench me-2"></i>
<?php endif; ?>
                                            <?= htmlspecialchars($r['JobType_Name'] ?? 'Unassigned') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <a href="?edit=<?= $r['Handyman_ID'] ?>" class="btn-action btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button onclick="confirmDelete(<?= $r['Handyman_ID'] ?>, '<?= htmlspecialchars(addslashes($r['Handyman_fn'] . ' ' . $r['Handyman_ln'])) ?>')" 
                                                    class="btn-action btn-delete" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add/Edit Handyman Form -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-<?= $edit ? 'user-edit' : 'user-plus' ?>"></i>
                <span><?= $edit ? 'Edit Handyman' : 'Add New Handyman' ?></span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($edit): ?>
                        <input type="hidden" name="handyman_id" value="<?= $edit_data['Handyman_ID'] ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-user me-2"></i>First Name *</label>
                            <input type="text" name="Handyman_fn" class="form-control" required
                                value="<?= htmlspecialchars($edit_data['Handyman_fn']) ?>"
                                placeholder="Enter first name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-user me-2"></i>Last Name *</label>
                            <input type="text" name="Handyman_ln" class="form-control" required
                                value="<?= htmlspecialchars($edit_data['Handyman_ln']) ?>"
                                placeholder="Enter last name">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-phone me-2"></i>Phone Number *</label>
                            <input type="text" name="Phone" class="form-control" required
                                value="<?= htmlspecialchars($edit_data['Phone']) ?>"
                                placeholder="Enter phone number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-briefcase me-2"></i>Job Type *</label>
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

                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-<?= $edit ? 'save' : 'plus' ?> me-2"></i>
                            <?= $edit ? 'Update Handyman' : 'Add Handyman' ?>
                        </button>
                        <?php if ($edit): ?>
                            <a href="admin_add_handyman.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Job Types Section -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-briefcase"></i>
                <span>Job Type Management</span>
            </div>
            <div class="card-body">
                <!-- Add Job Type Form -->
                <form method="POST" id="jobTypeForm" enctype="multipart/form-data">
                    <div class="row align-items-end">
                        <div class="col-md-5 mb-3">
                            <label class="form-label"><i class="fas fa-tag me-2"></i>Job Type Name *</label>
                            <input type="text" name="NewJobType" class="form-control" required 
                                placeholder="e.g., Plumbing, Electrical, Carpentry">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label"><i class="fas fa-image me-2"></i>Job Type Icon *</label>
                            
                            <!-- File Upload Area - FIXED -->
                            <div class="file-upload-container" id="fileUploadContainer">
                                <input type="file" name="JobIcon" id="JobIcon" class="file-input" 
                                       accept="image/*" required onchange="handleFileSelect(this)">
                                <label for="JobIcon" class="file-upload-label">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <h5>Click to upload icon</h5>
                                    <p class="text-muted mb-2">or drag and drop</p>
                                    <p class="file-info">PNG, JPG, GIF up to 2MB</p>
                                    
                                    <!-- Image Preview -->
                                    <div id="imagePreview" class="mt-3" style="display: none;">
                                        <img id="previewImage" class="jobtype-icon-preview" src="" alt="Preview">
                                        <p class="text-success mb-0" id="fileName"></p>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <button type="submit" name="add_jobtype" class="btn btn-success w-100 h-100">
                                <i class="fas fa-plus-circle me-2"></i>Add Job Type
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Job Types List -->
                <div class="mt-4">
                    <h5 class="mb-3"><i class="fas fa-list me-2"></i>All Job Types</h5>
                    <?php if (empty($jobtypes)): ?>
                        <div class="empty-state">
                            <i class="fas fa-briefcase"></i>
                            <h4>No job types found</h4>
                            <p>Add your first job type using the form above</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Icon</th>
                                        <th>Name</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jobtypes as $jt): ?>
                                    <tr>
                                        <td><span class="fw-medium">#<?= $jt['JobType_ID'] ?></span></td>
                                        <td>
                                            <?php 
                                            $icon_path = '../uploads/jobtype_icons/' . $jt['Icon'];
                                            if ($jt['Icon'] && file_exists($icon_path)): ?>
                                                <img src="../uploads/jobtype_icons/<?= htmlspecialchars($jt['Icon']) ?>" 
                                                     alt="<?= htmlspecialchars($jt['JobType_Name'] ?? 'Job Type') ?>" 
                                                     class="jobtype-icon">
                                            <?php else: ?>
                                                <i class="fas fa-wrench fa-lg text-muted"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($jt['JobType_Name']) ?></strong></td>
                                        <td>
                                            <div class="icon-actions">
                                                <!-- Update Icon Button -->
                                                <button type="button" class="btn-action-sm btn-update" title="Update Icon" 
                                                        data-bs-toggle="modal" data-bs-target="#updateIconModal<?= $jt['JobType_ID'] ?>">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                
                                                <!-- Delete Icon Button -->
                                                <?php if ($jt['Icon'] && file_exists('../uploads/jobtype_icons/' . $jt['Icon'])): ?>
                                                <button onclick="confirmDeleteIcon(<?= $jt['JobType_ID'] ?>, '<?= htmlspecialchars(addslashes($jt['JobType_Name'])) ?>')" 
                                                        class="btn-action-sm btn-delete" title="Delete Icon">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <!-- Delete Job Type Button -->
                                                <button onclick="confirmDeleteJobType(<?= $jt['JobType_ID'] ?>, '<?= htmlspecialchars(addslashes($jt['JobType_Name'])) ?>')" 
                                                        class="btn-action-sm btn-delete" title="Delete Job Type">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>

                                            <!-- Update Icon Modal -->
                                            <div class="modal fade update-icon-modal" id="updateIconModal<?= $jt['JobType_ID'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Update Icon for <?= htmlspecialchars($jt['JobType_Name']) ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="POST" enctype="multipart/form-data">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="jobtype_id" value="<?= $jt['JobType_ID'] ?>">
                                                                <div class="file-upload-container">
                                                                    <input type="file" name="JobIcon" class="file-input" 
                                                                           accept="image/*" required onchange="handleFileSelect(this, 'updatePreview<?= $jt['JobType_ID'] ?>')">
                                                                    <div class="file-upload-label">
                                                                        <div class="file-upload-icon">
                                                                            <i class="fas fa-cloud-upload-alt"></i>
                                                                        </div>
                                                                        <h5>Click to upload new icon</h5>
                                                                        <p class="text-muted mb-2">or drag and drop</p>
                                                                        <p class="file-info">PNG, JPG, GIF up to 2MB</p>
                                                                        
                                                                        <!-- Image Preview -->
                                                                        <div id="updatePreview<?= $jt['JobType_ID'] ?>" class="mt-3" style="display: none;">
                                                                            <img class="jobtype-icon-preview" src="" alt="Preview">
                                                                            <p class="text-success mb-0"></p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="update_jobtype_icon" class="btn btn-primary">Update Icon</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        }
        
        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', toggleMobileMenu);
        }

        // Close mobile menu when clicking on nav links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    mobileOverlay.classList.remove('active');
                }
            });
        });

        // Fixed file upload functionality
        function handleFileSelect(input, previewId = 'imagePreview') {
            const preview = document.getElementById(previewId);
            const fileName = preview ? preview.querySelector('p') : null;
            const previewImage = preview ? preview.querySelector('img') : null;
            const fileUploadContainer = input.closest('.file-upload-container');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPEG, PNG, GIF).');
                    input.value = '';
                    return;
                }
                
                // Validate file size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB.');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (previewImage) previewImage.src = e.target.result;
                    if (fileName) fileName.textContent = file.name;
                    if (preview) preview.style.display = 'block';
                    if (fileUploadContainer) {
                        fileUploadContainer.style.borderColor = '#10b981';
                        fileUploadContainer.style.background = 'rgba(16, 185, 129, 0.05)';
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Drag and drop functionality
        document.querySelectorAll('.file-upload-container').forEach(container => {
            const fileInput = container.querySelector('.file-input');
            
            container.addEventListener('dragover', (e) => {
                e.preventDefault();
                container.classList.add('dragover');
            });

            container.addEventListener('dragleave', (e) => {
                e.preventDefault();
                container.classList.remove('dragover');
            });

            container.addEventListener('drop', (e) => {
                e.preventDefault();
                container.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    // Find the correct preview ID
                    const form = container.closest('form');
                    const isUpdate = form && form.querySelector('input[name="update_jobtype_icon"]');
                    const previewId = isUpdate ? container.querySelector('[id^="updatePreview"]').id : 'imagePreview';
                    handleFileSelect(fileInput, previewId);
                }
            });
        });

        // Confirmation dialogs
        function confirmDelete(handymanId, name) {
            if (confirm(`Are you sure you want to delete handyman "${name}"?\n\nThis action cannot be undone.`)) {
                window.location.href = '?delete=' + handymanId;
            }
        }

        function confirmDeleteJobType(jobTypeId, jobTypeName) {
            if (confirm(`Are you sure you want to delete the job type "${jobTypeName}"?\n\nThis will remove it from all handymen assigned to this job type.`)) {
                window.location.href = '?delete_jobtype=' + jobTypeId;
            }
        }

        function confirmDeleteIcon(jobTypeId, jobTypeName) {
            if (confirm(`Are you sure you want to delete the icon for "${jobTypeName}"?\n\nThe job type will remain but will use a default icon.`)) {
                window.location.href = '?delete_icon=' + jobTypeId;
            }
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

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
                mobileOverlay.classList.remove('active');
            }
        });

        // Reset file input when modal is closed
        document.querySelectorAll('.update-icon-modal').forEach(modal => {
            modal.addEventListener('hidden.bs.modal', function () {
                const fileInput = this.querySelector('input[type="file"]');
                const preview = this.querySelector('[id^="updatePreview"]');
                if (fileInput) fileInput.value = '';
                if (preview) {
                    preview.style.display = 'none';
                    const img = preview.querySelector('img');
                    const p = preview.querySelector('p');
                    if (img) img.src = '';
                    if (p) p.textContent = '';
                }
            });
        });
    </script>
</body>
</html>