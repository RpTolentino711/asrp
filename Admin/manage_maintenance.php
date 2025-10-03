<?php
session_start();
require '../database/database.php';

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// Get filter from URL or default to 'active'
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'active';

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

// Get requests based on filter
switch($filter) {
    case 'in-progress':
        $requests = $db->getInProgressMaintenanceRequests();
        $filter_title = "In Progress";
        $filter_count = count($requests);
        break;
    case 'completed':
        $requests = $db->getCompletedMaintenanceRequests();
        $filter_title = "Completed";
        $filter_count = count($requests);
        break;
    case 'submitted':
        $requests = $db->getSubmittedMaintenanceRequests();
        $filter_title = "Submitted";
        $filter_count = count($requests);
        break;
    default: // active (all non-completed)
        $requests = $db->getActiveMaintenanceRequests();
        $filter_title = "Active";
        $filter_count = count($requests);
        break;
}

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
        /* Add to existing CSS */
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1rem;
        }
        
        .filter-tab {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: var(--border-radius);
            background: #f8fafc;
            color: #6b7280;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .filter-tab:hover {
            background: #e5e7eb;
            color: #374151;
            transform: translateY(-1px);
        }
        
        .filter-tab.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }
        
        .filter-tab .badge {
            background: rgba(255, 255, 255, 0.2);
            color: inherit;
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        
        .filter-tab:not(.active) .badge {
            background: #e5e7eb;
            color: #6b7280;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        .stat-card.active {
            border-left-color: var(--primary);
        }
        
        .stat-card.in-progress {
            border-left-color: var(--warning);
        }
        
        .stat-card.completed {
            border-left-color: var(--secondary);
        }
        
        .stat-card.submitted {
            border-left-color: var(--info);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .stat-card.active .stat-number { color: var(--primary); }
        .stat-card.in-progress .stat-number { color: var(--warning); }
        .stat-card.completed .stat-number { color: var(--secondary); }
        .stat-card.submitted .stat-number { color: var(--info); }

        /* Add to existing mobile styles */
        @media (max-width: 768px) {
            .filter-tabs {
                gap: 0.25rem;
            }
            
            .filter-tab {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
                flex: 1;
                min-width: 0;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .stat-card {
                padding: 1.25rem;
            }
            
            .stat-number {
                font-size: 1.75rem;
            }
        }

        /* Rest of your existing CSS remains the same */
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

        /* ... rest of your existing CSS ... */
    </style>
</head>
<body>
    <!-- Mobile overlay and header (unchanged) -->
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

    <!-- Sidebar (unchanged) -->
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
        
        <!-- Statistics Cards -->
        <div class="stats-grid animate-fade-in">
            <?php
            $active_count = count($db->getActiveMaintenanceRequests());
            $in_progress_count = count($db->getInProgressMaintenanceRequests());
            $completed_count = count($db->getCompletedMaintenanceRequests());
            $submitted_count = count($db->getSubmittedMaintenanceRequests());
            ?>
            <a href="?filter=active" class="stat-card active text-decoration-none">
                <div class="stat-number"><?= $active_count ?></div>
                <div class="stat-label">Active Requests</div>
            </a>
            <a href="?filter=in-progress" class="stat-card in-progress text-decoration-none">
                <div class="stat-number"><?= $in_progress_count ?></div>
                <div class="stat-label">In Progress</div>
            </a>
            <a href="?filter=completed" class="stat-card completed text-decoration-none">
                <div class="stat-number"><?= $completed_count ?></div>
                <div class="stat-label">Completed</div>
            </a>
            <a href="?filter=submitted" class="stat-card submitted text-decoration-none">
                <div class="stat-number"><?= $submitted_count ?></div>
                <div class="stat-label">Submitted</div>
            </a>
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=active" class="filter-tab <?= $filter === 'active' ? 'active' : '' ?>">
                <i class="fas fa-list"></i>
                Active
                <span class="badge"><?= $active_count ?></span>
            </a>
            <a href="?filter=in-progress" class="filter-tab <?= $filter === 'in-progress' ? 'active' : '' ?>">
                <i class="fas fa-spinner"></i>
                In Progress
                <span class="badge"><?= $in_progress_count ?></span>
            </a>
            <a href="?filter=completed" class="filter-tab <?= $filter === 'completed' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i>
                Completed
                <span class="badge"><?= $completed_count ?></span>
            </a>
            <a href="?filter=submitted" class="filter-tab <?= $filter === 'submitted' ? 'active' : '' ?>">
                <i class="fas fa-clock"></i>
                Submitted
                <span class="badge"><?= $submitted_count ?></span>
            </a>
        </div>
        
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-list-alt"></i>
                <span><?= $filter_title ?> Maintenance Requests</span>
                <span class="badge bg-primary ms-2"><?= $filter_count ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($requests)): ?>
                    <!-- Desktop Table -->
                    <div class="table-desktop">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Unit</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <?php if ($filter !== 'completed'): ?>
                                    <th>Assign Handyman</th>
                                    <th>Action</th>
                                    <?php else: ?>
                                    <th>Completed By</th>
                                    <th>Completion Date</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($requests as $row): ?>
                                <tr>
                                    <?php if ($filter !== 'completed'): ?>
                                    <form method="post">
                                        <input type="hidden" name="request_id" value="<?= (int)$row['Request_ID'] ?>">
                                        <td><span class="fw-medium">#<?= $row['Request_ID'] ?></span></td>
                                        <td><div class="fw-medium"><?= htmlspecialchars($row['Client_fn'] . " " . $row['Client_ln']) ?></div></td>
                                        <td><?= htmlspecialchars($row['SpaceName']) ?></td>
                                        <td><div class="text-muted"><?= htmlspecialchars($row['RequestDate']) ?></div></td>
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
                                    <?php else: ?>
                                    <td><span class="fw-medium">#<?= $row['Request_ID'] ?></span></td>
                                    <td><div class="fw-medium"><?= htmlspecialchars($row['Client_fn'] . " " . $row['Client_ln']) ?></div></td>
                                    <td><?= htmlspecialchars($row['SpaceName']) ?></td>
                                    <td><div class="text-muted"><?= htmlspecialchars($row['RequestDate']) ?></div></td>
                                    <td>
                                        <span class="badge-status badge-completed">Completed</span>
                                    </td>
                                    <td>
                                        <?php if ($row['Handyman_fn']): ?>
                                            <?= htmlspecialchars($row['Handyman_fn'] . ' ' . $row['Handyman_ln']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $completion_date = $db->getRequestCompletionDate($row['Request_ID']);
                                        echo $completion_date ? htmlspecialchars($completion_date) : '<span class="text-muted">N/A</span>';
                                        ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card Layout -->
                    <div class="table-mobile">
                        <?php foreach($requests as $index => $row): 
                            $statusClass = 'status-' . strtolower(str_replace(' ', '', $row['Status']));
                        ?>
                        <div class="mobile-card <?= $statusClass ?> animate-slide-up" style="animation-delay: <?= $index * 0.05 ?>s;">
                            <?php if ($filter !== 'completed'): ?>
                            <div class="loading-overlay">
                                <div class="spinner"></div>
                            </div>
                            
                            <form method="POST" action="" data-request-id="<?= $row['Request_ID'] ?>">
                                <input type="hidden" name="request_id" value="<?= (int)$row['Request_ID'] ?>">
                            <?php endif; ?>
                                
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

                                <?php if ($filter === 'completed'): ?>
                                <div class="mobile-card-detail">
                                    <span class="label"><i class="fas fa-user-tie me-1"></i>Completed By:</span>
                                    <span class="value">
                                        <?php if ($row['Handyman_fn']): ?>
                                            <?= htmlspecialchars($row['Handyman_fn'] . ' ' . $row['Handyman_ln']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php 
                                $completion_date = $db->getRequestCompletionDate($row['Request_ID']);
                                if ($completion_date): ?>
                                <div class="mobile-card-detail">
                                    <span class="label"><i class="fas fa-check-circle me-1"></i>Completed On:</span>
                                    <span class="value"><?= htmlspecialchars($completion_date) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($row['Description'])): ?>
                                <div class="mobile-card-detail">
                                    <span class="label"><i class="fas fa-info-circle me-1"></i>Issue:</span>
                                    <span class="value"><?= htmlspecialchars(substr($row['Description'], 0, 100)) ?><?= strlen($row['Description']) > 100 ? '...' : '' ?></span>
                                </div>
                                <?php endif; ?>

                                <?php if ($row['Handyman_fn'] && $filter !== 'completed'): ?>
                                <div class="current-handyman">
                                    <i class="fas fa-user-tie"></i>
                                    Currently assigned: <?= htmlspecialchars($row['Handyman_fn'] . ' ' . $row['Handyman_ln']) ?>
                                </div>
                                <?php endif; ?>

                                <?php if ($filter !== 'completed'): ?>
                                <div class="mobile-form">
                                    <div class="mobile-form-group">
                                        <label for="status_<?= $row['Request_ID'] ?>">
                                            <i class="fas fa-tasks me-1"></i>Update Status
                                        </label>
                                        <select name="status" id="status_<?= $row['Request_ID'] ?>">
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
                                    
                                    <button type="submit" name="update_request" class="mobile-save-btn">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                                </form>
                                <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state animate-fade-in">
                        <i class="fas fa-tools"></i>
                        <h4>No <?= strtolower($filter_title) ?> Maintenance Requests</h4>
                        <p>
                            <?php if ($filter === 'completed'): ?>
                                No maintenance requests have been completed yet.
                            <?php elseif ($filter === 'in-progress'): ?>
                                No maintenance requests are currently in progress.
                            <?php elseif ($filter === 'submitted'): ?>
                                No maintenance requests are currently submitted.
                            <?php else: ?>
                                All maintenance requests have been processed or completed.
                            <?php endif; ?>
                        </p>
                        <a href="?filter=active" class="btn btn-primary mt-3">
                            <i class="fas fa-list me-2"></i>View Active Requests
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Your existing JavaScript code remains the same
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

            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
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

            document.addEventListener('change', function(e) {
                if (e.target.name === 'status' && e.target.value === 'Completed') {
                    const requestId = e.target.closest('form').dataset.requestId;
                    if (!confirm('Mark maintenance request #' + requestId + ' as completed?\n\nThis will finalize the request.')) {
                        const options = e.target.options;
                        for (let i = 0; i < options.length; i++) {
                            if (options[i].defaultSelected) {
                                e.target.selectedIndex = i;
                                break;
                            }
                        }
                    }
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
            });
        });
    </script>
</body>
</html>