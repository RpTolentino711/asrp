<?php
session_start();
require '../database/database.php';

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

$message = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['update_request'])) {
        $request_id = intval($_POST['request_id']);
        $status = $_POST['status'];
        $handyman_id = $_POST['handyman_id'] !== "" ? intval($_POST['handyman_id']) : null;

        // Handle completion photo upload
        $completion_photo = null;
        if ($status === 'Completed' && isset($_FILES['completion_photo']) && $_FILES['completion_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['completion_photo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $message = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Invalid file type for completion photo. Please upload JPG, PNG, or GIF images only.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
            } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                $message = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Completion photo is too large (max 5MB). Please choose a smaller file.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = "completion_" . time() . "_" . rand(1000, 9999) . "." . $ext;
                $upload_dir = __DIR__ . "/../uploads/maintenance_completions/";
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filepath = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $completion_photo = $filename;
                } else {
                    $message = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Failed to upload completion photo. Please try again.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                }
            }
        }

        // If status is completed and no photo was uploaded, show error
        if ($status === 'Completed' && empty($completion_photo) {
            $message = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Please upload a completion photo when marking request as completed.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        } else {
            // Update the maintenance request
            if ($db->updateMaintenanceRequest($request_id, $status, $handyman_id, $completion_photo)) {
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
    }
}

$active_requests = $db->getActiveMaintenanceRequests();
$handyman_list = $db->getAllHandymenWithJobTypes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, minimum-scale=1.0, maximum-scale=3.0">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Maintenance Requests | ASRT Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Add these new styles for photo functionality */
        .photo-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            margin-top: 1rem;
        }

        .photo-upload-container {
            border: 2px dashed #cbd5e1;
            border-radius: 0.5rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            background: white;
            position: relative;
            margin-bottom: 1rem;
        }

        .photo-upload-container:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.02);
        }

        .photo-upload-container.dragover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .photo-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
        }

        .photo-upload-label {
            cursor: pointer;
            display: block;
            position: relative;
            z-index: 5;
        }

        .photo-upload-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.75rem;
        }

        .photo-info {
            margin-top: 0.75rem;
            font-size: 0.8rem;
            color: #64748b;
        }

        .photo-preview-container {
            margin-top: 1rem;
            text-align: center;
        }

        .photo-preview {
            max-width: 150px;
            max-height: 120px;
            border-radius: 0.375rem;
            border: 2px solid var(--success);
            margin-bottom: 0.5rem;
        }

        .photo-requirements {
            background: #f1f5f9;
            border-radius: 0.375rem;
            padding: 0.75rem;
            font-size: 0.8rem;
            color: #475569;
            margin-top: 0.75rem;
        }

        .photo-requirements ul {
            margin: 0;
            padding-left: 1.25rem;
        }

        .photo-requirements li {
            margin-bottom: 0.25rem;
        }

        .existing-photos {
            margin-top: 1rem;
        }

        .photo-thumbnail {
            width: 60px;
            height: 45px;
            object-fit: cover;
            border-radius: 0.375rem;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .photo-thumbnail:hover {
            transform: scale(1.1);
        }

        .photo-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .photo-modal-img {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
        }

        /* Status-specific photo requirements */
        .photo-required {
            border-left: 4px solid var(--danger);
            background: rgba(239, 68, 68, 0.05);
        }

        /* Update existing styles for photo integration */
        .mobile-form {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            margin-top: 1.25rem;
        }

        .completion-photo-section {
            display: none;
        }

        .completion-photo-section.required {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        /* Your existing CSS remains the same below this point */
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
        
        /* Desktop Table */
        .table-desktop {
            display: block;
            overflow-x: auto;
            border-radius: var(--border-radius);
            -webkit-overflow-scrolling: touch;
        }

        .table-mobile {
            display: none;
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
            min-height: 40px;
        }
        
        .btn-save:hover:not(:disabled) {
            background: #0da271;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }

        .btn-save:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .badge-status {
            padding: 0.4rem 0.8rem;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-submitted {
            background: rgba(59, 130, 246, 0.15);
            color: #1d4ed8;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .badge-progress {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .badge-completed {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .handyman-info {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.25rem;
            font-style: italic;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
            color: var(--primary);
        }

        .empty-state h4 {
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        /* Mobile Cards */
        .mobile-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.25rem;
            padding: 1.25rem;
            border-left: 4px solid;
            transition: var(--transition);
            position: relative;
        }

        .mobile-card.status-submitted {
            border-left-color: #3b82f6;
        }

        .mobile-card.status-inprogress {
            border-left-color: #f59e0b;
        }

        .mobile-card.status-completed {
            border-left-color: #10b981;
        }

        .mobile-card-header {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .mobile-card-id {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .mobile-card-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            align-items: flex-start;
            gap: 1rem;
        }

        .mobile-card-detail .label {
            font-weight: 600;
            color: #6b7280;
            min-width: 90px;
            flex-shrink: 0;
        }

        .mobile-card-detail .value {
            color: var(--dark);
            text-align: right;
            flex: 1;
            word-break: break-word;
        }

        .mobile-form {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            margin-top: 1.25rem;
        }

        .mobile-form-group {
            margin-bottom: 1.25rem;
        }

        .mobile-form-group:last-child {
            margin-bottom: 0;
        }

        .mobile-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: #374151;
            font-size: 0.9rem;
        }

        .mobile-form select {
            width: 100%;
            padding: 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: var(--border-radius);
            font-size: 16px;
            background: white;
            transition: var(--transition);
            -webkit-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        .mobile-form select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .mobile-save-btn {
            width: 100%;
            padding: 1rem;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            transition: var(--transition);
            cursor: pointer;
            min-height: 50px;
        }

        .mobile-save-btn:active:not(:disabled) {
            transform: scale(0.98);
        }

        .mobile-save-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .current-handyman {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.85rem;
            color: var(--secondary);
            margin-top: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .current-handyman i {
            color: var(--secondary);
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: var(--border-radius);
        }

        .loading-overlay.show {
            display: flex;
        }

        .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

            /* CRITICAL FIX: Toggle table/mobile display */
            .table-desktop {
                display: none !important;
            }

            .table-mobile {
                display: block !important;
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
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-title h1 {
                font-size: 1.4rem;
            }

            .mobile-card {
                padding: 1rem;
            }

            .mobile-form {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem;
            }

            .page-title h1 {
                font-size: 1.3rem;
            }

            .mobile-card-header {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .nav-link,
            .mobile-menu-btn,
            .btn-save,
            .mobile-save-btn {
                min-height: 48px;
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
    </style>
</head>
<body>
    <div class="mobile-overlay" id="mobileOverlay"></div>

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

    <div class="main-content">
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
        
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-list-alt"></i>
                <span>Active Maintenance Requests</span>
                <span class="badge bg-primary ms-2"><?= count($active_requests) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($active_requests)): ?>
                    <!-- Desktop Table -->
                    <div class="table-desktop">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Unit</th>
                                    <th>Date</th>
                                    <th>Issue Photo</th>
                                    <th>Status</th>
                                    <th>Assign Handyman</th>
                                    <th>Completion Photo</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($active_requests as $row): ?>
                                <tr>
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="request_id" value="<?= (int)$row['Request_ID'] ?>">
                                        <td><span class="fw-medium">#<?= $row['Request_ID'] ?></span></td>
                                        <td><div class="fw-medium"><?= htmlspecialchars($row['Client_fn'] . " " . $row['Client_ln']) ?></div></td>
                                        <td><?= htmlspecialchars($row['SpaceName']) ?></td>
                                        <td><div class="text-muted"><?= htmlspecialchars($row['RequestDate']) ?></div></td>
                                        <td>
                                            <?php if (!empty($row['IssuePhoto'])): ?>
                                                <img src="../uploads/maintenance_issues/<?= htmlspecialchars($row['IssuePhoto']) ?>" 
                                                     class="photo-thumbnail" 
                                                     data-bs-toggle="modal" 
                                                     data-bs-target="#photoModal"
                                                     data-photo="../uploads/maintenance_issues/<?= htmlspecialchars($row['IssuePhoto']) ?>"
                                                     alt="Issue Photo">
                                            <?php else: ?>
                                                <span class="text-muted">No photo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select name="status" class="form-select form-select-sm" onchange="toggleCompletionPhoto(this)">
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
                                            <div class="completion-photo-section" style="display: <?= $row['Status'] === 'Completed' ? 'block' : 'none' ?>;">
                                                <input type="file" name="completion_photo" accept="image/*" class="form-control form-control-sm">
                                                <small class="text-muted">Required for completion</small>
                                            </div>
                                            <?php if (!empty($row['CompletionPhoto'])): ?>
                                                <img src="../uploads/maintenance_completions/<?= htmlspecialchars($row['CompletionPhoto']) ?>" 
                                                     class="photo-thumbnail mt-1" 
                                                     data-bs-toggle="modal" 
                                                     data-bs-target="#photoModal"
                                                     data-photo="../uploads/maintenance_completions/<?= htmlspecialchars($row['CompletionPhoto']) ?>"
                                                     alt="Completion Photo">
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
                        <?php foreach($active_requests as $index => $row): 
                            $statusClass = 'status-' . strtolower(str_replace(' ', '', $row['Status']));
                        ?>
                        <div class="mobile-card <?= $statusClass ?> animate-slide-up" style="animation-delay: <?= $index * 0.05 ?>s;">
                            <div class="loading-overlay">
                                <div class="spinner"></div>
                            </div>
                            
                            <form method="POST" action="" data-request-id="<?= $row['Request_ID'] ?>" enctype="multipart/form-data">
                                <input type="hidden" name="request_id" value="<?= (int)$row['Request_ID'] ?>">
                                
                                <div class="mobile-card-header">
                                    <div>
                                        <strong><?= htmlspecialchars($row['Client_fn'] . " " . $row['Client_ln']) ?></strong>
                                        <div class="mobile-card-id">#<?= $row['Request_ID'] ?></div>
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
                                    <span class="label"><i class="fas fa-home me-1"></i>Unit:</span>
                                    <span class="value"><?= htmlspecialchars($row['SpaceName']) ?></span>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label"><i class="fas fa-calendar me-1"></i>Date:</span>
                                    <span class="value"><?= htmlspecialchars(date('M j, Y', strtotime($row['RequestDate']))) ?></span>
                                </div>

                                <!-- Issue Photo -->
                                <?php if (!empty($row['IssuePhoto'])): ?>
                                <div class="mobile-card-detail">
                                    <span class="label"><i class="fas fa-camera me-1"></i>Issue Photo:</span>
                                    <span class="value">
                                        <img src="../uploads/maintenance_issues/<?= htmlspecialchars($row['IssuePhoto']) ?>" 
                                             class="photo-thumbnail" 
                                             data-bs-toggle="modal" 
                                             data-bs-target="#photoModal"
                                             data-photo="../uploads/maintenance_issues/<?= htmlspecialchars($row['IssuePhoto']) ?>"
                                             alt="Issue Photo">
                                    </span>
                                </div>
                                <?php endif; ?>

                                <!-- Completion Photo -->
                                <?php if (!empty($row['CompletionPhoto'])): ?>
                                <div class="mobile-card-detail">
                                    <span class="label"><i class="fas fa-check-circle me-1"></i>Completion:</span>
                                    <span class="value">
                                        <img src="../uploads/maintenance_completions/<?= htmlspecialchars($row['CompletionPhoto']) ?>" 
                                             class="photo-thumbnail" 
                                             data-bs-toggle="modal" 
                                             data-bs-target="#photoModal"
                                             data-photo="../uploads/maintenance_completions/<?= htmlspecialchars($row['CompletionPhoto']) ?>"
                                             alt="Completion Photo">
                                    </span>
                                </div>
                                <?php endif; ?>

                                <?php if ($row['Handyman_fn']): ?>
                                <div class="current-handyman">
                                    <i class="fas fa-user-tie"></i>
                                    Currently assigned: <?= htmlspecialchars($row['Handyman_fn'] . ' ' . $row['Handyman_ln']) ?>
                                </div>
                                <?php endif; ?>

                                <div class="mobile-form">
                                    <div class="mobile-form-group">
                                        <label for="status_<?= $row['Request_ID'] ?>">
                                            <i class="fas fa-tasks me-1"></i>Update Status
                                        </label>
                                        <select name="status" id="status_<?= $row['Request_ID'] ?>" onchange="toggleMobileCompletionPhoto(this)">
                                            <option value="Submitted" <?= $row['Status'] === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                                            <option value="In Progress" <?= $row['Status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="Completed" <?= $row['Status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mobile-form-group">
                                        <label for="handyman_<?= $row['Request_ID'] ?>">
                                            <i class="fas fa-user-cog me-1"></i>Assign Handyman
                                        </label>
                                        <select name="handyman_id" id="handyman_<?= $row['Request_ID'] ?>">
                                            <option value="">-- Select Handyman --</option>
                                            <?php foreach ($handyman_list as $h): ?>
                                                <option value="<?= (int)$h['Handyman_ID'] ?>" <?= $row['Handyman_ID'] == $h['Handyman_ID'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($h['Handyman_fn'] . ' ' . $h['Handyman_ln']) ?>
                                                    <?php if($h['JobTypes']): ?> - <?= htmlspecialchars($h['JobTypes']) ?><?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Completion Photo Upload (Mobile) -->
                                    <div class="completion-photo-section mobile-form-group" id="completion_photo_<?= $row['Request_ID'] ?>" style="display: <?= $row['Status'] === 'Completed' ? 'block' : 'none' ?>;">
                                        <label for="completion_photo_input_<?= $row['Request_ID'] ?>">
                                            <i class="fas fa-camera me-1"></i>Completion Photo (Required)
                                        </label>
                                        <div class="photo-upload-container">
                                            <input type="file" name="completion_photo" id="completion_photo_input_<?= $row['Request_ID'] ?>" 
                                                   class="photo-input" accept="image/*" onchange="handlePhotoSelect(this, 'completion_preview_<?= $row['Request_ID'] ?>')">
                                            <label for="completion_photo_input_<?= $row['Request_ID'] ?>" class="photo-upload-label">
                                                <div class="photo-upload-icon">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                </div>
                                                <h6>Upload Completion Photo</h6>
                                                <p class="text-muted mb-1">or drag and drop</p>
                                                <p class="photo-info">JPG, PNG, GIF up to 5MB</p>
                                                
                                                <!-- Image Preview -->
                                                <div id="completion_preview_<?= $row['Request_ID'] ?>" class="photo-preview-container" style="display: none;">
                                                    <img class="photo-preview" src="" alt="Preview">
                                                    <p class="text-success mb-0" id="completion_file_name_<?= $row['Request_ID'] ?>"></p>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="photo-requirements">
                                            <strong>Photo Requirements:</strong>
                                            <ul>
                                                <li>Clear photo showing the completed work</li>
                                                <li>Ensure the fix is visible and well-lit</li>
                                                <li>Max file size: 5MB</li>
                                            </ul>
                                        </div>
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
                    <div class="empty-state animate-fade-in">
                        <i class="fas fa-tools"></i>
                        <h4>No Active Maintenance Requests</h4>
                        <p>All maintenance requests have been processed or completed.</p>
                        <a href="dashboard.php" class="btn btn-primary mt-3">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Photo View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" class="photo-modal-img" src="" alt="Photo">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            // Photo modal functionality
            const photoModal = document.getElementById('photoModal');
            if (photoModal) {
                photoModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const photoSrc = button.getAttribute('data-photo');
                    const modalImage = document.getElementById('modalPhoto');
                    modalImage.src = photoSrc;
                });
            }

            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const statusSelect = this.querySelector('select[name="status"]');
                    const completionPhotoInput = this.querySelector('input[name="completion_photo"]');
                    
                    // Check if status is completed and no completion photo is provided
                    if (statusSelect && statusSelect.value === 'Completed') {
                        if (!completionPhotoInput || !completionPhotoInput.files || completionPhotoInput.files.length === 0) {
                            e.preventDefault();
                            alert('Please upload a completion photo when marking the request as completed.');
                            return false;
                        }
                    }

                    const submitBtn = this.querySelector('[type="submit"]');
                    const mobileCard = this.closest('.mobile-card');
                    
                    // Show loading state
                    if (mobileCard) {
                        const loadingOverlay = mobileCard.querySelector('.loading-overlay');
                        if (loadingOverlay) {
                            loadingOverlay.classList.add('show');
                        }
                    }
                    
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                        submitBtn.disabled = true;
                    }
                });
            });

            document.querySelectorAll('.alert').forEach(alert => {
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-20px)';
                        setTimeout(() => {
                            if (alert.parentNode) {
                                alert.remove();
                            }
                        }, 300);
                    }
                }, 6000);
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
            });

            // Drag and drop functionality for photo upload
            document.querySelectorAll('.photo-upload-container').forEach(container => {
                const photoInput = container.querySelector('.photo-input');
                
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
                        photoInput.files = files;
                        const previewId = container.querySelector('.photo-preview-container').id;
                        handlePhotoSelect(photoInput, previewId);
                    }
                });
            });
        });

        function toggleCompletionPhoto(select) {
            const form = select.closest('form');
            const completionSection = form.querySelector('.completion-photo-section');
            if (select.value === 'Completed') {
                completionSection.style.display = 'block';
            } else {
                completionSection.style.display = 'none';
            }
        }

        function toggleMobileCompletionPhoto(select) {
            const requestId = select.id.split('_')[1];
            const completionSection = document.getElementById('completion_photo_' + requestId);
            if (select.value === 'Completed') {
                completionSection.style.display = 'block';
                completionSection.classList.add('photo-required');
            } else {
                completionSection.style.display = 'none';
                completionSection.classList.remove('photo-required');
            }
        }

        function handlePhotoSelect(input, previewId) {
            const preview = document.getElementById(previewId);
            const fileName = document.getElementById(previewId.replace('preview', 'file_name'));
            const previewImage = preview ? preview.querySelector('img') : null;
            const photoUploadContainer = input.closest('.photo-upload-container');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPEG, PNG, GIF).');
                    input.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB.');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (previewImage) previewImage.src = e.target.result;
                    if (fileName) fileName.textContent = file.name;
                    if (preview) preview.style.display = 'block';
                    if (photoUploadContainer) {
                        photoUploadContainer.style.borderColor = '#10b981';
                        photoUploadContainer.style.background = 'rgba(16, 185, 129, 0.05)';
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>