<?php
require '../database/database.php';
session_start();

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

$msg = "";

// --- Pagination Configuration ---
$clients_per_page = 10; // Number of clients per page
$units_per_page = 10;   // Number of units per page

// Get current page for clients
$client_page = isset($_GET['client_page']) ? max(1, intval($_GET['client_page'])) : 1;
$client_offset = ($client_page - 1) * $clients_per_page;

// Get current page for units
$unit_page = isset($_GET['unit_page']) ? max(1, intval($_GET['unit_page'])) : 1;
$unit_offset = ($unit_page - 1) * $units_per_page;

// --- Fetch Data with Pagination ---
$all_clients = $db->getAllClientsWithOrWithoutUnit();
$all_units = $db->getAllUnitsWithRenterInfo();

// Apply unit filter to clients
$unit_filter = isset($_GET['unit_filter']) ? $_GET['unit_filter'] : 'all';
$filtered_clients = array_filter($all_clients, function($c) use ($unit_filter) {
    $has_unit = !empty($c['SpaceName']);
    if ($unit_filter === 'with') return $has_unit;
    if ($unit_filter === 'without') return !$has_unit;
    return true;
});

// Paginate clients
$total_clients = count($filtered_clients);
$total_client_pages = ceil($total_clients / $clients_per_page);
$paginated_clients = array_slice($filtered_clients, $client_offset, $clients_per_page);

// Paginate units
$total_units = count($all_units);
$total_unit_pages = ceil($total_units / $units_per_page);
$paginated_units = array_slice($all_units, $unit_offset, $units_per_page);

// --- Handle POST Actions ---

// Rename Space Unit
if (isset($_POST['rename_unit']) && isset($_POST['space_id'], $_POST['new_name'])) {
    $sid = intval($_POST['space_id']);
    $new_name = trim($_POST['new_name']);
    if ($new_name === '') {
        $msg = '<div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Unit name cannot be empty.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else if ($db->renameUnit($sid, $new_name)) {
        $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Unit name updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error: Could not update unit name.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

// Nuke (PERMA DELETE) - This will delete the client, their records, and set any rented unit as available again
if (isset($_POST['nuke_client']) && isset($_POST['client_id'])) {
    $cid = intval($_POST['client_id']);
    // Find ALL units assigned to this client (supporting multi-unit clients)
    $client_unit_ids = [];
    foreach ($all_clients as $cl) {
        if ($cl['Client_ID'] == $cid && !empty($cl['Space_ID'])) {
            $client_unit_ids[] = $cl['Space_ID'];
        }
    }
    // Free all spaces BEFORE deleting the client
    foreach ($client_unit_ids as $space_id) {
        $db->setUnitAvailable($space_id);
    }
    // Now nuke the client (permanent delete)
    if ($db->hardDeleteClient($cid)) {
        $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Client and all associated records PERMANENTLY deleted. Any rented space is now available.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error: Could not nuke client (delete client and free space).
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

if (isset($_POST['delete_client']) && isset($_POST['client_id'])) {
    $cid = intval($_POST['client_id']);
    $client_has_unit = false;
    foreach ($all_clients as $cl) {
        if ($cl['Client_ID'] == $cid && !empty($cl['SpaceName'])) {
            $client_has_unit = true;
            break;
        }
    }
    if ($client_has_unit) {
        $msg = '<div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Cannot set inactive: Client still has a rented unit assigned.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        if ($db->updateClientStatus($cid, 'inactive')) {
            $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Client set as inactive!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        } else {
            $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error: Could not set client as inactive.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
}

if (isset($_POST['activate_client']) && isset($_POST['client_id'])) {
    $cid = intval($_POST['client_id']);
    if ($db->updateClientStatus($cid, 'active')) {
        $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Client reactivated!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error: Could not reactivate client.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

if (isset($_POST['update_price']) && isset($_POST['space_id'], $_POST['new_price'])) {
    $sid = intval($_POST['space_id']);
    $price = floatval($_POST['new_price']);
    if ($db->updateUnit_price($sid, $price)) {
        $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Unit price updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error: Could not update unit price.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

// REGULAR UNIT DELETION - Check if unit has renter before deleting
if (isset($_POST['delete_unit']) && isset($_POST['space_id'])) {
    $sid = intval($_POST['space_id']);
    
    // Debug: Log what we're trying to delete
    error_log("Attempting to delete unit ID: $sid");
    
    // Check if unit is rented using a more comprehensive check
    $isRented = $db->isUnitRented($sid);
    error_log("Unit $sid - isRented check result: " . ($isRented ? 'true' : 'false'));
    
    // Additional debug: Check active rentals directly
    $activeRenters = $db->runQuery(
        "SELECT Client_ID FROM clientspace WHERE Space_ID = ? AND active = 1",
        [$sid],
        true
    );
    error_log("Unit $sid - Active renters found: " . count($activeRenters));
    
    if ($isRented) {
        $msg = '<div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Cannot delete unit: This unit currently has a renter assigned. (Active renters: ' . count($activeRenters) . ')
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        // Attempt deletion
        $deleteResult = $db->hardDeleteUnit($sid);
        error_log("Unit $sid - hardDeleteUnit result: " . ($deleteResult ? 'success' : 'failed'));
        
        if ($deleteResult) {
            $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Unit deleted successfully! All associated records have been removed.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        } else {
            $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error: Could not delete unit. Check error logs for details.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
    
    // Refresh data after deletion attempt
    $all_clients = $db->getAllClientsWithOrWithoutUnit();
    $all_units = $db->getAllUnitsWithRenterInfo();
}

// FORCE DELETE UNIT - Ignores rental status and deletes everything
if (isset($_POST['force_delete_unit']) && isset($_POST['space_id'])) {
    $sid = intval($_POST['space_id']);
    
    error_log("Force deleting unit ID: $sid");
    
    if ($db->hardDeleteUnit($sid)) {
        $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Unit force deleted successfully! All associated records and renter relationships have been permanently removed.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
        
        // Refresh data after deletion
        $all_clients = $db->getAllClientsWithOrWithoutUnit();
        $all_units = $db->getAllUnitsWithRenterInfo();
    } else {
        $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error: Could not force delete unit. Check error logs for details.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

if (isset($_POST['hard_delete_client']) && isset($_POST['client_id'])) {
    $cid = intval($_POST['client_id']);
    $client_has_unit = false;
    foreach ($all_clients as $cl) {
        if ($cl['Client_ID'] == $cid && !empty($cl['SpaceName'])) {
            $client_has_unit = true;
            break;
        }
    }
    if ($client_has_unit) {
        $msg = '<div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Cannot hard delete: Client has a rented unit assigned.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        if ($db->hardDeleteClient($cid)) {
            $msg = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Client and all associated records have been permanently deleted.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        } else {
            $msg = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error: Could not delete client and their records.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
}

// Refresh paginated data after POST actions
if ($_POST) {
    $filtered_clients = array_filter($all_clients, function($c) use ($unit_filter) {
        $has_unit = !empty($c['SpaceName']);
        if ($unit_filter === 'with') return $has_unit;
        if ($unit_filter === 'without') return !$has_unit;
        return true;
    });
    $total_clients = count($filtered_clients);
    $total_client_pages = ceil($total_clients / $clients_per_page);
    $paginated_clients = array_slice($filtered_clients, $client_offset, $clients_per_page);
    
    $total_units = count($all_units);
    $total_unit_pages = ceil($total_units / $units_per_page);
    $paginated_units = array_slice($all_units, $unit_offset, $units_per_page);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, minimum-scale=1.0, maximum-scale=5.0">
    <title>User & Unit Management | ASRT Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ... (keep all existing CSS styles) ... */

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #e5e7eb;
        }

        .pagination-info {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .pagination {
            margin: 0;
        }

        .page-link {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            color: #374151;
            font-weight: 500;
            border-radius: 6px;
            margin: 0 0.15rem;
        }

        .page-link:hover {
            background-color: #f3f4f6;
            border-color: #9ca3af;
        }

        .page-item.active .page-link {
            background-color: #6366f1;
            border-color: #6366f1;
            color: white;
        }

        .page-item.disabled .page-link {
            color: #9ca3af;
            background-color: #f9fafb;
            border-color: #d1d5db;
        }

        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }

            .page-link {
                padding: 0.4rem 0.6rem;
                font-size: 0.875rem;
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
                <a href="manage_user.php" class="nav-link active">
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
                    <i class="fas fa-users-cog"></i>
                </div>
                <div>
                    <h1>User & Unit Management</h1>
                    <p class="text-muted mb-0">Manage clients, units, and their relationships</p>
                </div>
            </div>
        </div>
        
        <?= $msg ?>
        
        <!-- Filter Section -->
        <div class="filter-container">
            <form method="get" class="d-flex align-items-center gap-2 flex-wrap" id="clientUnitFilterForm">
                <input type="hidden" name="client_page" value="1">
                <input type="hidden" name="unit_page" value="1">
                <label for="clientUnitFilter" class="form-label mb-0">Filter:</label>
                <select name="unit_filter" id="clientUnitFilter" class="form-select form-select-sm w-auto">
                    <option value="all"<?= (!isset($_GET['unit_filter']) || $_GET['unit_filter']==='all') ? ' selected' : '' ?>>All Clients</option>
                    <option value="with"<?= (isset($_GET['unit_filter']) && $_GET['unit_filter']==='with') ? ' selected' : '' ?>>With Unit</option>
                    <option value="without"<?= (isset($_GET['unit_filter']) && $_GET['unit_filter']==='without') ? ' selected' : '' ?>>Without Unit</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            </form>
        </div>

        <!-- Clients Card -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-users"></i>
                <span>Clients</span>
                <span class="badge bg-primary ms-2"><?= $total_clients ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($paginated_clients)): ?>
                    <!-- Desktop Table -->
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Username</th>
                                    <th>Status</th>
                                    <th>Rented Unit</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paginated_clients as $c): ?>
                                <?php $client_has_unit = !empty($c['SpaceName']); ?>
                                <tr>
                                    <td>
                                        <span class="fw-medium">#<?= htmlspecialchars($c['Client_ID']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?= htmlspecialchars($c['Client_fn'].' '.$c['Client_ln']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($c['Client_Email']) ?></td>
                                    <td><?= htmlspecialchars($c['C_username']) ?></td>
                                    <td>
                                        <?php if (strtolower($c['Status']) === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $client_has_unit ? htmlspecialchars($c['SpaceName']) : '<span class="text-muted">None</span>' ?>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="client_id" value="<?= $c['Client_ID'] ?>">
                                                <?php if (strtolower($c['Status']) === 'active'): ?>
                                                    <div class="tooltip-wrapper">
                                                        <button type="submit" name="delete_client" class="btn-action btn-deactivate"
                                                            <?= $client_has_unit ? 'disabled' : '' ?>>
                                                            <i class="fas fa-user-slash"></i>
                                                        </button>
                                                        <span class="tooltip-text"><?= $client_has_unit ? 'Cannot deactivate: has rented unit' : 'Deactivate' ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="tooltip-wrapper">
                                                        <button type="submit" name="activate_client" class="btn-action btn-activate">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                        <span class="tooltip-text">Reactivate</span>
                                                    </div>
                                                <?php endif; ?>
                                            </form>
                                            
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="client_id" value="<?= $c['Client_ID'] ?>">
                                                <div class="tooltip-wrapper">
                                                    <button type="submit" name="hard_delete_client" class="btn-action btn-delete"
                                                        <?= $client_has_unit ? 'disabled' : '' ?>>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <span class="tooltip-text"><?= $client_has_unit ? 'Cannot delete: has rented unit' : 'Delete' ?></span>
                                                </div>
                                            </form>
                                            
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="client_id" value="<?= $c['Client_ID'] ?>">
                                                <div class="tooltip-wrapper">
                                                    <button type="submit" name="nuke_client" class="btn-action btn-nuke">
                                                        <i class="fas fa-bomb"></i>
                                                    </button>
                                                    <span class="tooltip-text">Nuke (Delete + Free Unit)</span>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card Layout -->
                    <div class="table-mobile">
                        <?php foreach ($paginated_clients as $c): ?>
                        <?php $client_has_unit = !empty($c['SpaceName']); ?>
                            <div class="mobile-card">
                                <div class="mobile-card-header">
                                    <div>
                                        <strong><?= htmlspecialchars($c['Client_fn'].' '.$c['Client_ln']) ?></strong>
                                        <span class="badge bg-primary ms-2">#<?= $c['Client_ID'] ?></span>
                                    </div>
                                    <div>
                                        <?php if (strtolower($c['Status']) === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Email:</span>
                                    <span class="value"><?= htmlspecialchars($c['Client_Email']) ?></span>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Username:</span>
                                    <span class="value"><?= htmlspecialchars($c['C_username']) ?></span>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Rented Unit:</span>
                                    <span class="value"><?= $client_has_unit ? htmlspecialchars($c['SpaceName']) : '<span class="text-muted">None</span>' ?></span>
                                </div>

                                <div class="mobile-actions">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="client_id" value="<?= $c['Client_ID'] ?>">
                                        <?php if (strtolower($c['Status']) === 'active'): ?>
                                            <button type="submit" name="delete_client" class="btn-action btn-deactivate"
                                                <?= $client_has_unit ? 'disabled title="Cannot deactivate: has rented unit"' : 'title="Deactivate"' ?>>
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="activate_client" class="btn-action btn-activate" title="Reactivate">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="client_id" value="<?= $c['Client_ID'] ?>">
                                        <button type="submit" name="hard_delete_client" class="btn-action btn-delete"
                                            <?= $client_has_unit ? 'disabled title="Cannot delete: has rented unit"' : 'title="Delete"' ?>>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="client_id" value="<?= $c['Client_ID'] ?>">
                                        <button type="submit" name="nuke_client" class="btn-action btn-nuke" title="Nuke (Delete + Free Unit)">
                                            <i class="fas fa-bomb"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Client Pagination -->
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?= count($paginated_clients) ?> of <?= $total_clients ?> clients
                        </div>
                        <nav>
                            <ul class="pagination">
                                <?php if ($client_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['client_page' => 1])) ?>" title="First Page">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['client_page' => $client_page - 1])) ?>" title="Previous Page">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-angle-double-left"></i></span>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-angle-left"></i></span>
                                    </li>
                                <?php endif; ?>

                                <?php
                                // Show page numbers
                                $start_page = max(1, $client_page - 2);
                                $end_page = min($total_client_pages, $client_page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?= $i == $client_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['client_page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($client_page < $total_client_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['client_page' => $client_page + 1])) ?>" title="Next Page">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['client_page' => $total_client_pages])) ?>" title="Last Page">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-angle-right"></i></span>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-angle-double-right"></i></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h4>No clients found</h4>
                        <p>There are no clients in the system</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Units Card -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-home"></i>
                <span>Units</span>
                <span class="badge bg-primary ms-2"><?= $total_units ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($paginated_units)): ?>
                    <!-- Desktop Table -->
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Type ID</th>
                                    <th>Price</th>
                                    <th>Renter</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paginated_units as $u): 
                                    $has_renter = !empty($u['Client_fn']);
                                    $renter_name = $has_renter ? htmlspecialchars($u['Client_fn'] . ' ' . $u['Client_ln']) : '';
                                ?>
                                <tr>
                                    <td>
                                        <span class="fw-medium">#<?= htmlspecialchars($u['Space_ID']) ?></span>
                                    </td>
                                    <td>
                                        <form method="post" class="d-inline-flex align-items-center" style="gap:0.5rem;">
                                            <input type="hidden" name="space_id" value="<?= $u['Space_ID'] ?>">
                                            <input type="text" name="new_name" value="<?= htmlspecialchars($u['Name']) ?>" class="form-control form-control-sm" style="width:120px;" required>
                                            <button type="submit" name="rename_unit" class="btn-action btn-update" title="Rename Unit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td><?= htmlspecialchars($u['SpaceType_ID']) ?></td>
                                    <td>
                                        <form method="post" class="price-form">
                                            <input type="hidden" name="space_id" value="<?= $u['Space_ID'] ?>">
                                            <input type="number" min="0" step="0.01" name="new_price" value="<?= htmlspecialchars($u['Price']) ?>" class="form-control form-control-sm price-input" required>
                                            <button type="submit" name="update_price" class="btn-action btn-update">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <?= $has_renter ? $renter_name : '<span class="text-muted">None</span>' ?>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="space_id" value="<?= $u['Space_ID'] ?>">
                                                <div class="tooltip-wrapper">
                                                    <button type="submit" name="force_delete_unit" class="btn-action btn-force-delete">
                                                        <i class="fas fa-skull"></i>
                                                    </button>
                                                    <span class="tooltip-text">Delete</span>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card Layout -->
                    <div class="table-mobile">
                        <?php foreach ($paginated_units as $u): 
                            $has_renter = !empty($u['Client_fn']);
                            $renter_name = $has_renter ? htmlspecialchars($u['Client_fn'] . ' ' . $u['Client_ln']) : '';
                        ?>
                            <div class="mobile-card">
                                <div class="mobile-card-header">
                                    <div>
                                        <strong><?= htmlspecialchars($u['Name']) ?></strong>
                                        <span class="badge bg-primary ms-2">#<?= $u['Space_ID'] ?></span>
                                    </div>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Type ID:</span>
                                    <span class="value"><?= htmlspecialchars($u['SpaceType_ID']) ?></span>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Price:</span>
                                    <span class="value">₱<?= number_format($u['Price'], 2) ?></span>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Renter:</span>
                                    <span class="value"><?= $has_renter ? $renter_name : '<span class="text-muted">None</span>' ?></span>
                                </div>

                                <!-- Rename Form -->
                                <form method="post" class="mobile-form">
                                    <input type="hidden" name="space_id" value="<?= $u['Space_ID'] ?>">
                                    <input type="text" name="new_name" value="<?= htmlspecialchars($u['Name']) ?>" class="form-control form-control-sm" placeholder="Unit name" required>
                                    <button type="submit" name="rename_unit" class="btn-action btn-update" title="Rename Unit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </form>

                                <!-- Price Update Form -->
                                <form method="post" class="mobile-form">
                                    <input type="hidden" name="space_id" value="<?= $u['Space_ID'] ?>">
                                    <input type="number" min="0" step="0.01" name="new_price" value="<?= htmlspecialchars($u['Price']) ?>" class="form-control form-control-sm" placeholder="Price" required>
                                    <button type="submit" name="update_price" class="btn-action btn-update" title="Update Price">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </form>

                                <!-- Actions -->
                                <div class="mobile-actions">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="space_id" value="<?= $u['Space_ID'] ?>">
                                        <button type="submit" name="force_delete_unit" class="btn-action btn-force-delete" title="Delete Unit">
                                            <i class="fas fa-skull"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Unit Pagination -->
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?= count($paginated_units) ?> of <?= $total_units ?> units
                        </div>
                        <nav>
                            <ul class="pagination">
                                <?php if ($unit_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['unit_page' => 1])) ?>" title="First Page">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['unit_page' => $unit_page - 1])) ?>" title="Previous Page">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-angle-double-left"></i></span>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-angle-left"></i></span>
                                    </li>
                                <?php endif; ?>

                                <?php
                                // Show page numbers
                                $start_page = max(1, $unit_page - 2);
                                $end_page = min($total_unit_pages, $unit_page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?= $i == $unit_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['unit_page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($unit_page < $total_unit_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['unit_page' => $unit_page + 1])) ?>" title="Next Page">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['unit_page' => $total_unit_pages])) ?>" title="Last Page">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-angle-right"></i></span>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-angle-double-right"></i></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-home"></i>
                        <h4>No units found</h4>
                        <p>There are no units in the system</p>
                    </div>
                <?php endif; ?>
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

        mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        mobileOverlay.addEventListener('click', toggleMobileMenu);

        // Close mobile menu when clicking on nav links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    mobileOverlay.classList.remove('active');
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
                mobileOverlay.classList.remove('active');
            }
        });

        // Confirmations for destructive actions
        document.querySelectorAll('form').forEach(form => {
            if (form.querySelector('[name="nuke_client"]')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('!!! NUKE !!!\nPERMANENTLY DELETE this client and ALL their records.\nAny space they rent will be set to available! THIS CANNOT BE UNDONE. Are you SURE?')) {
                        e.preventDefault();
                    }
                });
            }
            
            if (form.querySelector('[name="hard_delete_client"]')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('PERMANENTLY DELETE this client and all their records? This cannot be undone!')) {
                        e.preventDefault();
                    }
                });
            }
            
            if (form.querySelector('[name="delete_unit"]')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Permanently delete this unit and all its records? This cannot be undone!')) {
                        e.preventDefault();
                    }
                });
            }
            
            if (form.querySelector('[name="force_delete_unit"]')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('!!! FORCE DELETE !!!\nThis will PERMANENTLY DELETE the unit and ALL associated records, INCLUDING any renter relationships.\nThis CANNOT BE UNDONE and will likely orphan client records!\n\nAre you absolutely certain?')) {
                        e.preventDefault();
                    }
                });
            }
        });

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
                
                // Re-enable after 3 seconds to handle errors
                setTimeout(() => {
                    isSubmitting = false;
                }, 3000);
            });
        });

        // Reset to page 1 when filter changes
        document.getElementById('clientUnitFilter').addEventListener('change', function() {
            document.querySelector('input[name="client_page"]').value = 1;
            document.querySelector('input[name="unit_page"]').value = 1;
        });
    </script>
</body>
</html>