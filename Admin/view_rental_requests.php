<?php
require '../database/database.php';
session_start();

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// $pending_requests = $db->getPendingRentalRequests();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, minimum-scale=1.0, maximum-scale=3.0">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Pending Rental Requests | ASRT Management</title>
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

        /* Live indicator */
        .live-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--secondary);
            font-weight: 500;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--secondary);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
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
            background: var(--danger);
            color: white;
            animation: notificationPulse 2s infinite;
        }

        @keyframes notificationPulse {
            0%, 100% { transform: translateY(-50%) scale(1); }
            50% { transform: translateY(-50%) scale(1.1); }
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
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            font-size: 1.25rem;
            position: relative;
        }

        .title-icon::after {
            content: '';
            position: absolute;
            top: -3px;
            right: -3px;
            width: 16px;
            height: 16px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid white;
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
            justify-content: space-between;
            gap: 0.75rem;
        }

        .card-header-left {
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
            background: rgba(255, 255, 255, 0.95);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: var(--border-radius);
            backdrop-filter: blur(2px);
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
        
        /* Table Styling */
        .table-container {
            overflow-x: auto;
            border-radius: var(--border-radius);
            -webkit-overflow-scrolling: touch;
            position: relative;
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
            position: sticky;
            top: 0;
            z-index: 5;
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
        
        /* Client Info with Enhanced Tooltip */
        .client-info {
            position: relative;
            cursor: help;
            transition: var(--transition);
            font-weight: 600;
            color: var(--dark);
        }
        
        .client-info:hover {
            color: var(--primary);
        }
        
        .client-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: white;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
            margin-bottom: 12px;
            min-width: 250px;
        }
        
        .client-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 8px solid transparent;
            border-top-color: var(--dark);
        }
        
        .client-info:hover .client-tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-5px);
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
        }
        
        .contact-item:last-child {
            margin-bottom: 0;
        }
        
        .contact-item i {
            width: 16px;
            color: var(--info);
            flex-shrink: 0;
        }

        .contact-item a {
            color: white;
            text-decoration: none;
            transition: var(--transition);
        }

        .contact-item a:hover {
            color: var(--info);
        }
        
        /* Enhanced Button Styling */
        .btn-action {
            padding: 0.6rem 1.25rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            min-height: 40px;
        }
        
        .btn-accept {
            background: var(--secondary);
            color: white;
        }
        
        .btn-accept:hover:not(:disabled) {
            background: #0da271;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
        }
        
        .btn-reject:hover:not(:disabled) {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-action:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Status Badges */
        .badge {
            padding: 0.4rem 0.8rem;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pending {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .badge-approved {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .badge-rejected {
            background: rgba(239, 68, 68, 0.15);
            color: #b91c1c;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Mobile Card Layout */
        .mobile-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.25rem;
            padding: 1.25rem;
            border-left: 4px solid var(--warning);
            transition: var(--transition);
            position: relative;
        }

        .mobile-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }

        .mobile-card.pending {
            border-left-color: var(--warning);
        }

        .mobile-card.approved {
            border-left-color: var(--secondary);
        }

        .mobile-card.rejected {
            border-left-color: var(--danger);
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

        .mobile-client-name {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .mobile-client-name strong {
            font-size: 1.1rem;
            color: var(--dark);
        }

        .mobile-client-name .client-id {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 500;
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
            min-width: 100px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .mobile-card-detail .value {
            color: var(--dark);
            text-align: right;
            flex: 1;
            word-break: break-word;
        }

        .mobile-contact-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .mobile-contact-info h6 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .mobile-contact-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .mobile-contact-item:last-child {
            margin-bottom: 0;
        }

        .mobile-contact-item i {
            width: 16px;
            color: var(--info);
            flex-shrink: 0;
        }

        .mobile-contact-item a {
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
        }

        .mobile-contact-item a:hover {
            color: var(--primary);
        }

        .mobile-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .mobile-btn {
            flex: 1;
            padding: 0.875rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: var(--transition);
            cursor: pointer;
            min-height: 48px;
            border: none;
            text-decoration: none;
        }

        .mobile-btn-accept {
            background: var(--secondary);
            color: white;
        }

        .mobile-btn-accept:hover:not(:disabled) {
            background: #0da271;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .mobile-btn-reject {
            background: var(--danger);
            color: white;
        }

        .mobile-btn-reject:hover:not(:disabled) {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .mobile-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Hide desktop table on mobile */
        .table-mobile {
            display: none;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
            color: var(--primary);
        }

        .empty-state h4 {
            color: var(--dark);
            margin-bottom: 0.75rem;
            font-size: 1.5rem;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .refresh-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .refresh-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
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
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .card-header-left {
                width: 100%;
                justify-content: space-between;
            }

            .live-indicator {
                font-size: 0.75rem;
            }

            .empty-state {
                padding: 3rem 1.5rem;
            }

            .client-tooltip {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                max-width: 300px;
                white-space: normal;
                text-align: left;
                min-width: auto;
                width: 90vw;
            }
            
            .client-tooltip::after {
                display: none;
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

            .mobile-contact-info {
                padding: 0.875rem;
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

            .mobile-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .mobile-btn {
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

            .dashboard-card {
                border-radius: 8px;
                margin-bottom: 1rem;
            }

            .mobile-card {
                border-radius: 8px;
                padding: 0.875rem;
            }

            .mobile-contact-info {
                padding: 0.75rem;
                border-radius: 6px;
            }

            .mobile-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .mobile-card-detail .label {
                min-width: 85px;
                font-size: 0.85rem;
            }

            .mobile-card-detail .value {
                font-size: 0.85rem;
            }

            .empty-state {
                padding: 2rem 1rem;
            }

            .empty-state i {
                font-size: 3rem;
            }

            .empty-state h4 {
                font-size: 1.3rem;
            }

            .empty-state p {
                font-size: 1rem;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn-action, 
            .nav-link, 
            .mobile-menu-btn, 
            .mobile-btn,
            .refresh-btn {
                min-height: 48px;
                min-width: 48px;
            }

            .btn-action:hover,
            .mobile-btn:hover {
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
        .btn-action:focus,
        .mobile-btn:focus,
        .refresh-btn:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
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

        /* Network status indicator */
        .network-status {
            position: fixed;
            top: var(--mobile-header-height);
            left: 50%;
            transform: translateX(-50%);
            background: var(--danger);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0 0 8px 8px;
            font-size: 0.85rem;
            z-index: 999;
            opacity: 0;
            transition: var(--transition);
        }

        .network-status.show {
            opacity: 1;
        }

        .network-status.online {
            background: var(--secondary);
        }

        /* Custom scrollbar for webkit browsers */
        .table-container::-webkit-scrollbar {
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .mobile-card {
                border: 2px solid var(--dark);
            }
            
            .btn-action {
                border: 2px solid currentColor;
            }
        }

        /* Enhanced SweetAlert2 mobile styles */
        .swal2-container {
            padding: 1rem;
        }

        @media (max-width: 768px) {
            .swal2-popup {
                width: 90vw !important;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Network Status Indicator -->
    <div class="network-status" id="networkStatus">
        <i class="fas fa-wifi"></i> Connection lost
    </div>

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
        <div class="live-indicator" id="liveIndicator">
            <div class="live-dot"></div>
            <span>LIVE</span>
        </div>
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
                <a href="view_rental_requests.php" class="nav-link active">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Rental Requests</span>
                    <span class="badge-notification" id="sidebarBadge" style="display: none;">0</span>
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
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h1>Pending Rental Requests</h1>
                    <p class="text-muted mb-0">Review and manage rental requests from clients</p>
                </div>
            </div>
        </div>
        
        <?php
        if (isset($_SESSION['admin_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    ' . htmlspecialchars($_SESSION['admin_message']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            unset($_SESSION['admin_message']);
        }
        if (isset($_SESSION['admin_error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ' . htmlspecialchars($_SESSION['admin_error']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            unset($_SESSION['admin_error']);
        }
        ?>
        
        <!-- Requests Table -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <div class="card-header-left">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-list-alt"></i>
                        <span>Pending Requests</span>
                        <span class="badge bg-primary" id="pendingCount">0</span>
                    </div>
                    <div class="live-indicator" id="desktopLiveIndicator">
                        <div class="live-dot"></div>
                        <span>Auto-refresh</span>
                    </div>
                </div>
            </div>
            <div class="card-body p-0" style="position: relative;" id="pendingRequestsContainer">
                <div class="loading-overlay" id="mainLoader">
                    <div class="spinner"></div>
                </div>
                <!-- Pending requests table will be loaded here via AJAX -->
                <noscript>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>Enable JavaScript for live updates</h4>
                    <p>Please enable JavaScript to view and manage rental requests in real-time.</p>
                </div>
                </noscript>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced mobile functionality with comprehensive features
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

            // Network status monitoring
            const networkStatus = document.getElementById('networkStatus');
            let isOnline = navigator.onLine;

            function updateNetworkStatus() {
                if (navigator.onLine !== isOnline) {
                    isOnline = navigator.onLine;
                    networkStatus.className = `network-status ${isOnline ? 'online' : ''} show`;
                    networkStatus.innerHTML = isOnline 
                        ? '<i class="fas fa-wifi"></i> Back online' 
                        : '<i class="fas fa-wifi-slash"></i> Connection lost';
                    
                    setTimeout(() => {
                        networkStatus.classList.remove('show');
                    }, isOnline ? 2000 : 5000);

                    if (isOnline) {
                        // Refresh data when back online
                        fetchPendingRequests(true);
                    }
                }
            }

            window.addEventListener('online', updateNetworkStatus);
            window.addEventListener('offline', updateNetworkStatus);

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
                        fetchPendingRequests(true).then(() => {
                            setTimeout(() => {
                                pullRefresh.classList.remove('show');
                            }, 1000);
                        });
                    } else {
                        pullRefresh.classList.remove('show');
                    }
                    
                    isPulling = false;
                    pullDistance = 0;
                }, { passive: true });
            }

            // Enhanced AJAX polling for pending rental requests
            let isLoading = false;
            let pollInterval;
            let lastUpdateTime = Date.now();
            let pausePolling = false;

            async function fetchPendingRequests(force = false) {
                if (isLoading || (pausePolling && !force)) return;
                
                // Don't update if user is actively interacting with forms
                const activeElement = document.activeElement;
                const isFormActive = activeElement && (
                    activeElement.tagName === 'BUTTON' ||
                    activeElement.tagName === 'INPUT' ||
                    activeElement.tagName === 'SELECT' ||
                    activeElement.closest('.mobile-card') ||
                    activeElement.closest('form')
                );
                
                if (isFormActive && !force) {
                    console.log('Skipping update - user is interacting with form');
                    return;
                }
                
                isLoading = true;
                const mainLoader = document.getElementById('mainLoader');
                if (force) mainLoader.classList.add('show');
                
                try {
                    const response = await fetch('../AJAX/ajax_admin_pending_requests.php', {
                        method: 'GET',
                        headers: {
                            'Cache-Control': 'no-cache',
                            'Pragma': 'no-cache'
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const html = await response.text();
                    
                    // Store current scroll position
                    const scrollY = window.scrollY;
                    
                    // Check if content actually changed before updating
                    const container = document.getElementById('pendingRequestsContainer');
                    const currentHTML = container.innerHTML.replace(/animate-\w+/g, '').replace(/style="[^"]*"/g, '');
                    const newHTML = html.replace(/animate-\w+/g, '').replace(/style="[^"]*"/g, '');
                    
                    if (currentHTML !== newHTML) {
                        // Update container with animation
                        container.innerHTML = html;
                        
                        // Restore scroll position
                        window.scrollTo(0, scrollY);
                        
                        // Add animations to new elements
                        const cards = container.querySelectorAll('.mobile-card');
                        cards.forEach((card, index) => {
                            card.classList.add('animate-slide-up');
                            card.style.animationDelay = `${index * 0.1}s`;
                        });
                        
                        // Bind event handlers to new buttons
                        bindActionHandlers();
                    }
                    
                    // Update count badges
                    const match = html.match(/data-count="(\d+)"/);
                    const count = match ? match[1] : '0';
                    
                    document.getElementById('pendingCount').textContent = count;
                    
                    const sidebarBadge = document.getElementById('sidebarBadge');
                    if (parseInt(count) > 0) {
                        sidebarBadge.textContent = count;
                        sidebarBadge.style.display = 'inline-block';
                    } else {
                        sidebarBadge.style.display = 'none';
                    }
                    
                    lastUpdateTime = Date.now();
                    
                    if (force) {
                        showToast(`Updated ${count} pending request${count !== '1' ? 's' : ''}`, 'success', 2000);
                    }
                    
                } catch (error) {
                    console.error('Failed to fetch pending requests:', error);
                    if (force) {
                        showToast('Failed to update requests. Retrying...', 'error');
                    }
                    
                    // Show offline message if appropriate
                    if (!navigator.onLine) {
                        document.getElementById('pendingRequestsContainer').innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-wifi-slash"></i>
                                <h4>You're offline</h4>
                                <p>Check your connection and try again.</p>
                                <button class="refresh-btn" onclick="fetchPendingRequests(true)">
                                    <i class="fas fa-sync-alt"></i> Retry
                                </button>
                            </div>
                        `;
                    }
                } finally {
                    isLoading = false;
                    mainLoader.classList.remove('show');
                }
            }

            // Bind action handlers to dynamically loaded content
            function bindActionHandlers() {
                // Pause polling when user interacts with buttons
                document.querySelectorAll('.btn-accept, .mobile-btn-accept, .btn-reject, .mobile-btn-reject').forEach(btn => {
                    btn.addEventListener('mousedown', () => pausePolling = true);
                    btn.addEventListener('touchstart', () => pausePolling = true, { passive: true });
                    btn.addEventListener('focus', () => pausePolling = true);
                });

                document.querySelectorAll('.btn-accept, .mobile-btn-accept').forEach(btn => {
                    btn.addEventListener('click', handleAcceptRequest);
                });
                
                document.querySelectorAll('.btn-reject, .mobile-btn-reject').forEach(btn => {
                    btn.addEventListener('click', handleRejectRequest);
                });

                // Add hover/focus event listeners to mobile cards to pause polling
                document.querySelectorAll('.mobile-card').forEach(card => {
                    card.addEventListener('mouseenter', () => pausePolling = true);
                    card.addEventListener('mouseleave', () => {
                        setTimeout(() => pausePolling = false, 2000); // Resume after 2 seconds
                    });
                    card.addEventListener('touchstart', () => pausePolling = true, { passive: true });
                });

                // Resume polling after user interaction ends
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.mobile-card') && !e.target.closest('.btn-accept') && !e.target.closest('.btn-reject')) {
                        setTimeout(() => pausePolling = false, 1000);
                    }
                });
            }

            // Handle accept request
            async function handleAcceptRequest(e) {
                e.preventDefault();
                const button = e.currentTarget;
                const requestId = button.dataset.requestId;
                const clientName = button.dataset.clientName;
                
                const result = await Swal.fire({
                    title: 'Accept Rental Request?',
                    text: `Approve rental request from ${clientName}?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: '<i class="fas fa-check"></i> Accept',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                });

                if (result.isConfirmed) {
                    await processRequest(requestId, 'accept', button);
                }
            }

            // Handle reject request
            async function handleRejectRequest(e) {
                e.preventDefault();
                const button = e.currentTarget;
                const requestId = button.dataset.requestId;
                const clientName = button.dataset.clientName;
                
                const result = await Swal.fire({
                    title: 'Reject Rental Request?',
                    text: `Reject rental request from ${clientName}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: '<i class="fas fa-times"></i> Reject',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                });

                if (result.isConfirmed) {
                    await processRequest(requestId, 'reject', button);
                }
            }

            // Process request action
            async function processRequest(requestId, action, button) {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                button.disabled = true;

                try {
                    const response = await fetch('../AJAX/ajax_process_rental_request.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `request_id=${requestId}&action=${action}`
                    });

                    const result = await response.json();

                    if (result.success) {
                        await Swal.fire({
                            title: 'Success!',
                            text: result.message,
                            icon: 'success',
                            timer: 3000,
                            showConfirmButton: false
                        });
                        
                        // Refresh the data after successful action
                        await fetchPendingRequests(true);
                    } else {
                        throw new Error(result.message || 'Unknown error occurred');
                    }
                } catch (error) {
                    console.error('Error processing request:', error);
                    await Swal.fire({
                        title: 'Error!',
                        text: error.message,
                        icon: 'error'
                    });
                    
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            }

            // Toast notification system
            function showToast(message, type = 'info', duration = 4000) {
                const toastContainer = document.getElementById('toastContainer');
                if (!toastContainer) return;
                
                const toast = document.createElement('div');
                toast.className = `alert alert-${type} alert-dismissible fade show animate-scale-in`;
                toast.innerHTML = `
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
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

            // Smart polling with adaptive intervals
            function startPolling() {
                clearInterval(pollInterval);
                
                // Use different intervals based on activity
                const isVisible = document.visibilityState === 'visible';
                const timeSinceLastUpdate = Date.now() - lastUpdateTime;
                
                let interval = 10000; // Default 10 seconds
                
                if (!isVisible) {
                    interval = 30000; // 30 seconds when not visible
                } else if (timeSinceLastUpdate > 300000) { // 5 minutes
                    interval = 15000; // 15 seconds if no recent activity
                }
                
                pollInterval = setInterval(fetchPendingRequests, interval);
            }

            // Visibility change handler
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    fetchPendingRequests(true); // Immediate refresh when becoming visible
                }
                startPolling(); // Adjust polling interval
            });

            // Enhanced keyboard navigation
            document.addEventListener('keydown', function(e) {
                // Escape key closes mobile menu
                if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
                
                // F5 or Ctrl/Cmd + R to refresh
                if (e.key === 'F5' || ((e.ctrlKey || e.metaKey) && e.key === 'r')) {
                    e.preventDefault();
                    fetchPendingRequests(true);
                }
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

            // Initialize
            fetchPendingRequests(true);
            startPolling();

            // Service Worker for offline support (if available)
            if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
                navigator.serviceWorker.register('/sw.js').catch(console.error);
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
                
                // Observe elements as they're added
                const observeAnimatedElements = () => {
                    document.querySelectorAll('.animate-slide-up').forEach(el => {
                        el.style.animationPlayState = 'paused';
                        observer.observe(el);
                    });
                };
                
                // Initial observation and setup mutation observer for dynamic content
                observeAnimatedElements();
                
                const mutationObserver = new MutationObserver(observeAnimatedElements);
                mutationObserver.observe(document.getElementById('pendingRequestsContainer'), {
                    childList: true,
                    subtree: true
                });
            }
        });

        // SweetAlert for success/error messages (legacy support)
        <?php if (isset($_SESSION['admin_message'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= addslashes($_SESSION['admin_message']) ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['admin_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['admin_error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= addslashes($_SESSION['admin_error']) ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['admin_error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>