<?php
// settings.php
session_start();
require_once __DIR__ . '/database/database.php';

if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$pdo = $db->pdo ?? null;
if (!$pdo && method_exists($db, 'opencon')) {
    $pdo = $db->opencon();
}

$client_id = $_SESSION['client_id'];
$stmt = $pdo->prepare('SELECT * FROM client WHERE C_id = ?');
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) {
    echo 'Client not found.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container mt-5 pt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Account Settings</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="update_profile.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="fname" value="<?= htmlspecialchars($client['Client_fn']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="lname" value="<?= htmlspecialchars($client['Client_ln']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($client['Client_Email']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($client['Client_Contact']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($client['C_username']) ?>" required>
                            </div>
                        </div>
                        <hr>
                        <h6 class="mb-3"><i class="bi bi-key me-2 text-primary"></i>Change Password</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" autocomplete="current-password">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" autocomplete="new-password">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_new_password" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-success">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
