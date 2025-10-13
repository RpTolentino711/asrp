<?php
require '../database/database.php';
session_start();

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// --- GET COUNTS FOR SIDEBAR NOTIFICATIONS ---
$rental_count = $db->getRow("SELECT COUNT(*) as count FROM rentalrequest WHERE Status = 'Pending' AND Flow_Status = 'new'")['count'];
$maintenance_count = $db->getRow("SELECT COUNT(*) as count FROM maintenancerequest WHERE Status = 'Submitted'")['count'];
$chat_count = $db->getRow("SELECT COUNT(*) as count FROM invoice_chat WHERE Sender_Type = 'client' AND is_read_admin = 0")['count'];

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
        .form-control-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.875rem;
            border-radius: var(--border-radius);
            border: 1px solid #d1d5db;
            transition: var(--transition);
        }
        
        .form-control-sm:focus {
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
        
        .btn-deactivate {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .btn-deactivate:hover {
            background: #f59e0b;
            color: white;
        }
        
        .btn-activate {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .btn-activate:hover {
            background: #10b981;
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
        
        .btn-nuke {
            background: rgba(55, 65, 81, 0.1);
            color: #374151;
            border: 1px solid rgba(55, 65, 81, 0.2);
        }
        
        .btn-nuke:hover {
            background: #374151;
            color: white;
        }
        
        .btn-update {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .btn-update:hover {
            background: #6366f1;
            color: white;
        }
        
        .btn-force-delete {
            background: rgba(220, 38, 127, 0.1);
            color: #dc2626;
            border: 1px solid rgba(220, 38, 127, 0.2);
        }
        
        .btn-force-delete:hover {
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
        
        /* Action Group */
        .action-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        /* Price Update Form */
        .price-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .price-input {
            width: 100px;
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

        /* Mobile Card Layout */
        .mobile-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            padding: 1rem;
            border-left: 4px solid var(--primary);
        }

        .mobile-card-header {
            font-weight: 600;
            font-size: 1rem;
            color: var(--dark);
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .mobile-card-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            flex-wrap: wrap;
        }

        .mobile-card-detail .label {
            font-weight: 500;
            color: #6b7280;
        }

        .mobile-card-detail .value {
            color: var(--dark);
        }

        .mobile-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .mobile-form {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .mobile-form input {
            flex: 1;
            min-width: 120px;
        }

        .filter-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Hide desktop table on mobile */
        .table-mobile {
            display: none;
        }
        
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
                margin-bottom: 1.5rem;
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
                display: none;
            }

            .table-mobile {
                display: block;
            }

            .card-body {
                padding: 1rem;
            }

            .card-header {
                padding: 1rem;
                font-size: 1rem;
            }

            .btn-action {
                width: 40px;
                height: 40px;
            }

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
        
        @media (max-width: 768px) {
            .main-content {
                padding: 0.75rem;
            }

            .action-group {
                justify-content: center;
            }

            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .mobile-actions {
                justify-content: center;
            }

            .mobile-form {
                flex-direction: column;
                align-items: stretch;
            }

            .mobile-form input {
                min-width: auto;
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

            .btn-action {
                width: 36px;
                height: 36px;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn-action, .nav-link, .mobile-menu-btn {
                min-height: 44px;
                min-width: 44px;
            }

            .btn-action:hover {
                transform: none;
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
        
        /* Tooltip */
        .tooltip-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .tooltip-wrapper .tooltip-text {
            visibility: hidden;
            width: 120px;
            background-color: #374151;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.75rem;
        }
        
        .tooltip-wrapper:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Hide tooltips on mobile */
        @media (max-width: 992px) {
            .tooltip-wrapper .tooltip-text {
                display: none;
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
        // --- ENHANCED NOTIFICATION SYSTEM ---
        let rentalNotificationCooldown = false;
        let maintenanceNotificationCooldown = false;
        let clientMessageNotificationCooldown = false;

        let lastUnseenRentals = <?= $unseen_rentals ?>;
        let lastNewMaintenance = <?= $new_maintenance_requests ?>;
        let lastUnreadClientMessages = <?= $unread_client_messages ?>;
        let isFirstLoad = true;
        let isTabActive = true;

        // Debug logging
        console.log('Manage Users initialized');
        console.log('Initial counts - Unseen Rentals: <?= $unseen_rentals ?>, New Maintenance: <?= $new_maintenance_requests ?>, Unread Messages: <?= $unread_client_messages ?>');

        // Tab visibility handling
        document.addEventListener('visibilitychange', function() {
            isTabActive = !document.hidden;
            console.log('Tab visibility changed:', isTabActive ? 'active' : 'hidden');
            if (isTabActive) {
                fetchDashboardCounts();
            }
        });

        // Show rental notification
        function showNewRequestNotification(count) {
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

        // Show maintenance notification
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
                                <i class="fas fa-tools me-1"></i>View Maintenance
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

        // Show client message notification
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
                        const currentNewMaintenance = data.new_maintenance_requests ?? 0;
                        const currentUnreadClientMessages = data.unread_client_messages ?? 0;

                        // Check for new rental requests
                        if (!isFirstLoad && currentUnseenRentals > lastUnseenRentals) {
                            const newRequests = currentUnseenRentals - lastUnseenRentals;
                            console.log(`New rental requests detected: ${newRequests} (was ${lastUnseenRentals}, now ${currentUnseenRentals})`);
                            showNewRequestNotification(newRequests);
                            
                            // Update sidebar badge
                            updateSidebarBadge(currentUnseenRentals, 'sidebarRentalBadge', 'view_rental_requests.php');
                        }
                        
                        // Check for new maintenance requests
                        if (!isFirstLoad && currentNewMaintenance > lastNewMaintenance) {
                            const newMaintenance = currentNewMaintenance - lastNewMaintenance;
                            console.log(`New maintenance requests detected: ${newMaintenance} (was ${lastNewMaintenance}, now ${currentNewMaintenance})`);
                            showNewMaintenanceNotification(newMaintenance);
                            
                            // Update sidebar badge
                            updateSidebarBadge(currentNewMaintenance, 'sidebarMaintenanceBadge', 'manage_maintenance.php');
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
                        lastNewMaintenance = currentNewMaintenance;
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

        // Debug: Manual trigger for testing
        window.testNotification = function(type) {
            if (type === 'rental') {
                showNewRequestNotification(1);
            } else if (type === 'maintenance') {
                showNewMaintenanceNotification(1);
            } else if (type === 'client_message') {
                showNewClientMessageNotification(1);
            }
        };

        // Start polling for notifications
        document.addEventListener('DOMContentLoaded', () => {
            console.log('Manage Users fully loaded with ENHANCED notification system');
            console.log('Test notifications with: testNotification("rental") or testNotification("maintenance") or testNotification("client_message")');
            
            fetchDashboardCounts();
            
            // Poll every 5 seconds for faster response
            setInterval(() => {
                if (isTabActive) {
                    fetchDashboardCounts();
                }
            }, 5000);
        });
    </script>
</body>
</html>