<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../database/database.php';
session_start();

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// MARK ALL PENDING REQUESTS AS SEEN WHEN ADMIN VIEWS THE PAGE
$db->executeStatement(
    "UPDATE rentalrequest SET admin_seen = 1 WHERE Status = 'Pending' AND admin_seen = 0"
);

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// Handle rental request acceptance
if (isset($_POST['accept_request'])) {
    // LOAD EMAIL FUNCTION ONLY WHEN NEEDED
    require '../email_functions.php';
    
    $requestId = intval($_POST['request_id']);
    
    // Get request details before accepting
    $requestDetails = $db->getRentalRequestById($requestId);
    
    if ($requestDetails) {
        // Accept the request
        $result = $db->acceptRentalRequest($requestId);
        
        if ($result) {
            // Try to send welcome email
            try {
                $emailSent = sendRentalAcceptanceEmail(
                    $requestDetails['Client_Email'],
                    $requestDetails['Client_fn'] ?: 'User',
                    $requestDetails['SpaceName'] ?: 'Your Unit',
                    $requestDetails['StartDate'],
                    $requestDetails['EndDate'],
                    $requestDetails['Price'] ?: 0
                );
                
                if ($emailSent) {
                    $_SESSION['admin_message'] = 'Rental request accepted successfully! Welcome email sent to client.';
                    error_log("Welcome email sent to: " . $requestDetails['Client_Email'] . " for request ID: " . $requestId);
                } else {
                    $_SESSION['admin_message'] = 'Rental request accepted successfully! (Email notification failed)';
                    error_log("Failed to send welcome email to: " . $requestDetails['Client_Email'] . " for request ID: " . $requestId);
                }
            } catch (Exception $e) {
                $_SESSION['admin_message'] = 'Rental request accepted successfully! (Email error: ' . $e->getMessage() . ')';
                error_log("Error sending welcome email: " . $e->getMessage());
            }
        } else {
            $_SESSION['admin_error'] = 'Failed to accept rental request. Please try again.';
        }
    } else {
        $_SESSION['admin_error'] = 'Rental request not found.';
    }
    
    header('Location: view_rental_requests.php');
    exit();
}

// Handle rental request rejection
if (isset($_POST['reject_request'])) {
    $requestId = intval($_POST['request_id']);
    $result = $db->rejectRentalRequest($requestId);
    
    if ($result) {
        $_SESSION['admin_message'] = 'Rental request rejected successfully.';
    } else {
        $_SESSION['admin_error'] = 'Failed to reject rental request. Please try again.';
    }
    
    header('Location: view_rental_requests.php');
    exit();
}
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
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: var(--transition);
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
            min-width: 250px;
            pointer-events: none;
            bottom: 100%;
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
        
        .custom-table tr:first-child .client-tooltip {
            bottom: auto;
            top: 100%;
            margin-top: 8px;
            margin-bottom: 0;
        }
        
        .custom-table tr:first-child .client-tooltip::after {
            top: auto;
            bottom: 100%;
            border-top-color: transparent;
            border-bottom-color: var(--dark);
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
        
        .request-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            color: #d1d5db;
            font-size: 0.8rem;
        }
        
        .request-date i {
            color: var(--warning);
        }
        
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
            white-space: nowrap;
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
        
        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
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
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                min-width: 120px;
            }
        }
        
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
            
            .btn-action {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
            
            .client-tooltip {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                max-width: 280px;
                white-space: normal;
                text-align: center;
                margin: 0;
            }
            
            .client-tooltip::after {
                display: none;
            }
            
            .custom-table tr:nth-child(-n+2) .client-tooltip {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        #pendingRequestsContainer {
            min-height: 200px;
            position: relative;
        }
    </style>
</head>
<body>
    <div class="mobile-overlay" id="mobileOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999;"></div>

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

    <div class="main-content">
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
        
        <div class="alert alert-info animate-fade-in">
            <i class="fas fa-envelope me-2"></i>
            When you accept a rental request, the client will automatically receive a beautifully styled welcome email with their rental details and benefits.
        </div>
        
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-list-alt"></i>
                <span>Pending Requests</span>
                <span class="badge bg-primary ms-2" id="pendingCount">0</span>
            </div>
            <div class="card-body p-0" id="pendingRequestsContainer">
                <div class="empty-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <h4>Loading requests...</h4>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let isTabActive = true;
    let isLoading = false;

    // Stop polling when tab is not visible
    document.addEventListener('visibilitychange', function() {
        isTabActive = !document.hidden;
        if (isTabActive && !isLoading) {
            fetchPendingRequests(); // Load immediately when tab becomes active
        }
    });

    function fetchPendingRequests() {
        if (isLoading) return;
        
        isLoading = true;
        
        // Add cache-busting parameter only when needed
        const url = '../AJAX/ajax_admin_pending_requests.php?t=' + (isTabActive ? Date.now() : 'cache');
        
        fetch(url)
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.text();
            })
            .then(html => {
                document.getElementById('pendingRequestsContainer').innerHTML = html;
                const container = document.getElementById('pendingRequestsContainer');
                const countElement = container.querySelector('[data-count]');
                if (countElement) {
                    document.getElementById('pendingCount').textContent = countElement.getAttribute('data-count');
                }
                isLoading = false;
            })
            .catch(err => {
                console.error('Error fetching requests:', err);
                document.getElementById('pendingRequestsContainer').innerHTML = 
                    '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h4>Error loading requests</h4></div>';
                isLoading = false;
            });
    }

    function confirmAccept(requestId, clientName, spaceName) {
        Swal.fire({
            title: 'Accept Rental Request?',
            html: `
                <p>You are about to accept the rental request from:</p>
                <p class="fw-bold">${clientName}</p>
                <p>For property: <span class="fw-bold">${spaceName}</span></p>
                <p class="text-muted mt-3">
                    <i class="fas fa-envelope me-2"></i>
                    A welcome email will be automatically sent to the client.
                </p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Accept & Send Welcome Email',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create and submit form dynamically
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'view_rental_requests.php';
                
                const requestIdInput = document.createElement('input');
                requestIdInput.type = 'hidden';
                requestIdInput.name = 'request_id';
                requestIdInput.value = requestId;
                
                const acceptInput = document.createElement('input');
                acceptInput.type = 'hidden';
                acceptInput.name = 'accept_request';
                acceptInput.value = '1';
                
                form.appendChild(requestIdInput);
                form.appendChild(acceptInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    
    function confirmReject(requestId, clientName, spaceName) {
        Swal.fire({
            title: 'Reject Rental Request?',
            html: `
                <p>You are about to reject the rental request from:</p>
                <p class="fw-bold">${clientName}</p>
                <p>For property: <span class="fw-bold">${spaceName}</span></p>
                <p class="text-danger mt-3">This action cannot be undone.</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-times me-2"></i>Reject Request',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create and submit form dynamically
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'view_rental_requests.php';
                
                const requestIdInput = document.createElement('input');
                requestIdInput.type = 'hidden';
                requestIdInput.name = 'request_id';
                requestIdInput.value = requestId;
                
                const rejectInput = document.createElement('input');
                rejectInput.type = 'hidden';
                rejectInput.name = 'reject_request';
                rejectInput.value = '1';
                
                form.appendChild(requestIdInput);
                form.appendChild(rejectInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    
    // Load immediately, then every 30 seconds (instead of 10)
    document.addEventListener('DOMContentLoaded', function() {
        fetchPendingRequests();
        setInterval(function() {
            if (isTabActive && !isLoading) {
                fetchPendingRequests();
            }
        }, 30000); // 30 seconds instead of 10
    });
    
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
    </script>
</body>
</html>