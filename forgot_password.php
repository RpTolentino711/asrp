<?php
// forgot_password.php
session_start();
require_once __DIR__ . '/database/database.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['client_id'])) {
    header('Location: dashboard.php');
    exit;
}

$step = isset($_SESSION['fp_step']) ? $_SESSION['fp_step'] : 1;
$fp_email = $_SESSION['fp_email'] ?? '';
$fp_otp = $_SESSION['fp_otp'] ?? '';
$fp_verified = $_SESSION['fp_verified'] ?? false;

function clear_fp_session() {
    unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_otp'], $_SESSION['fp_otp_expires'], $_SESSION['fp_verified']);
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $pdo = $db->pdo ?? null;
    if (!$pdo && method_exists($db, 'opencon')) {
        $pdo = $db->opencon();
    }

    // Step 1: Request OTP
    if (isset($_POST['email'])) {
        $email = trim($_POST['email']);
        $stmt = $pdo->prepare('SELECT * FROM client WHERE Client_Email = ?');
        $stmt->execute([$email]);
        $client = $stmt->fetch();
        if (!$client) {
            $error = 'No account found with that email.';
        } else {
            $otp = random_int(100000, 999999);
            $_SESSION['fp_email'] = $email;
            $_SESSION['fp_otp'] = (string)$otp;
            $_SESSION['fp_otp_expires'] = time() + 5 * 60;
            $_SESSION['fp_step'] = 2;
            $_SESSION['fp_verified'] = false;
            // Send OTP email
            require_once __DIR__ . '/class.phpmailer.php';
            require_once __DIR__ . '/class.smtp.php';
            $mail = new PHPMailer;
            $mail->CharSet    = 'UTF-8';
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->Port       = 587;
            $mail->SMTPAuth   = true;
            $mail->SMTPSecure = 'tls';
            $mail->Username = 'management@asrt.space';
            $mail->Password = '@Pogilameg10';
            $mail->setFrom($mail->Username, 'ASRP Password Reset');
            $mail->addReplyTo('no-reply@asrp.local', 'ASRP Password Reset');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Your password reset code";
            $mail->Body    = "<p>Hi,</p><p>Your password reset OTP is <b>{$otp}</b>.</p><p>This code expires in 5 minutes.</p><p>If you did not request this, please ignore this email.</p>";
            $mail->AltBody = "Your password reset OTP is {$otp}. It expires in 5 minutes.";
            if (!$mail->send()) {
                $error = 'Failed to send OTP email. Please try again.';
            } else {
                $step = 2;
            }
        }
    }
    // Step 2: Verify OTP
    elseif (isset($_POST['otp'])) {
        $otp = trim($_POST['otp']);
        if (!isset($_SESSION['fp_otp'], $_SESSION['fp_otp_expires']) || time() > $_SESSION['fp_otp_expires']) {
            $error = 'OTP expired. Please request a new one.';
            clear_fp_session();
            $step = 1;
        } elseif ($otp !== $_SESSION['fp_otp']) {
            $error = 'Incorrect OTP.';
            $step = 2;
        } else {
            $_SESSION['fp_verified'] = true;
            $_SESSION['fp_step'] = 3;
            $step = 3;
        }
    }
    // Step 3: Change password
    elseif (isset($_POST['new_password'], $_POST['confirm_password'])) {
        if (!$_SESSION['fp_verified'] || !isset($_SESSION['fp_email'])) {
            $error = 'Session expired. Please start again.';
            clear_fp_session();
            $step = 1;
        } else {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            if ($new_password !== $confirm_password) {
                $error = 'Passwords do not match.';
                $step = 3;
            } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[\W_]/', $new_password)) {
                $error = 'Password must contain at least one uppercase letter and one special character.';
                $step = 3;
            } else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE client SET C_password=? WHERE Client_Email=?');
                $stmt->execute([$hash, $_SESSION['fp_email']]);
                $success = 'Password changed successfully. You can now log in.';
                clear_fp_session();
                $step = 1;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container mt-5 pt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-key me-2"></i>Forgot Password</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
                    <?php elseif (!empty($success)): ?>
                        <div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div>
                    <?php endif; ?>
                    <?php if ($step === 1): ?>
                        <form method="POST" id="fpEmailForm">
                            <div class="mb-3">
                                <label class="form-label">Enter your email address</label>
                                <input type="email" class="form-control" name="email" required autofocus>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Send OTP</button>
                            </div>
                        </form>
                    <?php elseif ($step === 3): ?>
<!-- OTP Modal (always present) -->
<div class="modal fade" id="fpOtpModal" tabindex="-1" aria-labelledby="fpOtpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="fpOtpForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="fpOtpModalLabel">Enter OTP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Enter the OTP sent to your email</label>
                        <input type="text" class="form-control" name="otp" maxlength="6" required autofocus>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <button type="submit" form="fpResendForm" class="btn btn-link p-0">Resend OTP</button>
                        <form method="POST" id="fpResendForm" style="display:none;">
                            <input type="hidden" name="email" value="<?= htmlspecialchars($fp_email) ?>">
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Verify OTP</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($step === 2): ?>
        setTimeout(function() {
            var otpModal = new bootstrap.Modal(document.getElementById('fpOtpModal'));
            otpModal.show();
        }, 200);
    <?php endif; ?>
});
</script>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            <div class="form-text mb-3">Password must contain at least one uppercase letter and one special character.</div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">Change Password</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
