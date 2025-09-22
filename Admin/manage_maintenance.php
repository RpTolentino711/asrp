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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, minimum-scale=1.0, maximum-scale=3.0">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
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

        /* Mobile Menu Overlay */
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

        /* Mobile Header */
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
        }

        .mobile-menu-btn:hover {
            background: rgba(0,0,0,0.1);
        }

        .mobile-menu-btn:active {
            transform: scale(0.95);
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
        
        /* Sidebar Styling */
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
        
        /* Dashboard Card */
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
            transform: none;
        }
        
        /* Status Badges */
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
        
        /* Handyman Info */
        .handyman-info {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.25rem;
            font-style: italic;
        }
        
        /* Empty State */
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

        /* Mobile Card Layout */
        .mobile-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.25rem;
            padding: 1.25rem;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
            position: relative;
        }

        .mobile-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-2px);
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
            font-size: 16px; /* Prevents zoom on iOS */
            background: white;
            transition: var(--transition);
            -webkit-appearance: none;
            -moz-appearance: none;
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

        .mobile-save-btn:hover:not(:disabled) {
            background: #0da271;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .mobile-save-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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

        /* Pull to refresh indicator */
        .pull-refresh {
            position: fixed;
            top: var(--mobile-header-height);
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0 0 12px 12px;
            font-size: 0.9rem;
            z-index: 999;
            opacity: 0;
            transition: var(--transition);
        }

        .pull-refresh.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        /* Loading states */
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

        /* Toast notifications */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050;
        }

        @media (max-width: 768px) {
            .toast-container {
                bottom: 80px;
                left: 20px;
                right: 20px;
            }
        }

        /* Swipe indicators */
        .swipe-indicator {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 1rem;
            border-radius: 50%;
            font-size: 1.2rem;
            opacity: 0;
            transition: var(--transition);
            pointer-events: none;
        }

        .swipe-indicator.left {
            left: 20px;
        }

        .swipe-indicator.right {
            right: 20px;
        }

        .swipe-indicator.show {
            opacity: 1;
        }
        
        /* Mobile Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 0 0 50px rgba(0,0,0,0.3);
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
                gap: 1.25rem;
                margin-bottom: 1.5rem;
            }

            .page-title {
                width: 100%;
            }

            .page-title h1 {
                font-size: 1.6rem;
            }

            .title-icon {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }

            .custom-table {
                display: none;
            }

            .table-mobile {
                display: block;
            }

            .card-body {
                padding: 1.25rem;
            }

            .card-header {
                padding: 1.25rem;
                font-size: 1rem;
            }

            .empty-state {
                padding: 3rem 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .mobile-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .mobile-form {
                padding: 1rem;
            }

            .mobile-card-header {
                font-size: 1rem;
                margin-bottom: 0.75rem;
            }

            .mobile-card-detail {
                margin-bottom: 0.5rem;
                font-size: 0.9rem;
            }

            .page-title h1 {
                font-size: 1.4rem;
            }

            .title-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem;
            }

            .page-title h1 {
                font-size: 1.3rem;
            }

            .dashboard-card {
                border-radius: 8px;
                margin-bottom: 1rem;
            }

            .mobile-card {
                border-radius: 8px;
            }

            .mobile-form {
                padding: 0.875rem;
                border-radius: 8px;
            }

            .mobile-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .mobile-card-detail .label {
                min-width: 80px;
            }

            .empty-state {
                padding: 2rem 1rem;
            }

            .empty-state i {
                font-size: 3rem;
            }
        }

        /* High DPI displays */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 2dppx) {
            .sidebar-brand i,
            .title-icon i,
            .card-header i {
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn-save, 
            .nav-link, 
            .mobile-menu-btn, 
            .mobile-save-btn,
            .form-select,
            .mobile-form select {
                min-height: 48px;
                min-width: 48px;
            }

            .btn-save:hover,
            .mobile-save-btn:hover {
                transform: none;
            }

            .nav-link:hover {
                transform: none;
            }

            .mobile-card:hover {
                transform: none;
            }

            /* Remove hover effects on touch devices */
            .custom-table tr:hover {
                background-color: transparent;
            }
        }

        /* Focus states for accessibility */
        .mobile-menu-btn:focus,
        .nav-link:focus,
        .btn-save:focus,
        .mobile-save-btn:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        .form-select:focus,
        .mobile-form select:focus {
            outline: none;
        }
        
        /* Animations */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        .animate-slide-up {
            animation: slideUp 0.3s ease-out;
        }

        .animate-scale-in {
            animation: scaleIn 0.2s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        /* Reduced motion for accessibility */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Dark mode support (optional) */
        @media (prefers-color-scheme: dark) {
            /* Add dark mode styles if needed */
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Pull to refresh indicator -->
    <div class="pull-refresh" id="pullRefresh">
        <i class="fas fa-sync-alt"></i> Pull to refresh
    </div>

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
                                    <form method="post" class="desktop-form" data-request-id="<?= $row['Request_ID'] ?>">
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
                        <?php foreach($active_requests as $index => $row): 
                            $statusClass = strtolower(str_replace(' ', '', $row['Status']));
                            if ($statusClass === 'inprogress') $statusClass = 'progress';
                            $cardClass = 'mobile-card ' . $statusClass;
                        ?>
                        <div class="<?= $cardClass ?> animate-slide-up" style="animation-delay: <?= $index * 0.1 ?>s;">
                            <div class="loading-overlay">
                                <div class="spinner"></div>
                            </div>
                            
                            <form method="post" class="mobile-form-container" data-request-id="<?= $row['Request_ID'] ?>">
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

                                <?php if (!empty($row['Description'])): ?>
                                <div class="mobile-card-detail">
                                    <span class="label"><i class="fas fa-info-circle me-1"></i>Issue:</span>
                                    <span class="value"><?= htmlspecialchars(substr($row['Description'], 0, 100)) ?><?= strlen($row['Description']) > 100 ? '...' : '' ?></span>
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
                                        <select name="status" id="status_<?= $row['Request_ID'] ?>">
                                            <option value="Submitted" <?= $row['Status'] === 'Submitted' ? 'selected' : '' ?>>📝 Submitted</option>
                                            <option value="In Progress" <?= $row['Status'] === 'In Progress' ? 'selected' : '' ?>>⚠️ In Progress</option>
                                            <option value="Completed" <?= $row['Status'] === 'Completed' ? 'selected' : '' ?>>✅ Completed</option>
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
                                    
                                    <button type="submit" name="update_request" class="mobile-save-btn">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Swipe indicators -->
                            <div class="swipe-indicator left">
                                <i class="fas fa-arrow-left"></i>
                            </div>
                            <div class="swipe-indicator right">
                                <i class="fas fa-arrow-right"></i>
                            </div>
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

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced mobile functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu functionality
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            function toggleMobileMenu() {
                sidebar.classList.toggle('active');
                mobileOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            }

            mobileMenuBtn.addEventListener('click', toggleMobileMenu);
            mobileOverlay.addEventListener('click', toggleMobileMenu);

            // Close mobile menu when clicking on nav links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 992) {
                        sidebar.classList.remove('active');
                        mobileOverlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
            });

            // Handle window resize
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (window.innerWidth > 992) {
                        sidebar.classList.remove('active');
                        mobileOverlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                }, 250);
            });

            // Enhanced form submission with loading states
            document.querySelectorAll('form').forEach(form => {
                let isSubmitting = false;
                
                form.addEventListener('submit', function(e) {
                    if (isSubmitting) {
                        e.preventDefault();
                        return false;
                    }
                    
                    isSubmitting = true;
                    const requestId = this.dataset.requestId;
                    
                    // Show loading overlay for mobile cards
                    const mobileCard = this.closest('.mobile-card');
                    if (mobileCard) {
                        const loadingOverlay = mobileCard.querySelector('.loading-overlay');
                        if (loadingOverlay) {
                            loadingOverlay.classList.add('show');
                        }
                    }
                    
                    // Show loading state on submit button
                    const submitBtn = form.querySelector('[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                        submitBtn.disabled = true;
                        
                        // Store original text for restoration
                        submitBtn.dataset.originalText = originalText;
                    }
                    
                    // Show toast notification
                    showToast('Updating request #' + requestId + '...', 'info');
                    
                    // Reset after timeout in case of network issues
                    setTimeout(() => {
                        isSubmitting = false;
                        if (submitBtn && submitBtn.disabled) {
                            submitBtn.innerHTML = submitBtn.dataset.originalText || originalText;
                            submitBtn.disabled = false;
                        }
                        if (mobileCard) {
                            const loadingOverlay = mobileCard.querySelector('.loading-overlay');
                            if (loadingOverlay) {
                                loadingOverlay.classList.remove('show');
                            }
                        }
                    }, 10000);
                });
            });

            // Auto-hide alerts with enhanced animation
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

            // Enhanced status change confirmation
            document.addEventListener('change', function(e) {
                if (e.target.name === 'status') {
                    const newStatus = e.target.value;
                    const requestId = e.target.closest('form').dataset.requestId;
                    
                    if (newStatus === 'Completed') {
                        if (!confirm('Mark maintenance request #' + requestId + ' as completed?\n\nThis will finalize the request and notify the client.')) {
                            // Reset to previous value
                            e.target.selectedIndex = [...e.target.options].findIndex(option => option.selected && option.value !== newStatus);
                            return;
                        }
                    }
                    
                    // Auto-save on status change for better UX
                    if (window.innerWidth <= 992) {
                        const form = e.target.closest('form');
                        const saveBtn = form.querySelector('[type="submit"]');
                        if (saveBtn) {
                            // Highlight the save button
                            saveBtn.style.background = '#f59e0b';
                            saveBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Save Required';
                            
                            setTimeout(() => {
                                saveBtn.style.background = '';
                                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                            }, 3000);
                        }
                    }
                }
            });

            // Pull to refresh functionality (mobile only)
            let startY = 0;
            let pullDistance = 0;
            let isPulling = false;
            const pullRefresh = document.getElementById('pullRefresh');
            
            if (window.innerWidth <= 992) {
                document.addEventListener('touchstart', function(e) {
                    if (window.scrollY === 0) {
                        startY = e.touches[0].pageY;
                        isPulling = true;
                    }
                }, { passive: true });
                
                document.addEventListener('touchmove', function(e) {
                    if (!isPulling) return;
                    
                    pullDistance = e.touches[0].pageY - startY;
                    
                    if (pullDistance > 0 && window.scrollY === 0) {
                        if (pullDistance > 80) {
                            pullRefresh.classList.add('show');
                            pullRefresh.innerHTML = '<i class="fas fa-arrow-down"></i> Release to refresh';
                        } else if (pullDistance > 20) {
                            pullRefresh.classList.add('show');
                            pullRefresh.innerHTML = '<i class="fas fa-arrow-down"></i> Pull to refresh';
                        }
                    }
                }, { passive: true });
                
                document.addEventListener('touchend', function(e) {
                    if (isPulling && pullDistance > 80) {
                        pullRefresh.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Refreshing...';
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        pullRefresh.classList.remove('show');
                    }
                    
                    isPulling = false;
                    pullDistance = 0;
                }, { passive: true });
            }

            // Toast notification system
            function showToast(message, type = 'info', duration = 4000) {
                const toastContainer = document.querySelector('.toast-container');
                if (!toastContainer) return;
                
                const toast = document.createElement('div');
                toast.className = `alert alert-${type} alert-dismissible fade show animate-scale-in`;
                toast.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                toastContainer.appendChild(toast);
                
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.style.opacity = '0';
                        toast.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.remove();
                            }
                        }, 300);
                    }
                }, duration);
            }

            // Enhanced keyboard navigation
            document.addEventListener('keydown', function(e) {
                // Escape key closes mobile menu
                if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
                
                // Ctrl/Cmd + S to save first form
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    const firstForm = document.querySelector('form');
                    if (firstForm) {
                        firstForm.submit();
                    }
                }
            });

            // Service Worker for offline support (if needed)
            if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
                navigator.serviceWorker.register('/sw.js').catch(console.error);
            }

            // Enhanced touch gestures for mobile cards
            if (window.innerWidth <= 992) {
                let touchStartX = 0;
                let touchStartY = 0;
                let touchEndX = 0;
                let touchEndY = 0;
                
                document.querySelectorAll('.mobile-card').forEach(card => {
                    card.addEventListener('touchstart', function(e) {
                        touchStartX = e.changedTouches[0].screenX;
                        touchStartY = e.changedTouches[0].screenY;
                    }, { passive: true });
                    
                    card.addEventListener('touchend', function(e) {
                        touchEndX = e.changedTouches[0].screenX;
                        touchEndY = e.changedTouches[0].screenY;
                        handleSwipe(this);
                    }, { passive: true });
                });
                
                function handleSwipe(element) {
                    const swipeThreshold = 100;
                    const swipeLength = Math.abs(touchEndX - touchStartX);
                    const swipeHeight = Math.abs(touchEndY - touchStartY);
                    
                    // Horizontal swipe detection
                    if (swipeLength > swipeThreshold && swipeHeight < 100) {
                        const leftIndicator = element.querySelector('.swipe-indicator.left');
                        const rightIndicator = element.querySelector('.swipe-indicator.right');
                        
                        if (touchEndX < touchStartX) {
                            // Swipe left - could trigger quick actions
                            if (rightIndicator) {
                                rightIndicator.classList.add('show');
                                setTimeout(() => rightIndicator.classList.remove('show'), 1000);
                            }
                        } else {
                            // Swipe right - could trigger quick actions
                            if (leftIndicator) {
                                leftIndicator.classList.add('show');
                                setTimeout(() => leftIndicator.classList.remove('show'), 1000);
                            }
                        }
                    }
                }
            }

            // Performance optimization - Intersection Observer for animations
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.animationPlayState = 'running';
                        }
                    });
                });
                
                document.querySelectorAll('.animate-slide-up').forEach(el => {
                    el.style.animationPlayState = 'paused';
                    observer.observe(el);
                });
            }
        });
    </script>
</body>
</html>