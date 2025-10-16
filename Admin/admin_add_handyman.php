<?php
session_start();
require_once '../database/database.php';

$db = new Database();

// --- NOTIFICATION SYSTEM VARIABLES ---
$unseen_rentals_sql = "SELECT COUNT(*) as count FROM rentalrequest WHERE Status = 'Pending' AND admin_seen = 0 AND Flow_Status = 'new'";
$unseen_rentals_result = $db->getRow($unseen_rentals_sql);
$unseen_rentals = $unseen_rentals_result['count'] ?? 0;

$new_maintenance_sql = "SELECT COUNT(*) as count FROM maintenancerequest WHERE Status = 'Submitted' AND admin_seen = 0";
$new_maintenance_result = $db->getRow($new_maintenance_sql);
$new_maintenance_requests = $new_maintenance_result['count'] ?? 0;

$unread_messages_sql = "SELECT COUNT(*) as count FROM invoice_chat WHERE Sender_Type = 'client' AND is_read_admin = 0";
$unread_messages_result = $db->getRow($unread_messages_sql);
$unread_client_messages = $unread_messages_result['count'] ?? 0;

// Get counts for sidebar badges
$rental_count = $db->getRow("SELECT COUNT(*) as count FROM rentalrequest WHERE Status = 'Pending' AND Flow_Status = 'new'")['count'];
$maintenance_count = $db->getRow("SELECT COUNT(*) as count FROM maintenancerequest WHERE Status = 'Submitted'")['count'];
$chat_count = $db->getRow("SELECT COUNT(*) as count FROM invoice_chat WHERE Sender_Type = 'client' AND is_read_admin = 0")['count'];

// MARK ALL MAINTENANCE REQUESTS AS SEEN WHEN ADMIN VIEWS THE PAGE
$db->executeStatement(
    "UPDATE maintenancerequest SET admin_seen = 1 WHERE Status = 'Submitted' AND admin_seen = 0"
);

// --- Authentication ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// ... rest of your existing PHP code remains the same ...
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
            --mobile-header-height: 65px;
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
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 999;
            display: none;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }

        .mobile-overlay.active {
            display: block;
            animation: fadeInOverlay 0.3s ease-out;
        }

        @keyframes fadeInOverlay {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--mobile-header-height);
            background: white;
            border-bottom: 1px solid #e5e7eb;
            z-index: 1001;
            padding: 0 1rem;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .mobile-menu-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            padding: 0.75rem;
            border-radius: 8px;
            transition: var(--transition);
            min-width: 48px;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .mobile-menu-btn:active {
            background: rgba(0,0,0,0.1);
        }

        .mobile-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .mobile-brand i {
            color: var(--primary);
        }
        
        .sidebar {
            position: fixed;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--dark), var(--darker));
            color: white;
            padding: 1.5rem 1rem;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transition: var(--transition);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
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
            padding: 0.85rem 1rem;
            color: rgba(255, 255, 255, 0.85);
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.95rem;
            min-height: 48px;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
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
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: var(--transition);
            min-height: 100vh;
        }
        
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
            color: #6b7280;
            margin: 0;
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
        
        .dashboard-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
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
            -webkit-overflow-scrolling: touch;
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
            white-space: nowrap;
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

        /* Notification Styles */
        .notification-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
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

        .message-shake {
            animation: messageShake 0.5s ease-in-out;
        }

        @keyframes messageShake {
            0%, 100% { transform: rotate(0deg) scale(1); }
            25% { transform: rotate(-8deg) scale(1.1); }
            50% { transform: rotate(8deg) scale(1.1); }
            75% { transform: rotate(-4deg) scale(1.05); }
        }
        
        /* CRITICAL: Mobile Responsive Breakpoints */
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
                margin-top: var(--mobile-header-height);
                padding: 1.25rem;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .page-title h1 {
                font-size: 1.6rem;
            }

            .card-body {
                padding: 1.25rem;
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

            .custom-table {
                min-width: 700px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .dashboard-card {
                margin-bottom: 1.5rem;
            }

            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .file-upload-container {
                padding: 1.5rem;
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
        
        /* Animations */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        .animate-slide-up {
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .nav-link,
            .mobile-menu-btn,
            .btn-action,
            .file-upload-label {
                min-height: 48px;
                min-width: 48px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="mobile-brand">
            <i class="fas fa-crown"></i>
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
                    <?php if ($rental_count > 0): ?>
                        <span class="badge badge-notification bg-danger notification-badge" id="sidebarRentalBadge"><?= $rental_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="manage_maintenance.php" class="nav-link">
                    <i class="fas fa-tools"></i>
                    <span>Maintenance</span>
                    <?php if ($maintenance_count > 0): ?>
                        <span class="badge badge-notification bg-warning" id="sidebarMaintenanceBadge"><?= $maintenance_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="generate_invoice.php" class="nav-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
                    <?php if ($chat_count > 0): ?>
                        <span class="badge badge-notification bg-info" id="sidebarInvoicesBadge"><?= $chat_count ?></span>
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
        // --- COMPLETE NOTIFICATION SYSTEM ---
        let rentalNotificationCooldown = false;
        let maintenanceNotificationCooldown = false;
        let clientMessageNotificationCooldown = false;

        let lastUnseenRentals = <?= $unseen_rentals ?? 0 ?>;
        let lastUnseenMaintenance = <?= $new_maintenance_requests ?? 0 ?>;
        let lastUnreadClientMessages = <?= $unread_client_messages ?? 0 ?>;
        let isFirstLoad = true;
        let isTabActive = true;

        // Debug logging
        console.log('Initial counts - Rentals: <?= $unseen_rentals ?? 0 ?>, Maintenance: <?= $new_maintenance_requests ?? 0 ?>, Messages: <?= $unread_client_messages ?? 0 ?>');

        // Tab visibility handling
        document.addEventListener('visibilitychange', function() {
            isTabActive = !document.hidden;
            console.log('Tab visibility changed:', isTabActive ? 'active' : 'hidden');
            if (isTabActive) {
                fetchDashboardCounts();
            }
        });

        // 1. Show rental notification
        function showNewRentalNotification(count) {
            if (rentalNotificationCooldown) {
                console.log('Rental notification cooldown active');
                return;
            }
            
            console.log('Showing rental notification for', count, 'new requests');
            rentalNotificationCooldown = true;
            
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
                animation: slideInRight 0.3s ease-out;
            `;
            notification.innerHTML = `
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-bell text-success fs-4 me-3 bell-shake"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading mb-1">🏠 New Rental Request!</h6>
                        <p class="mb-2">You have <strong>${count}</strong> new pending request${count > 1 ? 's' : ''} to review.</p>
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
                rentalNotificationCooldown = false;
                console.log('Rental notification cooldown reset');
            }, 10000);
        }

        // 2. Show maintenance notification
        function showNewMaintenanceNotification(count) {
            if (maintenanceNotificationCooldown) {
                console.log('Maintenance notification cooldown active');
                return;
            }
            
            console.log('Showing maintenance notification for', count, 'new requests');
            maintenanceNotificationCooldown = true;
            
            const notification = document.createElement('div');
            notification.className = 'alert alert-warning alert-dismissible fade show';
            notification.style.cssText = `
                position: fixed; 
                top: 100px; 
                right: 20px; 
                z-index: 9999; 
                min-width: 320px; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-left: 4px solid #f59e0b;
                animation: slideInRight 0.3s ease-out;
            `;
            notification.innerHTML = `
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tools text-warning fs-4 me-3 tools-shake"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading mb-1">🔧 New Maintenance Request!</h6>
                        <p class="mb-2">You have <strong>${count}</strong> new maintenance request${count > 1 ? 's' : ''} to review.</p>
                        <div class="d-flex gap-2 mt-2">
                            <a href="manage_maintenance.php" class="btn btn-sm btn-warning text-white">
                                <i class="fas fa-tools me-1"></i>View Requests
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
                maintenanceNotificationCooldown = false;
                console.log('Maintenance notification cooldown reset');
            }, 10000);
        }

        // 3. Show client message notification
        function showNewClientMessageNotification(count) {
            if (clientMessageNotificationCooldown) {
                console.log('Client message notification cooldown active');
                return;
            }
            
            console.log('Showing client message notification for', count, 'new messages');
            clientMessageNotificationCooldown = true;
            
            const notification = document.createElement('div');
            notification.className = 'alert alert-info alert-dismissible fade show';
            notification.style.cssText = `
                position: fixed; 
                top: 180px; 
                right: 20px; 
                z-index: 9999; 
                min-width: 320px; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-left: 4px solid #06b6d4;
                animation: slideInRight 0.3s ease-out;
            `;
            notification.innerHTML = `
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-comments text-info fs-4 me-3 message-shake"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading mb-1">💬 New Client Message!</h6>
                        <p class="mb-2">You have <strong>${count}</strong> new message${count > 1 ? 's' : ''} from client${count > 1 ? 's' : ''}.</p>
                        <div class="d-flex gap-2 mt-2">
                            <a href="generate_invoice.php" class="btn btn-sm btn-info text-white">
                                <i class="fas fa-inbox me-1"></i>View Messages
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
                clientMessageNotificationCooldown = false;
                console.log('Client message notification cooldown reset');
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

        // Function to update sidebar badges
        function updateSidebarBadge(currentCount, badgeId, linkSelector) {
            const sidebarBadge = document.getElementById(badgeId);
            if (sidebarBadge) {
                const oldCount = parseInt(sidebarBadge.textContent);
                sidebarBadge.textContent = currentCount;
                updateBadgeAnimation(sidebarBadge, currentCount, oldCount);
            } else {
                // Create badge if it doesn't exist
                const link = document.querySelector(`a[href="${linkSelector}"]`);
                if (link && currentCount > 0) {
                    const newBadge = document.createElement('span');
                    newBadge.id = badgeId;
                    newBadge.className = 'badge badge-notification bg-danger notification-badge';
                    newBadge.textContent = currentCount;
                    link.appendChild(newBadge);
                }
            }
        }

        // Fetch dashboard counts
        function fetchDashboardCounts() {
            if (!isTabActive) {
                console.log('Tab not active, skipping count fetch');
                return;
            }
            
            console.log('Fetching dashboard counts...');
            fetch('../AJAX/ajax_admin_dashboard_counts.php')
                .then(res => {
                    if (!res.ok) throw new Error('Network response was not ok');
                    return res.json();
                })
                .then(data => {
                    console.log('Counts received:', data);
                    
                    if (data && !data.error) {
                        const currentUnseenRentals = data.unseen_rentals ?? 0;
                        const currentUnseenMaintenance = data.new_maintenance_requests ?? 0;
                        const currentUnreadClientMessages = data.unread_client_messages ?? 0;

                        // Check for new rental requests
                        if (!isFirstLoad && currentUnseenRentals > lastUnseenRentals) {
                            const newRequests = currentUnseenRentals - lastUnseenRentals;
                            console.log(`New rental requests detected: ${newRequests} (was ${lastUnseenRentals}, now ${currentUnseenRentals})`);
                            showNewRentalNotification(newRequests);
                            
                            // Update sidebar badge
                            updateSidebarBadge(currentUnseenRentals, 'sidebarRentalBadge', 'view_rental_requests.php');
                        }
                        
                        // Check for new maintenance requests
                        if (!isFirstLoad && currentUnseenMaintenance > lastUnseenMaintenance) {
                            const newRequests = currentUnseenMaintenance - lastUnseenMaintenance;
                            console.log(`New maintenance requests detected: ${newRequests} (was ${lastUnseenMaintenance}, now ${currentUnseenMaintenance})`);
                            showNewMaintenanceNotification(newRequests);
                            
                            // Update sidebar badge
                            updateSidebarBadge(currentUnseenMaintenance, 'sidebarMaintenanceBadge', 'manage_maintenance.php');
                        }
                        
                        // Check for new client messages
                        if (!isFirstLoad && currentUnreadClientMessages > lastUnreadClientMessages) {
                            const newMessages = currentUnreadClientMessages - lastUnreadClientMessages;
                            console.log(`New client messages detected: ${newMessages} (was ${lastUnreadClientMessages}, now ${currentUnreadClientMessages})`);
                            showNewClientMessageNotification(newMessages);
                            
                            // Update sidebar badge
                            updateSidebarBadge(currentUnreadClientMessages, 'sidebarInvoicesBadge', 'generate_invoice.php');
                        }
                        
                        lastUnseenRentals = currentUnseenRentals;
                        lastUnseenMaintenance = currentUnseenMaintenance;
                        lastUnreadClientMessages = currentUnreadClientMessages;
                        isFirstLoad = false;
                    }
                })
                .catch(err => {
                    console.error('Error fetching dashboard counts:', err);
                });
        }

        // Mobile menu functionality
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        function toggleMobileMenu() {
            sidebar.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        }
        
        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', toggleMobileMenu);
        }

        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
            });
        });

        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (window.innerWidth > 992 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    mobileOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }, 250);
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

        // Start polling for notifications
        document.addEventListener('DOMContentLoaded', () => {
            console.log('Handyman Management page fully loaded with COMPLETE notification system');
            console.log('Test notifications with: testNotification("rental") or testNotification("maintenance") or testNotification("client_message")');
            
            fetchDashboardCounts();
            
            // Poll every 5 seconds for faster response
            setInterval(() => {
                if (isTabActive) {
                    fetchDashboardCounts();
                }
            }, 5000);
        });

        // Debug: Manual trigger for testing
        window.testNotification = function(type) {
            if (type === 'rental') {
                showNewRentalNotification(1);
            } else if (type === 'maintenance') {
                showNewMaintenanceNotification(1);
            } else if (type === 'client_message') {
                showNewClientMessageNotification(1);
            }
        };

        // Add slideInRight animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>