<?php
session_start();

// If OTP or user data not set, redirect to registration
if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_user_data'])) {
    header('Location: index.php');
    exit();
}

$otp_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp']);
    if ($entered_otp == $_SESSION['otp']) {
        // OTP correct, register user
        require 'database/database.php';
        $db = new Database();
        $data = $_SESSION['otp_user_data'];
        $success = $db->registerClient(
            $data['fname'],
            $data['lname'],
            $data['email'],
            $data['phone'],
            $data['username'],
            $data['password']
        );
        unset($_SESSION['otp']);
        unset($_SESSION['otp_user_data']);
        unset($_SESSION['otp_email']);
        if ($success) {
            $_SESSION['register_success'] = 'Registration successful! You can now log in.';
            header('Location: index.php');
            exit();
        } else {
            $_SESSION['register_error'] = 'An unexpected error occurred. Please try again later.';
            header('Location: index.php');
            exit();
        }
    } else {
        $otp_error = 'Invalid OTP. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - ASRT Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg">
                    <div class="card-body">
                        <h3 class="card-title mb-4 text-center">Email Verification</h3>
                        <p class="mb-3">We have sent a 6-digit OTP to your email: <b><?php echo htmlspecialchars($_SESSION['otp_email']); ?></b></p>
                        <form method="post">
                            <div class="mb-3">
                                <label for="otp" class="form-label">Enter OTP</label>
                                <input type="text" class="form-control" id="otp" name="otp" maxlength="6" required autofocus>
                            </div>
                            <?php if ($otp_error): ?>
                                <div class="alert alert-danger py-2"><?php echo $otp_error; ?></div>
                            <?php endif; ?>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Verify & Register</button>
                            </div>
                        </form>
                        <form method="post" action="resend_otp.php" class="mt-3 text-center">
                            <button type="submit" class="btn btn-link">Resend OTP</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
