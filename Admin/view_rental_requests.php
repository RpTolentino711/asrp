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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            transition: var(--transition);
        }
        
        .mobile-menu-toggle:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-overlay.active {
            opacity: 1;
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
            min-width: 800px; /* Ensures horizontal scroll on small screens */
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
        
        /* Mobile Card View for Tables */
        .mobile-card-view {
            display: none;
        }
        
        .request-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .request-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .request-card-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .request-card-subtitle {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .request-card-body {
            margin-bottom: 1rem;
        }
        
        .request-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
        }
        
        .request-info-row:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 500;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .info-value {
            font-size: 0.9rem;
            color: #6b7280;
            text-align: right;
            flex: 1;
            margin-left: 1rem;
        }
        
        .request-card-actions {
            display: flex;
            gap: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #f3f4f6;
        }
        
        /* Client Info with Hover Tooltip */
        .client-info {
            position: relative;
            cursor: help;
            transition: var(--transition);
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
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 8px;
        }
        
        .client-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: var(--dark);
        }
        
        .client-info:hover .client-tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }
        
        .contact-item:last-child {
            margin-bottom: 0;
        }
        
        .contact-item i {
            width: 14px;
            color: var(--info);
        }
        
        /* Button Styling */
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            flex: 1;
            justify-content: center;
        }
        
        .btn-accept {
            background: var(--secondary);
            color: white;
        }
        
        .btn-accept:hover {
            background: #0da271;
            color: white;
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
        }
        
        .btn-reject:hover {
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
        
        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .main-content {
                padding: 1.5rem;
            }
            
            .page-title h1 {
                font-size: 1.6rem;
            }
        }
        
        @media (max-width: 992px) {
            .mobile-menu-toggle {
                display: block;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .sidebar-overlay {
                display: block;
            }
            
            .sidebar-close {
                display: block;
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 5rem; /* Account for mobile menu button */
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .page-title {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
                padding-top: 5rem;
            }
            
            .page-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .page-title h1 {
                font-size: 1.5rem;
            }
            
            .title-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .card-header {
                padding: 1rem;
                font-size: 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            /* Hide table view, show mobile card view */
            .table-container {
                display: none;
            }
            
            .mobile-card-view {
                display: block;
            }
            
            .btn-action {
                font-size: 0.85rem;
                padding: 0.6rem 0.8rem;
            }
            
            /* Mobile tooltip adjustments */
            .client-tooltip {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                max-width: 280px;
                white-space: normal;
                text-align: center;
            }
            
            .client-tooltip::after {
                display: none;
            }
            
            /* Alert adjustments */
            .alert {
                margin: 0 -1rem 1rem -1rem;
                border-radius: 0;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem;
                padding-top: 4.5rem;
            }
            
            .page-title h1 {
                font-size: 1.3rem;
            }
            
            .request-card {
                padding: 1rem;
            }
            
            .request-card-actions {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
            }
            
            .mobile-menu-toggle {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }
        }
        
        /* Dark mode support for mobile tooltips */
        @media (prefers-color-scheme: dark) {
            .client-tooltip {
                background: #374151;
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
        
        /* Loading states */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid #f3f4f6;
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

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
                <a href="view_rental_requests.php" class="nav-link active">
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
                <i class="fas fa-list-alt"></i>
                <span>Pending Requests</span>
                <span class="badge bg-primary ms-2" id="pendingCount">0</span>
            </div>
            <div class="card-body p-0" id="pendingRequestsContainer">
                <!-- Desktop table view -->
                <div class="table-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Client</th>
                                <th>Unit</th>
                                <th>Move-in Date</th>
                                <th>Monthly Rent</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Table data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile card view -->
                <div class="mobile-card-view" id="mobileRequestsContainer">
                    <!-- Mobile cards will be loaded via AJAX -->
                </div>
                
                <!-- Default loading state -->
                <div class="empty-state" id="loadingState">
                    <i class="fas fa-spinner fa-spin"></i>
                    <h4>Loading requests...</h4>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu functionality
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarClose = document.getElementById('sidebarClose');
        
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        }
        
        function closeSidebar() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        }
        
        mobileMenuToggle.addEventListener('click', toggleSidebar);
        sidebarClose.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);
        
        // Close sidebar when clicking on nav links on mobile
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    closeSidebar();
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                closeSidebar();
            }
        });
        
        // Touch gestures for mobile sidebar
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
            
            // Swipe right to open sidebar
            if (diffX > 50 && startX < 50 && window.innerWidth <= 992) {
                toggleSidebar();
                isDragging = false;
            }
            
            // Swipe left to close sidebar
            if (diffX < -50 && sidebar.classList.contains('active')) {
                closeSidebar();
                isDragging = false;
            }
        });
        
        document.addEventListener('touchend', () => {
            isDragging = false;
        });

        // --- LIVE ADMIN: AJAX Polling for Pending Rental Requests ---
        function fetchPendingRequests() {
            const container = document.getElementById('pendingRequestsContainer');
            const loadingState = document.getElementById('loadingState');
            
            // Show loading state
            if (loadingState) loadingState.style.display = 'block';
            
            fetch('../AJAX/ajax_admin_pending_requests.php')
                .then(res => res.text())
                .then(html => {
                    // Hide loading state
                    if (loadingState) loadingState.style.display = 'none';
                    
                    // Update desktop table
                    const tableContainer = container.querySelector('.table-container tbody');
                    if (tableContainer) {
                        // Extract table rows from response
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        const rows = tempDiv.querySelectorAll('tr');
                        tableContainer.innerHTML = '';
                        rows.forEach(row => tableContainer.appendChild(row));
                    }
                    
                    // Update mobile card view
                    const mobileContainer = document.getElementById('mobileRequestsContainer');
                    if (mobileContainer) {
                        // Generate mobile cards from data
                        generateMobileCards(html);
                    }
                    
                    // Update count badge
                    const match = html.match(/data-count="(\d+)"/);
                    if (match) document.getElementById('pendingCount').textContent = match[1];
                })
                .catch(error => {
                    console.error('Error fetching requests:', error);
                    if (loadingState) {
                        loadingState.innerHTML = '<i class="fas fa-exclamation-circle"></i><h4>Error loading requests</h4>';
                    }
                });
        }
        
        // Generate mobile card view from table data
        function generateMobileCards(html) {
            const mobileContainer = document.getElementById('mobileRequestsContainer');
            
            // Parse the HTML to extract table data
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const rows = tempDiv.querySelectorAll('tbody tr');
            
            if (rows.length === 0) {
                mobileContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>No pending requests</h4>
                        <p>All caught up! New requests will appear here.</p>
                    </div>
                `;
                return;
            }
            
            let cardsHTML = '';
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 7) {
                    const requestId = cells[0].textContent.trim();
                    const client = cells[1].innerHTML;
                    const unit = cells[2].textContent.trim();
                    const moveInDate = cells[3].textContent.trim();
                    const rent = cells[4].textContent.trim();
                    const status = cells[5].innerHTML;
                    const actions = cells[6].innerHTML;
                    
                    cardsHTML += `
                        <div class="request-card">
                            <div class="request-card-header">
                                <div>
                                    <div class="request-card-title">Request #${requestId}</div>
                                    <div class="request-card-subtitle">${unit}</div>
                                </div>
                                ${status}
                            </div>
                            <div class="request-card-body">
                                <div class="request-info-row">
                                    <span class="info-label">
                                        <i class="fas fa-user"></i> Client
                                    </span>
                                    <span class="info-value">${client}</span>
                                </div>
                                <div class="request-info-row">
                                    <span class="info-label">
                                        <i class="fas fa-calendar"></i> Move-in Date
                                    </span>
                                    <span class="info-value">${moveInDate}</span>
                                </div>
                                <div class="request-info-row">
                                    <span class="info-label">
                                        <i class="fas fa-dollar-sign"></i> Monthly Rent
                                    </span>
                                    <span class="info-value">${rent}</span>
                                </div>
                            </div>
                            <div class="request-card-actions">
                                ${actions}
                            </div>
                        </div>
                    `;
                }
            });
            
            mobileContainer.innerHTML = cardsHTML;
        }
        
        // Handle action buttons with loading states
        function handleActionClick(button, action) {
            const card = button.closest('.request-card') || button.closest('tr');
            card.classList.add('loading');
            
            // Re-enable after timeout (in case of network issues)
            setTimeout(() => {
                card.classList.remove('loading');
            }, 10000);
        }
        
        // Attach event listeners to action buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-accept') || e.target.classList.contains('btn-reject')) {
                handleActionClick(e.target, e.target.classList.contains('btn-accept') ? 'accept' : 'reject');
            }
        });
        
        // Initialize polling
        setInterval(fetchPendingRequests, 10000); // every 10s
        document.addEventListener('DOMContentLoaded', fetchPendingRequests);
        
        // Pull to refresh functionality for mobile
        let startY = 0;
        let pullDistance = 0;
        let isPulling = false;
        const pullThreshold = 100;
        
        document.addEventListener('touchstart', (e) => {
            if (window.scrollY === 0) {
                startY = e.touches[0].clientY;
                isPulling = true;
            }
        });
        
        document.addEventListener('touchmove', (e) => {
            if (!isPulling) return;
            
            pullDistance = e.touches[0].clientY - startY;
            
            if (pullDistance > 0 && window.scrollY === 0) {
                e.preventDefault();
                
                // Visual feedback for pull to refresh
                if (pullDistance > pullThreshold) {
                    document.body.style.transform = `translateY(${Math.min(pullDistance * 0.5, 50)}px)`;
                    document.body.style.opacity = '0.8';
                }
            }
        });
        
        document.addEventListener('touchend', () => {
            if (isPulling && pullDistance > pullThreshold) {
                // Trigger refresh
                fetchPendingRequests();
                
                // Show refresh feedback
                const header = document.querySelector('.dashboard-header');
                const refreshIndicator = document.createElement('div');
                refreshIndicator.className = 'alert alert-info';
                refreshIndicator.innerHTML = '<i class="fas fa-sync-alt fa-spin me-2"></i>Refreshing requests...';
                header.appendChild(refreshIndicator);
                
                setTimeout(() => {
                    refreshIndicator.remove();
                }, 2000);
            }
            
            // Reset visual feedback
            document.body.style.transform = '';
            document.body.style.opacity = '';
            isPulling = false;
            pullDistance = 0;
        });
        
        // Enhanced error handling for network issues
        window.addEventListener('online', () => {
            fetchPendingRequests();
            showNotification('Connection restored', 'success');
        });
        
        window.addEventListener('offline', () => {
            showNotification('No internet connection', 'warning');
        });
        
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 2000;
                max-width: 300px;
                animation: slideInRight 0.3s ease;
            `;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : type === 'warning' ? 'exclamation-triangle' : 'info'}-circle me-2"></i>
                ${message}
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        // Add slide animations for notifications
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // SweetAlert for success/error messages
        <?php if (isset($_SESSION['admin_message'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= addslashes($_SESSION['admin_message']) ?>',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            <?php unset($_SESSION['admin_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['admin_error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= addslashes($_SESSION['admin_error']) ?>',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            <?php unset($_SESSION['admin_error']); ?>
        <?php endif; ?>
        
        // Optimize for mobile performance
        if (window.innerWidth <= 768) {
            // Reduce polling frequency on mobile to save battery
            clearInterval();
            setInterval(fetchPendingRequests, 15000); // every 15s on mobile
            
            // Add haptic feedback for iOS devices
            function hapticFeedback(type = 'light') {
                if (window.navigator && window.navigator.vibrate) {
                    window.navigator.vibrate(type === 'light' ? 10 : type === 'medium' ? 20 : 50);
                }
            }
            
            // Add haptic feedback to buttons
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('btn-action')) {
                    hapticFeedback('medium');
                }
            });
        }
        
        // Service worker for offline functionality (optional)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(() => {
                // Service worker registration failed - not critical
            });
        }
        
        // Keyboard shortcuts for desktop users
        document.addEventListener('keydown', (e) => {
            if (e.altKey && e.key === 'm') {
                e.preventDefault();
                toggleSidebar();
            }
            if (e.key === 'Escape') {
                closeSidebar();
            }
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                fetchPendingRequests();
            }
        });
    </script>
</body>
</html>