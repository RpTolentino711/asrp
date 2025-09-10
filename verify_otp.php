<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database/database.php';

// --- DEBUG LOGGING, REMOVE IN PRODUCTION ---
error_log("verify_otp.php called: POST=" . json_encode($_POST));
error_log("verify_otp.php SESSION=" . json_encode($_SESSION));

// Check for session data
if (!isset($_SESSION['otp']) || !isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid. Please register again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp']);
    if (!preg_match('/^\d{6}$/', $entered_otp)) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP format.']);
        exit;
    }
    if (isset($_SESSION['otp_expires']) && time() > $_SESSION['otp_expires']) {
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please resend the code.']);
        exit;
    }
    if ($entered_otp === $_SESSION['otp']) {
        // Register user in the database
        $data = $_SESSION['pending_registration'];
        $db = new Database();
        $pdo = $db->pdo ?? (method_exists($db, 'opencon') ? $db->opencon() : null);
        $success = false;

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO client (Client_Fname, Client_Lname, Client_Email, Client_Contact, C_username, C_password, Created_At) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([
                    $data['fname'],
                    $data['lname'],
                    $data['email'],
                    $data['phone'],
                    $data['username'],
                    $data['password'], // This is already hashed!
                    $data['created_at']
                ]);
            } catch (PDOException $e) {
                error_log('DB ERROR in verify_otp.php: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                // Clean up partial session anyway
                unset($_SESSION['otp'], $_SESSION['pending_registration'], $_SESSION['otp_email'], $_SESSION['otp_expires'], $_SESSION['otp_attempts']);
                exit;
            }
        }

        // Clean up sessions
        unset($_SESSION['otp'], $_SESSION['pending_registration'], $_SESSION['otp_email'], $_SESSION['otp_expires'], $_SESSION['otp_attempts']);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Registration successful! You can now log in.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error. Please try registering again or contact support.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
    }
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit;