<?php
session_start();
require_once '../database/database.php';

$db = new Database();

// --- Authentication ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$edit = false;
$edit_data = ['Handyman_ID' => '', 'Handyman_fn' => '', 'Handyman_ln' => '', 'Phone' => '', 'JobType_ID' => ''];
$msg = '';

// --- Handle DELETE Request ---
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





// --- Handle POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add a new job type
    if (isset($_POST['add_jobtype'])) {
        $new_jobtype = trim($_POST['NewJobType'] ?? '');
        if (!empty($new_jobtype)) {
            if ($db->addJobType($new_jobtype)) {
                header("Location: admin_add_handyman.php?msg=jobtype_added");
                exit;
            } else {
                $msg = "Failed to add new job type.";
            }
        } else {
            $msg = "Job type name cannot be empty.";
        }
    } else {
        // Handle adding/updating a handyman
        $fn = trim($_POST['Handyman_fn'] ?? '');
        $ln = trim($_POST['Handyman_ln'] ?? '');
        $phone = trim($_POST['Phone'] ?? '');
        $jobtype_id = intval($_POST['JobType_ID'] ?? 0);

        // Use strict checks to avoid "" and 0
        if ($fn !== '' && $ln !== '' && $phone !== '' && $jobtype_id > 0) {
            if (isset($_POST['handyman_id']) && !empty($_POST['handyman_id'])) {
                // UPDATE
                $id = intval($_POST['handyman_id']);
                if ($db->updateHandyman($id, $fn, $ln, $phone, $jobtype_id)) {
                    header("Location: admin_add_handyman.php?msg=updated");
                    exit;
                } else { $msg = "Failed to update handyman."; }
            } else {
                // ADD
                if ($db->addHandyman($fn, $ln, $phone, $jobtype_id)) {
                    header("Location: admin_add_handyman.php?msg=added");
                    exit;
                } else { $msg = "Failed to add handyman."; }
            }
        } else {
            $msg = "All handyman fields are required.";
        }
    }
}

// --- Handle EDIT Request (to populate the form) ---
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handyman Management - Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --bg-light: #f8fafc;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
        }

        .page-header h1 {
            color: #1e293b;
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h1 i {
            color: var(--primary-color);
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--primary-color);
        }

        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }

        .stats-card p {
            color: var(--secondary-color);
            margin: 5px 0 0 0;
            font-size: 0.9rem;
        }

        .content-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 30px;
        }

        .content-card h2 {
            color: #1e293b;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .content-card h2 i {
            color: var(--primary-color);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .custom-table {
            margin: 0;
            border: none;
        }

        .custom-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .custom-table thead th {
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border: none;
            padding: 18px 15px;
        }

        .custom-table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border-color);
        }

        .custom-table tbody tr:hover {
            background-color: #f1f5f9;
            transform: scale(1.01);
        }

        .custom-table tbody td {
            padding: 18px 15px;
            vertical-align: middle;
            color: #334155;
            border: none;
        }

        .badge-job-type {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .btn-custom {
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #5568d3 0%, #653a8b 100%);
            color: white;
        }

        .btn-success-custom {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-success-custom:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
        }

        .btn-warning-custom {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .btn-danger-custom {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-secondary-custom {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-sm-custom {
            padding: 6px 14px;
            font-size: 0.875rem;
        }

        .form-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 25px;
            border: 2px solid var(--border-color);
        }

        .form-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
        }

        .alert-custom i {
            font-size: 1.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--secondary-color);
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .content-card {
                padding: 20px;
            }

            .custom-table {
                font-size: 0.875rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
            }
        }

        .section-divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--border-color), transparent);
            margin: 40px 0;
        }
    </style>
</head>
<body>
<div class="main-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h1><i class="fas fa-user-cog"></i> Handyman Management</h1>
            <a href="dashboard.php" class="btn btn-secondary-custom btn-custom">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="content-card">
            <div class="alert alert-danger alert-custom">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= htmlspecialchars($msg) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="stats-card">
                <h3><?= count($handymen_list) ?></h3>
                <p><i class="fas fa-users"></i> Total Handymen</p>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="stats-card" style="border-left-color: var(--success-color);">
                <h3 style="color: var(--success-color);"><?= count($jobtypes) ?></h3>
                <p><i class="fas fa-briefcase"></i> Job Types Available</p>
            </div>
        </div>
    </div>

    <!-- Handyman List -->
    <div class="content-card">
        <h2><i class="fas fa-list"></i> Handyman Directory</h2>
        
        <?php if (empty($handymen_list)): ?>
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <h4>No Handymen Found</h4>
                <p>Start by adding your first handyman using the form below.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> First Name</th>
                            <th><i class="fas fa-user"></i> Last Name</th>
                            <th><i class="fas fa-phone"></i> Phone</th>
                            <th><i class="fas fa-briefcase"></i> Job Type</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($handymen_list as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['Handyman_fn']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($r['Handyman_ln']) ?></strong></td>
                            <td><i class="fas fa-phone-alt" style="color: var(--secondary-color);"></i> <?= htmlspecialchars($r['Phone']) ?></td>
                            <td><span class="badge-job-type"><?= htmlspecialchars($r['JobType_Name'] ?? 'Unassigned') ?></span></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?edit=<?= $r['Handyman_ID'] ?>" class="btn btn-warning-custom btn-custom btn-sm-custom">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="#" class="btn btn-danger-custom btn-custom btn-sm-custom" onclick="confirmDelete(<?= $r['Handyman_ID'] ?>)">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="section-divider"></div>

    <!-- Add/Edit Handyman Form -->
    <div class="content-card">
        <h2>
            <i class="fas fa-<?= $edit ? 'user-edit' : 'user-plus' ?>"></i> 
            <?= $edit ? 'Edit Handyman' : 'Add New Handyman' ?>
        </h2>
        
        <form method="POST">
            <?php if ($edit): ?>
                <input type="hidden" name="handyman_id" value="<?= $edit_data['Handyman_ID'] ?>">
            <?php endif; ?>
            
            <div class="form-section">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-user"></i> First Name *</label>
                        <input type="text" name="Handyman_fn" class="form-control" required
                            value="<?= htmlspecialchars($edit_data['Handyman_fn']) ?>"
                            placeholder="Enter first name">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-user"></i> Last Name *</label>
                        <input type="text" name="Handyman_ln" class="form-control" required
                            value="<?= htmlspecialchars($edit_data['Handyman_ln']) ?>"
                            placeholder="Enter last name">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-phone"></i> Phone Number *</label>
                        <input type="text" name="Phone" class="form-control" required
                            value="<?= htmlspecialchars($edit_data['Phone']) ?>"
                            placeholder="Enter phone number">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-briefcase"></i> Job Type *</label>
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

                <div class="d-flex gap-2 flex-wrap mt-4">
                    <button type="submit" class="btn btn-primary-custom btn-custom">
                        <i class="fas fa-<?= $edit ? 'save' : 'plus' ?>"></i> 
                        <?= $edit ? 'Update Handyman' : 'Add Handyman' ?>
                    </button>
                    <?php if ($edit): ?>
                        <a href="admin_add_handyman.php" class="btn btn-secondary-custom btn-custom">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="section-divider"></div>

    <!-- Add Job Type Form -->
    <div class="content-card">
        <h2><i class="fas fa-briefcase"></i> Add New Job Type</h2>
        
        <form method="POST">
            <div class="form-section">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label"><i class="fas fa-tag"></i> Job Type Name *</label>
                        <input type="text" name="NewJobType" class="form-control" required 
                            placeholder="e.g., General Cleaning, Plumbing, Electrical">
                    </div>
                    <div class="col-md-4 mb-3 d-flex align-items-end">
                        <button type="submit" name="add_jobtype" class="btn btn-success-custom btn-custom w-100">
                            <i class="fas fa-plus-circle"></i> Add Job Type
                        </button>
                    </div>
                </div>
            </div>
        </form>
            <div class="mt-4">
                <h4><i class="fas fa-list"></i> All Job Types</h4>
                <div class="table-container">
                    <table class="table custom-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobtypes as $jt): ?>
                            <tr>
                                <td><?= $jt['JobType_ID'] ?></td>
                                <td><?= htmlspecialchars($jt['JobType_Name']) ?></td>
                                <td>
                                    <a href="#" class="btn btn-danger-custom btn-custom btn-sm-custom" onclick="confirmDeleteJobType(<?= $jt['JobType_ID'] ?>, '<?= htmlspecialchars(addslashes($jt['JobType_Name'])) ?>')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    let title = 'Success!';
    let text = '';
    let icon = 'success';
    
    if (msg === 'added') text = 'Handyman successfully added to the system.';
    if (msg === 'updated') text = 'Handyman information has been updated.';
    if (msg === 'deleted') text = 'Handyman has been removed from the system.';
    if (msg === 'jobtype_added') text = 'New job type has been added successfully.';
    if (msg === 'error') { 
        title = 'Error!'; 
        text = 'An operation could not be completed. Please try again.';
        icon = 'error';
    }

    if (text) {
        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            confirmButtonColor: '#667eea',
            confirmButtonText: 'OK'
        });
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

function confirmDelete(handymanId) {
    Swal.fire({
        title: 'Delete Handyman?',
        text: 'Are you sure you want to delete this handyman? This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?delete=' + handymanId;
        }
    });
}

function confirmDeleteJobType(jobTypeId, jobTypeName) {
    Swal.fire({
        title: 'Delete Job Type?',
        text: 'Are you sure you want to delete the job type "' + jobTypeName + '"? This will remove it from all handymen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?delete_jobtype=' + jobTypeId;
        }
    });
}
</script>
</body>
</html>