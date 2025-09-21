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
            padding: 0.75rem;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            transition: var(--transition);
        }
        
        .mobile-menu-toggle:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .mobile-menu-toggle i {
            font-size: 1.2rem;
        }
        
        /* Mobile Overlay */
        .mobile-overlay {
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
        
        .mobile-overlay.active {
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
            min-width: 800px; /* Ensure table doesn't get too cramped */
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
        
        /* Client Info with Hover Tooltip */
        .client-info {
            position: relative;
            cursor: help;
            transition: var(--transition);
            min-width: 150px;
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
            white-space: nowrap;
        }
        
        .btn-accept {
            background: var(--secondary);
            color: white;
            border: none;
        }
        
        .btn-accept:hover {
            background: #0da271;
            color: white;
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
            border: none;
        }
        
        .btn-reject:hover {
            background: #dc2626;
            color: white;
        }
        
        /* Action buttons container for mobile */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
        
        /* Mobile Card Layout - Alternative to table */
        .mobile-request-card {
            display: none;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            padding: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .mobile-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .mobile-card-title {
            font-weight: 600;
            color: var(--dark);
            font-size: 1.1rem;
        }
        
        .mobile-card-date {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .mobile-card-details {
            margin-bottom: 1.5rem;
        }
        
        .mobile-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .mobile-detail-row:last-child {
            border-bottom: none;
        }
        
        .mobile-detail-label {
            font-weight: 500;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .mobile-detail-value {
            font-weight: 600;
            color: var(--dark);
            text-align: right;
            max-width: 60%;
        }
        
        .mobile-card-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .mobile-card-actions .btn-action {
            flex: 1;
            min-width: 120px;
            justify-content: center;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
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
            
            .mobile-overlay {
                display: block;
            }
            
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 5rem; /* Account for mobile toggle button */
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
            
            .title-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .page-title h1 {
                font-size: 1.4rem;
            }
            
            .card-header {
                padding: 1rem;
                font-size: 1rem;
            }
            
            .card-body {
                padding: 0;
            }
            
            /* Hide table on mobile, show cards instead */
            .table-container {
                display: none;
            }
            
            .mobile-request-card {
                display: block;
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
            
            /* Mobile-friendly buttons */
            .btn-action {
                font-size: 0.85rem;
                padding: 0.6rem 1rem;
            }
            
            .mobile-card-actions .btn-action {
                font-size: 0.9rem;
                padding: 0.75rem 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem;
                padding-top: 4.5rem;
            }
            
            .mobile-request-card {
                padding: 1rem;
                margin-bottom: 0.75rem;
            }
            
            .mobile-card-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .mobile-card-title {
                font-size: 1rem;
            }
            
            .mobile-detail-value {
                max-width: 70%;
                font-size: 0.9rem;
            }
            
            .mobile-card-actions {
                flex-direction: column;
            }
            
            .mobile-card-actions .btn-action {
                width: 100%;
                min-width: unset;
            }
            
            .page-title {
                gap: 0.75rem;
            }
            
            .page-title h1 {
                font-size: 1.25rem;
            }
            
            .title-icon {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
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
        
        /* Smooth scrolling for mobile */
        @media (max-width: 768px) {
            .sidebar {
                -webkit-overflow-scrolling: touch;
            }
            
            .table-container {
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" onclick="toggleMobileMenu()"></div>
    
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
                <!-- Pending requests table will be loaded here via AJAX -->
                <noscript>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>Enable JavaScript for live updates</h4>
                </div>
                </noscript>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Mobile menu toggle function
    function toggleMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.mobile-overlay');
        const isActive = sidebar.classList.contains('active');
        
        if (isActive) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        } else {
            sidebar.classList.add('active');
            overlay.classList.add('active');
        }
    }
    
    // Close mobile menu when clicking on nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                toggleMobileMenu();
            }
        });
    });
    
    // Close mobile menu on window resize if screen becomes large
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            document.getElementById('sidebar').classList.remove('active');
            document.querySelector('.mobile-overlay').classList.remove('active');
        }
    });
    
    // --- LIVE ADMIN: AJAX Polling for Pending Rental Requests ---
    function fetchPendingRequests() {
        fetch('../AJAX/ajax_admin_pending_requests.php')
            .then(res => res.text())
            .then(html => {
                document.getElementById('pendingRequestsContainer').innerHTML = html;
                // Update count badge
                const match = html.match(/data-count="(\d+)"/);
                if (match) document.getElementById('pendingCount').textContent = match[1];
                
                // Convert table data to mobile cards on small screens
                convertToMobileCards();
            })
            .catch(error => {
                console.error('Error fetching pending requests:', error);
            });
    }
    
    // Function to convert table data to mobile-friendly cards
    function convertToMobileCards() {
        if (window.innerWidth <= 768) {
            const tableContainer = document.querySelector('.table-container');
            const table = document.querySelector('.custom-table');
            
            if (table && tableContainer) {
                const rows = table.querySelectorAll('tbody tr');
                let mobileCardsHTML = '';
                
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 4) {
                        const clientInfo = cells[0].innerHTML;
                        const unitInfo = cells[1].innerHTML;
                        const requestDate = cells[2].innerHTML;
                        const actions = cells[3].innerHTML;
                        
                        mobileCardsHTML += `
                            <div class="mobile-request-card">
                                <div class="mobile-card-header">
                                    <div class="mobile-card-title">Rental Request</div>
                                    <div class="mobile-card-date">${requestDate}</div>
                                </div>
                                <div class="mobile-card-details">
                                    <div class="mobile-detail-row">
                                        <div class="mobile-detail-label">Client</div>
                                        <div class="mobile-detail-value">${clientInfo}</div>
                                    </div>
                                    <div class="mobile-detail-row">
                                        <div class="mobile-detail-label">Unit</div>
                                        <div class="mobile-detail-value">${unitInfo}</div>
                                    </div>
                                </div>
                                <div class="mobile-card-actions">
                                    ${actions}
                                </div>
                            </div>
                        `;
                    }
                });
                
                if (mobileCardsHTML) {
                    // Hide table and show mobile cards
                    tableContainer.style.display = 'none';
                    
                    // Create or update mobile cards container
                    let mobileContainer = document.querySelector('.mobile-cards-container');
                    if (!mobileContainer) {
                        mobileContainer = document.createElement('div');
                        mobileContainer.className = 'mobile-cards-container';
                        tableContainer.parentNode.insertBefore(mobileContainer, tableContainer.nextSibling);
                    }
                    mobileContainer.innerHTML = mobileCardsHTML;
                } else {
                    // No data, show empty state
                    tableContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>No Pending Requests</h4>
                            <p class="mb-0">All rental requests have been processed.</p>
                        </div>
                    `;
                }
            }
        }
    }
    
    // Initial load and polling
    setInterval(fetchPendingRequests, 10000); // every 10s
    document.addEventListener('DOMContentLoaded', fetchPendingRequests);
    
    // Handle window resize for responsive behavior
    window.addEventListener('resize', () => {
        // Re-fetch data to handle responsive layout changes
        if (document.getElementById('pendingRequestsContainer').innerHTML) {
            convertToMobileCards();
        }
    });
    
    // SweetAlert for success/error messages
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