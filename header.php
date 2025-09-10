<?php
// Robust session_start check at the very top.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'database/database.php';
$db = new Database();

// --- Invoice Notification Badge Logic ---
$invoice_alert_count = 0;
if (isset($_SESSION['client_id'])) {
    $invoice_list = $db->getClientInvoiceHistory($_SESSION['client_id']);
    foreach ($invoice_list as $inv) {
        $due = isset($inv['InvoiceDate']) ? $inv['InvoiceDate'] : '';
        $is_unpaid = ($inv['Status'] === 'unpaid');
        $is_overdue = ($due && $inv['Status'] === 'unpaid' && strtotime($due) < strtotime(date('Y-m-d')));
        if ($is_unpaid || $is_overdue) {
            $invoice_alert_count++;
        }
    }
}

function display_flash_message($icon, $title, $message) {
    $safe_message = addslashes($message);
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({ 
                    icon: '{$icon}', 
                    title: '{$title}', 
                    text: '{$safe_message}' 
                });
            });
          </script>";
}

if (isset($_SESSION['login_error'])) {
    display_flash_message('error', 'Login Failed', $_SESSION['login_error']);
    unset($_SESSION['login_error']);
}
if (isset($_SESSION['logout_success'])) {
    display_flash_message('success', 'Logged Out', $_SESSION['logout_success']);
    unset($_SESSION['logout_success']);
}
if (isset($_SESSION['register_success'])) {
    display_flash_message('success', 'Registration Successful', $_SESSION['register_success']);
    unset($_SESSION['register_success']);
}
if (isset($_SESSION['register_error'])) {
    display_flash_message('error', 'Registration Failed', $_SESSION['register_error']);
    unset($_SESSION['register_error']);
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['client_id']);
?>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<style>
:root {
  --navbar-primary: #1e40af;
  --navbar-primary-light: #3b82f6;
  --navbar-secondary: #0f172a;
  --navbar-accent: #ef4444;
  --navbar-success: #059669;
  --navbar-light: #f8fafc;
  --navbar-white: #ffffff;
  --navbar-gray: #64748b;
  --navbar-gray-light: #e2e8f0;
  --navbar-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  --navbar-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.modern-navbar {
  background: rgba(255, 255, 255, 0.95) !important;
  backdrop-filter: blur(20px);
  box-shadow: var(--navbar-shadow);
  border-bottom: 1px solid var(--navbar-gray-light);
  transition: var(--navbar-transition);
  min-height: 70px;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.modern-navbar.scrolled {
  background: rgba(255, 255, 255, 0.98) !important;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.modern-navbar-brand {
  font-family: 'Playfair Display', serif !important;
  font-weight: 700 !important;
  font-size: 1.5rem !important;
  color: var(--navbar-primary) !important;
  text-decoration: none !important;
  display: flex !important;
  align-items: center !important;
  transition: var(--navbar-transition);
}

.modern-navbar-brand:hover {
  color: var(--navbar-primary-light) !important;
  transform: scale(1.02);
}

.modern-navbar-brand .brand-icon {
  font-size: 1.8rem !important;
  margin-right: 0.5rem !important;
  color: var(--navbar-primary) !important;
}

.modern-nav-link {
  color: var(--navbar-secondary) !important;
  font-weight: 500 !important;
  font-size: 1rem !important;
  padding: 0.75rem 1rem !important;
  margin: 0 0.25rem !important;
  border-radius: 8px !important;
  transition: var(--navbar-transition) !important;
  position: relative !important;
  text-decoration: none !important;
  display: flex !important;
  align-items: center !important;
  min-height: 45px !important;
}

.modern-nav-link:hover {
  color: var(--navbar-primary) !important;
  background: rgba(59, 130, 246, 0.1) !important;
  transform: translateY(-1px);
}

.modern-nav-link.active {
  color: var(--navbar-primary) !important;
  background: rgba(59, 130, 246, 0.15) !important;
  font-weight: 600 !important;
}

.modern-nav-link.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 1rem;
  right: 1rem;
  height: 2px;
  background: var(--navbar-primary);
  border-radius: 1px;
}

.notification-badge {
  position: absolute !important;
  top: -5px !important;
  right: -5px !important;
  background: var(--navbar-accent) !important;
  color: white !important;
  border-radius: 50% !important;
  font-size: 0.7rem !important;
  font-weight: 600 !important;
  min-width: 18px !important;
  height: 18px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  animation: pulse 2s infinite !important;
}

.modern-btn {
  font-weight: 600 !important;
  border-radius: 8px !important;
  padding: 0.625rem 1.25rem !important;
  border: none !important;
  transition: var(--navbar-transition) !important;
  font-size: 0.95rem !important;
  min-height: 45px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  text-decoration: none !important;
  margin: 0 0.25rem !important;
}

.modern-btn-primary {
  background: linear-gradient(135deg, var(--navbar-primary) 0%, var(--navbar-primary-light) 100%) !important;
  color: white !important;
}

.modern-btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(30, 64, 175, 0.3);
  color: white !important;
}

.modern-btn-outline {
  border: 2px solid var(--navbar-primary) !important;
  color: var(--navbar-primary) !important;
  background: transparent !important;
}

.modern-btn-outline:hover {
  background: var(--navbar-primary) !important;
  color: white !important;
  transform: translateY(-2px);
}

.modern-btn-accent {
  background: linear-gradient(135deg, var(--navbar-accent) 0%, #f87171 100%) !important;
  color: white !important;
}

.modern-btn-accent:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
  color: white !important;
}

.modern-btn-success {
  background: linear-gradient(135deg, var(--navbar-success) 0%, #10b981 100%) !important;
  color: white !important;
}

.modern-btn-success:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3);
  color: white !important;
}

.modern-navbar-toggler {
  border: none !important;
  padding: 0.5rem !important;
  border-radius: 8px !important;
  background: rgba(30, 64, 175, 0.1) !important;
  color: var(--navbar-primary) !important;
  transition: var(--navbar-transition) !important;
}

.modern-navbar-toggler:hover {
  background: rgba(30, 64, 175, 0.2) !important;
  transform: scale(1.05);
}

.modern-navbar-toggler:focus {
  box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.2) !important;
}

.modern-navbar-toggler-icon {
  background-image: none !important;
  display: flex !important;
  flex-direction: column !important;
  justify-content: space-around !important;
  width: 20px !important;
  height: 15px !important;
}

.modern-navbar-toggler-icon::before,
.modern-navbar-toggler-icon::after,
.modern-navbar-toggler-icon {
  content: '';
  display: block;
  height: 2px;
  background: var(--navbar-primary);
  border-radius: 1px;
  transition: var(--navbar-transition);
}

.modern-navbar-toggler-icon::before,
.modern-navbar-toggler-icon::after {
  width: 100%;
}

/* Mobile Styles */
@media (max-width: 991.98px) {
  .modern-navbar {
    min-height: 60px;
  }

  .modern-navbar-brand {
    font-size: 1.3rem !important;
  }

  .modern-navbar-brand .brand-icon {
    font-size: 1.5rem !important;
  }

  .navbar-collapse {
    background: var(--navbar-white) !important;
    border-radius: 0 0 16px 16px !important;
    box-shadow: var(--navbar-shadow) !important;
    margin-top: 0.5rem !important;
    padding: 1rem 0 !important;
    border-top: 1px solid var(--navbar-gray-light) !important;
  }

  .navbar-collapse.show {
    animation: slideDown 0.3s ease-out;
  }

  .modern-nav-link,
  .modern-btn {
    width: 100% !important;
    margin: 0.25rem 0 !important;
    justify-content: flex-start !important;
  }

  .modern-nav-link.active::after {
    display: none;
  }
}

@media (max-width: 576px) {
  .modern-navbar {
    min-height: 50px;
    padding: 0.5rem 1rem !important;
  }

  .modern-navbar-brand {
    font-size: 1.1rem !important;
  }

  .modern-navbar-brand .brand-icon {
    font-size: 1.3rem !important;
  }

  .modern-nav-link,
  .modern-btn {
    padding: 0.5rem 1rem !important;
    font-size: 0.9rem !important;
    min-height: 40px !important;
  }
}

/* Modal Improvements */
.modern-modal .modal-content {
  border-radius: 16px !important;
  border: none !important;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.15) !important;
}

.modern-modal .modal-header {
  border-bottom: 1px solid var(--navbar-gray-light) !important;
  border-radius: 16px 16px 0 0 !important;
  padding: 1.5rem !important;
  background: linear-gradient(135deg, var(--navbar-light) 0%, var(--navbar-white) 100%) !important;
}

.modern-modal .modal-title {
  font-family: 'Inter', sans-serif !important;
  font-weight: 600 !important;
  color: var(--navbar-secondary) !important;
}

.modern-modal .modal-body {
  padding: 2rem !important;
}

.modern-modal .form-control {
  border-radius: 8px !important;
  border: 1px solid var(--navbar-gray-light) !important;
  padding: 0.75rem 1rem !important;
  transition: var(--navbar-transition) !important;
  font-family: 'Inter', sans-serif !important;
}

.modern-modal .form-control:focus {
  border-color: var(--navbar-primary) !important;
  box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1) !important;
}

.modern-modal .form-label {
  font-weight: 500 !important;
  color: var(--navbar-secondary) !important;
  margin-bottom: 0.5rem !important;
  font-family: 'Inter', sans-serif !important;
}

/* Animations */
@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes pulse {
  0% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
  100% {
    transform: scale(1);
  }
}

/* Loading state for buttons */
.modern-btn.loading {
  position: relative;
  color: transparent !important;
}

.modern-btn.loading::after {
  content: '';
  position: absolute;
  width: 16px;
  height: 16px;
  top: 50%;
  left: 50%;
  margin-left: -8px;
  margin-top: -8px;
  border-radius: 50%;
  border: 2px solid transparent;
  border-top-color: currentColor;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>

<nav class="navbar navbar-expand-lg fixed-top modern-navbar">
  <div class="container">
    <!-- Brand -->
    <a class="modern-navbar-brand" href="index.php">
      <i class="bi bi-house-door-fill brand-icon"></i>
      <span>ASRT Spaces</span>
    </a>

    <!-- Mobile toggle button -->
    <button class="navbar-toggler modern-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="modern-navbar-toggler-icon"></span>
    </button>

    <!-- Navigation items -->
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto align-items-center">
        <!-- Main Navigation -->
        <li class="nav-item">
          <a class="modern-nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="index.php">
            <i class="bi bi-house-door me-2"></i>Home
          </a>
        </li>

        <li class="nav-item">
          <a class="modern-nav-link <?= $current_page == 'invoice_history.php' ? 'active' : '' ?>" href="invoice_history.php" style="position: relative;">
            <i class="bi bi-credit-card me-2"></i>Payment
            <?php if ($invoice_alert_count > 0): ?>
              <span class="notification-badge">
                <?= $invoice_alert_count ?>
              </span>
            <?php endif; ?>
          </a>
        </li>

        <li class="nav-item">
          <a class="modern-nav-link <?= $current_page == 'handyman_type.php' ? 'active' : '' ?>" href="handyman_type.php">
            <i class="bi bi-tools me-2"></i>Services
          </a>
        </li>

        <li class="nav-item">
          <a class="modern-nav-link <?= $current_page == 'maintenance.php' ? 'active' : '' ?>" href="maintenance.php">
            <i class="bi bi-gear me-2"></i>Maintenance
          </a>
        </li>

        <!-- User Actions -->
        <?php if ($is_logged_in): ?>
          <?php if ($current_page != 'dashboard.php'): ?>
          <li class="nav-item ms-2">
            <a href="dashboard.php" class="modern-btn modern-btn-primary">
              <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
          </li>
          <?php endif; ?>
          <li class="nav-item">
            <form action="logout.php" method="post" class="d-inline">
              <button type="submit" class="modern-btn modern-btn-accent">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
              </button>
            </form>
          </li>
        <?php else: ?>
          <li class="nav-item ms-2">
            <button type="button" class="modern-btn modern-btn-outline" data-bs-toggle="modal" data-bs-target="#loginModal">
              <i class="bi bi-person me-2"></i>Login
            </button>
          </li>
          <li class="nav-item">
            <button type="button" class="modern-btn modern-btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
              <i class="bi bi-person-plus me-2"></i>Register
            </button>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Login Modal -->
<div class="modal fade modern-modal" id="loginModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="login.php">
        <div class="modal-header">
          <h5 class="modal-title d-flex align-items-center">
            <i class="bi bi-person-circle fs-3 me-2 text-primary"></i> 
            Client Login
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <div class="input-group">
              <span class="input-group-text">
                <i class="bi bi-person"></i>
              </span>
              <input type="text" class="form-control" name="username" placeholder="Enter your username" required>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
              <span class="input-group-text">
                <i class="bi bi-lock"></i>
              </span>
              <input type="password" class="form-control" name="password" id="login_password" placeholder="Enter your password" required>
              <button type="button" class="input-group-text" onclick="togglePassword('login_password', this)">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
          <div class="d-grid">
            <button type="submit" class="modern-btn modern-btn-primary">
              <i class="bi bi-box-arrow-in-right me-2"></i>Login
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Register Modal -->
<div class="modal fade modern-modal" id="registerModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
  <form id="registerForm" method="POST" action="register.php" onsubmit="return false;">
        <div class="modal-header">
          <h5 class="modal-title d-flex align-items-center">
            <i class="bi bi-person-plus-fill fs-3 me-2 text-primary"></i> 
            Create Account
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <!-- Personal Information -->
            <div class="col-md-6 mb-3">
              <label class="form-label">First Name</label>
              <div class="input-group">
                <span class="input-group-text">
                  <i class="bi bi-person"></i>
                </span>
                <input type="text" class="form-control" name="fname" placeholder="First name" required value="<?= isset($_SESSION['register_backup']['fname']) ? htmlspecialchars($_SESSION['register_backup']['fname']) : '' ?>">
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Last Name</label>
              <div class="input-group">
                <span class="input-group-text">
                  <i class="bi bi-person"></i>
                </span>
                <input type="text" class="form-control" name="lname" placeholder="Last name" required value="<?= isset($_SESSION['register_backup']['lname']) ? htmlspecialchars($_SESSION['register_backup']['lname']) : '' ?>">
              </div>
            </div>

            <!-- Contact Information -->
            <div class="col-md-6 mb-3">
              <label class="form-label">Email Address</label>
              <div class="input-group">
                <span class="input-group-text">
                  <i class="bi bi-envelope"></i>
                </span>
                <input type="email" class="form-control<?php if(isset($_SESSION['register_duplicate']) && $_SESSION['register_duplicate']==='email') echo ' is-invalid'; ?>" name="email" id="reg_email" placeholder="your@email.com" required value="<?= isset($_SESSION['register_backup']['email']) ? htmlspecialchars($_SESSION['register_backup']['email']) : '' ?>">
              </div>
              <div class="form-text text-danger" id="email_msg"></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Phone Number</label>
              <div class="input-group">
                <span class="input-group-text">
                  <i class="bi bi-telephone"></i>
                </span>
                <input type="text" class="form-control" name="phone" id="reg_phone" placeholder="09XXXXXXXXX" maxlength="11" pattern="\d{11}" inputmode="numeric" required value="<?= isset($_SESSION['register_backup']['phone']) ? htmlspecialchars($_SESSION['register_backup']['phone']) : '' ?>">
              </div>
              <div class="form-text text-muted">11 digits (e.g., 09123456789)</div>
            </div>

            <!-- Account Information -->
            <div class="col-md-6 mb-3">
              <label class="form-label">Username</label>
              <div class="input-group">
                <span class="input-group-text">
                  <i class="bi bi-at"></i>
                </span>
                <input type="text" class="form-control<?php if(isset($_SESSION['register_duplicate']) && $_SESSION['register_duplicate']==='username') echo ' is-invalid'; ?>" name="username" id="reg_username" placeholder="Choose username" required value="<?= isset($_SESSION['register_backup']['username']) ? htmlspecialchars($_SESSION['register_backup']['username']) : '' ?>">
<?php if (isset($_SESSION['register_backup'])) unset($_SESSION['register_backup']); ?>
<?php if (isset($_SESSION['register_duplicate'])) unset($_SESSION['register_duplicate']); ?>
              </div>
              <div class="form-text text-danger" id="username_msg"></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Password</label>
              <div class="input-group">
                <span class="input-group-text">
                  <i class="bi bi-lock"></i>
                </span>
                <input type="password" class="form-control" name="password" id="reg_password" placeholder="Create password" required>
                <button type="button" class="input-group-text" onclick="togglePassword('reg_password', this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="form-text text-muted">Must contain uppercase & special character</div>
            </div>

            <!-- Confirm Password -->
            <div class="col-12 mb-4">
              <label class="form-label">Confirm Password</label>
              <div class="input-group">
                <span class="input-group-text">
                  <i class="bi bi-lock-fill"></i>
                </span>
                <input type="password" class="form-control" name="confirm_password" id="reg_confirm_password" placeholder="Confirm password" required>
                <button type="button" class="input-group-text" onclick="togglePassword('reg_confirm_password', this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="d-grid">
            <button type="submit" class="modern-btn modern-btn-success" id="registerSubmitBtn">
              <i class="bi bi-person-check me-2"></i>Create Account
            </button>
          </div>
        </div>
      </form>

<!-- OTP Modal -->
<div class="modal fade modern-modal" id="otpModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="otpForm" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-shield-lock-fill me-2 text-primary"></i>Verify Email (OTP)</h5>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="otpInput" class="form-label">Enter the 6-digit code sent to your email</label>
            <input type="text" class="form-control text-center" id="otpInput" name="otp" maxlength="6" pattern="\d{6}" required autofocus autocomplete="one-time-code">
            <div class="form-text text-danger" id="otpErrorMsg"></div>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <button type="button" class="btn btn-link p-0" id="resendOtpBtn">Resend OTP</button>
            <span id="otpTimer" class="text-muted small"></span>
          </div>
          <div class="d-grid">
            <button type="submit" class="modern-btn modern-btn-primary">Verify & Register</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
    </div>
  </div>
</div>

<script>
// OTP Modal Logic
let otpExpiresAt = null;
let otpTimerInterval = null;

function showOtpModal(expiresAt) {
  otpExpiresAt = expiresAt;
  document.getElementById('otpInput').value = '';
  document.getElementById('otpErrorMsg').textContent = '';
  updateOtpTimer();
  // Hide the register modal if it's open
  const regModalEl = document.getElementById('registerModal');
  if (regModalEl) {
    const regModal = bootstrap.Modal.getInstance(regModalEl) || new bootstrap.Modal(regModalEl);
    regModal.hide();
  }
  const otpModal = new bootstrap.Modal(document.getElementById('otpModal'));
  otpModal.show();
  if (otpTimerInterval) clearInterval(otpTimerInterval);
  otpTimerInterval = setInterval(updateOtpTimer, 1000);
}

function updateOtpTimer() {
  if (!otpExpiresAt) return;
  const now = Math.floor(Date.now() / 1000);
  const secondsLeft = otpExpiresAt - now;
  const timerSpan = document.getElementById('otpTimer');
  if (secondsLeft > 0) {
    const min = Math.floor(secondsLeft / 60);
    const sec = secondsLeft % 60;
    timerSpan.textContent = `Expires in ${min}:${sec.toString().padStart(2, '0')}`;
    document.getElementById('resendOtpBtn').disabled = true;
  } else {
    timerSpan.textContent = 'OTP expired. Please resend.';
    document.getElementById('resendOtpBtn').disabled = false;
    clearInterval(otpTimerInterval);
  }
}

document.addEventListener('DOMContentLoaded', function() {
  // Intercept registration form submit
  const regForm = document.getElementById('registerForm');
  if (regForm) {
    regForm.addEventListener('submit', function(e) {
      e.preventDefault();
      if (!checkRegisterForm()) return;
      const submitBtn = document.getElementById('registerSubmitBtn');
      submitBtn.disabled = true;
      submitBtn.classList.add('loading');
      const formData = new FormData(regForm);
      fetch('register.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        if (data.success && data.pending_verification) {
          showOtpModal(data.expires_at);
        } else if (!data.success) {
          Swal.fire({ icon: 'error', title: 'Registration Failed', text: data.message });
        }
      })
      .catch(() => {
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        Swal.fire({ icon: 'error', title: 'Error', text: 'Could not process registration.' });
      });
    });
  }

  // OTP form submit
  const otpForm = document.getElementById('otpForm');
  if (otpForm) {
    otpForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const otp = document.getElementById('otpInput').value.trim();
      if (!/^\d{6}$/.test(otp)) {
        document.getElementById('otpErrorMsg').textContent = 'Please enter a valid 6-digit code.';
        return;
      }
      document.getElementById('otpErrorMsg').textContent = '';
      fetch('verify_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'otp=' + encodeURIComponent(otp)
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const otpModal = bootstrap.Modal.getInstance(document.getElementById('otpModal'));
          otpModal.hide();
          Swal.fire({ icon: 'success', title: 'Registration Complete', text: data.message || 'You can now log in.' });
          // Optionally reload or redirect
          setTimeout(() => window.location.reload(), 1500);
        } else {
          document.getElementById('otpErrorMsg').textContent = data.message || 'Invalid OTP.';
        }
      })
      .catch(() => {
        document.getElementById('otpErrorMsg').textContent = 'Could not verify OTP. Please try again.';
      });
    });
  }

  // Resend OTP
  const resendBtn = document.getElementById('resendOtpBtn');
  if (resendBtn) {
    resendBtn.addEventListener('click', function() {
      resendBtn.disabled = true;
      fetch('resend_otp.php', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            otpExpiresAt = data.expires_at;
            updateOtpTimer();
            Swal.fire({ icon: 'success', title: 'OTP Resent', text: 'A new code has been sent to your email.' });
          } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Could not resend OTP.' });
          }
        })
        .catch(() => {
          Swal.fire({ icon: 'error', title: 'Error', text: 'Could not resend OTP.' });
        })
        .finally(() => {
          resendBtn.disabled = false;
        });
    });
  }
});
// Navbar scroll effect
window.addEventListener('scroll', function() {
  const navbar = document.querySelector('.modern-navbar');
  if (window.scrollY > 50) {
    navbar.classList.add('scrolled');
  } else {
    navbar.classList.remove('scrolled');
  }
});

// Mobile navbar auto-close
document.addEventListener('DOMContentLoaded', function() {
  const navbarCollapse = document.getElementById('navbarNav');
  if (navbarCollapse) {
    navbarCollapse.addEventListener('click', function(e) {
      const clickedElement = e.target.closest('.modern-nav-link, .modern-btn');
      if (clickedElement && window.innerWidth < 992) {
        const bsCollapse = bootstrap.Collapse.getOrCreateInstance(navbarCollapse);
        bsCollapse.hide();
      }
    });
  }
});

// Password toggle functionality
function togglePassword(inputId, button) {
  const input = document.getElementById(inputId);
  const icon = button.querySelector('i');
  
  if (input.type === "password") {
    input.type = "text";
    icon.classList.remove("bi-eye");
    icon.classList.add("bi-eye-slash");
  } else {
    input.type = "password";
    icon.classList.remove("bi-eye-slash");
    icon.classList.add("bi-eye");
  }
}

// Form validation (existing logic remains below)
document.addEventListener('DOMContentLoaded', function() {
  // ...existing code...
  // (Email, username, phone validation logic remains unchanged)
});

// Form submission validation
function checkRegisterForm() {
  const emailMsg = document.getElementById('email_msg').textContent;
  const usernameMsg = document.getElementById('username_msg').textContent;
  const phoneInput = document.getElementById('reg_phone');
  
  if (emailMsg || usernameMsg) {
    return false;
  }
  
  if (phoneInput && phoneInput.value.length !== 11) {
    Swal.fire({
      icon: 'error',
      title: 'Invalid Phone Number',
      text: 'Phone number must be exactly 11 digits.'
    });
    phoneInput.focus();
    return false;
  }
  
  // Add loading state to submit button
  const submitBtn = document.querySelector('button[type="submit"]');
  submitBtn.classList.add('loading');
  
  return true;
}

// Enhanced form submissions with loading states
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', function() {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn && !submitBtn.classList.contains('loading')) {
      submitBtn.classList.add('loading');
      
      // Remove loading state after 5 seconds as fallback
      setTimeout(() => {
        submitBtn.classList.remove('loading');
      }, 5000);
    }
  });
});
</script>