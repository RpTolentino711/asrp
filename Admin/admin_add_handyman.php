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
        $new_icon = trim($_POST['JobIcon'] ?? 'fa-wrench');
        if (!empty($new_jobtype)) {
            if ($db->addJobTypeWithIcon($new_jobtype, $new_icon)) {
                header("Location: admin_add_handyman.php?msg=jobtype_added");
                exit;
            } else {
                $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to add new job type.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
            }
        } else {
            $msg = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Job type name cannot be empty.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    } else {
        // Handle adding/updating a handyman
        $fn = trim($_POST['Handyman_fn'] ?? '');
        $ln = trim($_POST['Handyman_ln'] ?? '');
        $phone = trim($_POST['Phone'] ?? '');
        $jobtype_id = intval($_POST['JobType_ID'] ?? 0);

        if ($fn !== '' && $ln !== '' && $phone !== '' && $jobtype_id > 0) {
            if (isset($_POST['handyman_id']) && !empty($_POST['handyman_id'])) {
                // UPDATE
                $id = intval($_POST['handyman_id']);
                if ($db->updateHandyman($id, $fn, $ln, $phone, $jobtype_id)) {
                    header("Location: admin_add_handyman.php?msg=updated");
                    exit;
                } else {
                    $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to update handyman.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                }
            } else {
                // ADD
                if ($db->addHandyman($fn, $ln, $phone, $jobtype_id)) {
                    header("Location: admin_add_handyman.php?msg=added");
                    exit;
                } else {
                    $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to add handyman.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                }
            }
        } else {
            $msg = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    All handyman fields are required.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
}

// --- Handle EDIT Request ---
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

// Display success messages
if (isset($_GET['msg'])) {
    $msg_type = $_GET['msg'];
    $alert_messages = [
        'added' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'Handyman successfully added to the system!'],
        'updated' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'Handyman information has been updated!'],
        'deleted' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'Handyman has been removed from the system!'],
        'jobtype_added' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'New job type has been added successfully!'],
        'jobtype_deleted' => ['type' => 'success', 'icon' => 'check-circle', 'text' => 'Job type has been deleted successfully!'],
        'error' => ['type' => 'danger', 'icon' => 'exclamation-circle', 'text' => 'An error occurred. Please try again.'],
        'jobtype_delete_error' => ['type' => 'danger', 'icon' => 'exclamation-circle', 'text' => 'Failed to delete job type. It may be assigned to handymen.']
    ];
    
    if (isset($alert_messages[$msg_type])) {
        $alert = $alert_messages[$msg_type];
        $msg = '<div class="alert alert-' . $alert['type'] . ' alert-dismissible fade show" role="alert">
                <i class="fas fa-' . $alert['icon'] . ' me-2"></i>
                ' . $alert['text'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handyman Management | ASRT Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
        }
        .icon-option {
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
        }
        .icon-option:hover, .icon-option.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
            transform: translateY(-2px);
        }
        .icon-picker {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
            gap: 10px;
            margin-top: 10px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
        }
        .badge-job {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar">
                <div class="position-sticky pt-3">
                    <div class="sidebar-header p-3 border-bottom border-secondary">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-crown me-2"></i>
                            ASRT Admin
                        </h5>
                    </div>
                    <ul class="nav flex-column mt-3">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active bg-primary rounded" href="admin_add_handyman.php">
                                <i class="fas fa-user-cog me-2"></i>
                                Handyman Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="manage_user.php">
                                <i class="fas fa-users me-2"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="view_rental_requests.php">
                                <i class="fas fa-clipboard-check me-2"></i>
                                Rental Requests
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="me-3 bg-primary rounded p-3 text-white">
                            <i class="fas fa-user-cog fa-lg"></i>
                        </div>
                        <div>
                            <h1 class="h2 mb-0">Handyman Management</h1>
                            <p class="text-muted mb-0">Manage handymen and their job types</p>
                        </div>
                    </div>
                </div>

                <?php echo $msg; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded p-3 me-3">
                                        <i class="fas fa-users fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-0 text-primary"><?php echo count($handymen_list); ?></h3>
                                        <p class="text-muted mb-0">Total Handymen</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-success bg-opacity-10 rounded p-3 me-3">
                                        <i class="fas fa-briefcase fa-2x text-success"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-0 text-success"><?php echo count($jobtypes); ?></h3>
                                        <p class="text-muted mb-0">Job Types Available</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Handyman List -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 d-flex align-items-center">
                            <i class="fas fa-list me-2 text-primary"></i>
                            Handyman Directory
                            <span class="badge bg-primary ms-2"><?php echo count($handymen_list); ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($handymen_list)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No handymen found</h4>
                                <p class="text-muted">Start by adding your first handyman using the form below</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Phone</th>
                                            <th>Job Type</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($handymen_list as $r): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($r['Handyman_fn'] . ' ' . $r['Handyman_ln']); ?></strong>
                                                <br>
                                                <small class="text-muted">ID: #<?php echo $r['Handyman_ID']; ?></small>
                                            </td>
                                            <td>
                                                <i class="fas fa-phone-alt text-muted me-2"></i>
                                                <?php echo htmlspecialchars($r['Phone']); ?>
                                            </td>
                                            <td>
                                                <span class="badge-job">
                                                    <i class="fas <?php echo htmlspecialchars($r['Icon'] ?? 'fa-wrench'); ?>"></i>
                                                    <?php echo htmlspecialchars($r['JobType_Name'] ?? 'Unassigned'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="?edit=<?php echo $r['Handyman_ID']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="confirmDelete(<?php echo $r['Handyman_ID']; ?>, '<?php echo htmlspecialchars(addslashes($r['Handyman_fn'] . ' ' . $r['Handyman_ln'])); ?>')" 
                                                            class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Add/Edit Handyman Form -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 d-flex align-items-center">
                            <i class="fas fa-<?php echo $edit ? 'user-edit' : 'user-plus'; ?> me-2 text-primary"></i>
                            <?php echo $edit ? 'Edit Handyman' : 'Add New Handyman'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($edit): ?>
                                <input type="hidden" name="handyman_id" value="<?php echo $edit_data['Handyman_ID']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-user me-2 text-muted"></i>First Name *</label>
                                    <input type="text" name="Handyman_fn" class="form-control" required
                                        value="<?php echo htmlspecialchars($edit_data['Handyman_fn']); ?>"
                                        placeholder="Enter first name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-user me-2 text-muted"></i>Last Name *</label>
                                    <input type="text" name="Handyman_ln" class="form-control" required
                                        value="<?php echo htmlspecialchars($edit_data['Handyman_ln']); ?>"
                                        placeholder="Enter last name">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-phone me-2 text-muted"></i>Phone Number *</label>
                                    <input type="text" name="Phone" class="form-control" required
                                        value="<?php echo htmlspecialchars($edit_data['Phone']); ?>"
                                        placeholder="Enter phone number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-briefcase me-2 text-muted"></i>Job Type *</label>
                                    <select name="JobType_ID" class="form-select" required>
                                        <option value="">-- Select Job Type --</option>
                                        <?php foreach ($jobtypes as $jt): ?>
                                            <option value="<?php echo $jt['JobType_ID']; ?>"
                                                <?php echo $jt['JobType_ID'] == $edit_data['JobType_ID'] ? 'selected' : ''; ?>>
                                                <i class="fas <?php echo htmlspecialchars($jt['Icon'] ?? 'fa-wrench'); ?> me-2"></i>
                                                <?php echo htmlspecialchars($jt['JobType_Name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-<?php echo $edit ? 'save' : 'plus'; ?> me-2"></i>
                                    <?php echo $edit ? 'Update Handyman' : 'Add Handyman'; ?>
                                </button>
                                <?php if ($edit): ?>
                                    <a href="admin_add_handyman.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Job Types Section -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 d-flex align-items-center">
                            <i class="fas fa-briefcase me-2 text-primary"></i>
                            Job Type Management
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="jobTypeForm">
                            <div class="row align-items-end">
                                <div class="col-md-5 mb-3">
                                    <label class="form-label"><i class="fas fa-tag me-2 text-muted"></i>Job Type Name *</label>
                                    <input type="text" name="NewJobType" class="form-control" required 
                                        placeholder="e.g., Plumbing, Electrical, Carpentry">
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label class="form-label"><i class="fas fa-icons me-2 text-muted"></i>Select Icon *</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">
                                            <i class="fas fa-wrench" id="selectedIconPreview"></i>
                                        </span>
                                        <input type="text" name="JobIcon" id="jobIconInput" class="form-control" 
                                            value="fa-wrench" readonly required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="toggleIconPicker()">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Icon Picker -->
                                    <div id="iconPicker" class="icon-picker">
                                        <?php 
                                        $icons = ['fa-wrench', 'fa-hammer', 'fa-screwdriver', 'fa-toolbox', 'fa-bolt', 
                                                 'fa-plug', 'fa-paint-roller', 'fa-faucet', 'fa-toilet', 'fa-fire-extinguisher',
                                                 'fa-hard-hat', 'fa-tools', 'fa-ruler', 'fa-brush', 'fa-spray-can'];
                                        foreach ($icons as $icon): 
                                        ?>
                                            <div class="icon-option" data-icon="<?php echo $icon; ?>" onclick="selectIcon('<?php echo $icon; ?>')">
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <button type="submit" name="add_jobtype" class="btn btn-success w-100">
                                        <i class="fas fa-plus-circle me-2"></i>Add
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="mt-4">
                            <h6 class="border-bottom pb-2">
                                <i class="fas fa-list me-2"></i>All Job Types
                            </h6>
                            <?php if (empty($jobtypes)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-briefcase fa-2x text-muted mb-3"></i>
                                    <h5 class="text-muted">No job types found</h5>
                                    <p class="text-muted">Add your first job type using the form above</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Icon</th>
                                                <th>Name</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($jobtypes as $jt): ?>
                                            <tr>
                                                <td>
                                                    <i class="fas <?php echo htmlspecialchars($jt['Icon'] ?? 'fa-wrench'); ?> fa-lg text-primary"></i>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($jt['JobType_Name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">ID: #<?php echo $jt['JobType_ID']; ?></small>
                                                </td>
                                                <td>
                                                    <button onclick="confirmDeleteJobType(<?php echo $jt['JobType_ID']; ?>, '<?php echo htmlspecialchars(addslashes($jt['JobType_Name'])); ?>')" 
                                                            class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initially hide the icon picker
        document.getElementById('iconPicker').style.display = 'none';

        function toggleIconPicker() {
            const picker = document.getElementById('iconPicker');
            picker.style.display = picker.style.display === 'none' ? 'grid' : 'none';
        }

        function selectIcon(iconClass) {
            document.getElementById('jobIconInput').value = iconClass;
            document.getElementById('selectedIconPreview').className = 'fas ' + iconClass;
            
            // Update selected state
            document.querySelectorAll('.icon-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            document.querySelector(`[data-icon="${iconClass}"]`).classList.add('selected');
            
            // Close picker
            document.getElementById('iconPicker').style.display = 'none';
        }

        // Close icon picker when clicking outside
        document.addEventListener('click', function(event) {
            const picker = document.getElementById('iconPicker');
            const iconInput = document.getElementById('jobIconInput');
            const searchBtn = event.target.closest('.btn-outline-secondary');
            
            if (picker && !picker.contains(event.target) && 
                event.target !== iconInput && !searchBtn) {
                picker.style.display = 'none';
            }
        });

        function confirmDelete(handymanId, name) {
            if (confirm(`Are you sure you want to delete handyman "${name}"?\n\nThis action cannot be undone.`)) {
                window.location.href = '?delete=' + handymanId;
            }
        }

        function confirmDeleteJobType(jobTypeId, jobTypeName) {
            if (confirm(`Are you sure you want to delete the job type "${jobTypeName}"?\n\nThis will remove it from the system.`)) {
                window.location.href = '?delete_jobtype=' + jobTypeId;
            }
        }

        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.opacity = '0';
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