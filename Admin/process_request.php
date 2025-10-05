<?php
// Require your single database file. Adjust path to go up one level.
require '../database/database.php';
require '../class.phpmailer.php';
require '../class.smtp.php';
session_start();

$db = new Database();

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// --- Authentication Check ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) { 
    header('Location: login.php'); 
    exit(); 
}

// --- Email notification function for rental acceptance ---
function sendRentalAcceptanceNotification($clientEmail, $clientFirstName, $clientLastName, $username, $unitName) {
    $mail = new PHPMailer;
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    
    $mail->Username = 'management@asrt.space';
    $mail->Password = '@Pogilameg10'; // Move to environment variable in production
    
    $mail->Timeout = 30;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ],
    ];
    
    $mail->setFrom($mail->Username, 'ASRT Spaces Management');
    $mail->addReplyTo('no-reply@asrt.space', 'ASRT Spaces');
    $mail->addAddress($clientEmail);
    
    $mail->isHTML(true);
    $mail->Subject = "Welcome to ASRT Spaces Community!";
    
    $safeName = htmlspecialchars($clientFirstName . ' ' . $clientLastName, ENT_QUOTES, 'UTF-8');
    $safeFirstName = htmlspecialchars($clientFirstName, ENT_QUOTES, 'UTF-8');
    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($clientEmail, ENT_QUOTES, 'UTF-8');
    $safeUnitName = htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8');
    $currentDate = date('F j, Y');
    
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .container { max-width: 600px; margin: 0 auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; }
            .header { 
                background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
                color: white; 
                padding: 40px 20px; 
                text-align: center; 
                border-radius: 12px 12px 0 0;
            }
            .logo { font-size: 28px; font-weight: bold; margin-bottom: 10px; }
            .subtitle { font-size: 18px; opacity: 0.95; }
            .emoji { font-size: 48px; margin-bottom: 15px; }
            .content { padding: 35px 25px; background: #f8fafc; }
            .welcome-box {
                background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
                border-left: 4px solid #10b981;
                padding: 25px;
                margin: 25px 0;
                border-radius: 0 8px 8px 0;
                box-shadow: 0 2px 10px rgba(16, 185, 129, 0.15);
            }
            .welcome-title { 
                font-weight: bold; 
                color: #065f46; 
                margin-bottom: 12px; 
                font-size: 20px;
            }
            .welcome-message { color: #047857; font-size: 15px; line-height: 1.7; }
            .account-details {
                background: white;
                border: 2px solid #10b981;
                border-radius: 12px;
                padding: 25px;
                margin: 25px 0;
                box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            }
            .detail-header {
                color: #10b981;
                font-weight: bold;
                font-size: 18px;
                margin-bottom: 20px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .detail-row { 
                display: flex; 
                justify-content: space-between; 
                margin-bottom: 12px;
                padding: 12px;
                background: #f9fafb;
                border-radius: 6px;
            }
            .detail-row:last-child { margin-bottom: 0; }
            .detail-label { color: #64748b; font-weight: 500; }
            .detail-value { color: #1e293b; font-weight: 600; }
            .cta-section {
                background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
                color: white;
                padding: 30px;
                border-radius: 12px;
                text-align: center;
                margin: 30px 0;
            }
            .cta-title { font-size: 20px; font-weight: bold; margin-bottom: 12px; }
            .cta-description { font-size: 15px; margin-bottom: 20px; opacity: 0.95; }
            .cta-button {
                display: inline-block;
                background: white;
                color: #6366f1;
                padding: 14px 30px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
                margin: 8px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            .benefits-section {
                background: white;
                border-radius: 10px;
                padding: 25px;
                margin: 25px 0;
                border: 1px solid #e5e7eb;
            }
            .benefits-title {
                color: #1e293b;
                font-weight: bold;
                font-size: 18px;
                margin-bottom: 20px;
            }
            .benefit-item {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                margin-bottom: 15px;
                padding: 12px;
                background: #f9fafb;
                border-radius: 8px;
            }
            .benefit-item:last-child { margin-bottom: 0; }
            .benefit-icon { color: #10b981; font-size: 20px; min-width: 24px; }
            .benefit-text { color: #374151; font-size: 14px; line-height: 1.6; }
            .footer { 
                padding: 25px 20px; 
                text-align: center; 
                background: #1e293b; 
                color: #94a3b8;
                border-radius: 0 0 12px 12px;
            }
            .footer h3 { color: white; margin-bottom: 15px; font-size: 18px; }
            .footer p { margin: 6px 0; font-size: 13px; }
            .support-info { color: #60a5fa; font-weight: 500; }
            .important-note {
                background: #fef3c7;
                border: 1px solid #f59e0b;
                padding: 18px;
                margin: 20px 0;
                border-radius: 8px;
                color: #92400e;
                font-size: 14px;
            }
            @media (max-width: 600px) {
                .container { margin: 10px; }
                .content, .header, .footer { padding: 20px 15px; }
                .detail-row { flex-direction: column; }
                .detail-value { margin-top: 5px; }
                .cta-button { display: block; margin: 10px 0; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='emoji'>🎉</div>
                <div class='logo'>ASRT Spaces</div>
                <div class='subtitle'>Your Rental Request Has Been Approved!</div>
            </div>
            
            <div class='content'>
                <div class='welcome-box'>
                    <div class='welcome-title'>Welcome to the ASRT Community, {$safeFirstName}!</div>
                    <div class='welcome-message'>
                        We are thrilled to inform you that your rental request has been approved! 
                        You are now officially part of the ASRT Spaces family. Your journey to a comfortable 
                        and convenient living experience starts here.
                    </div>
                </div>
                
                <div class='account-details'>
                    <div class='detail-header'>📋 Your Account Details</div>
                    <div class='detail-row'>
                        <span class='detail-label'>Full Name:</span>
                        <span class='detail-value'>{$safeName}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Username:</span>
                        <span class='detail-value'>{$safeUsername}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Email:</span>
                        <span class='detail-value'>{$safeEmail}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Assigned Unit:</span>
                        <span class='detail-value'>{$safeUnitName}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Approval Date:</span>
                        <span class='detail-value'>{$currentDate}</span>
                    </div>
                </div>
                
                <div class='cta-section'>
                    <div class='cta-title'>Ready to Get Started?</div>
                    <div class='cta-description'>Access your personalized dashboard and view your invoice details</div>
                    <a href='https://asrt.space/user/dashboard.php' class='cta-button'>🏠 Go to Dashboard</a>
                    <a href='https://asrt.space/user/view_invoice.php' class='cta-button'>📄 View Invoice</a>
                </div>
                
                <div class='benefits-section'>
                    <div class='benefits-title'>✨ What You Can Do Now:</div>
                    <div class='benefit-item'>
                        <div class='benefit-icon'>💰</div>
                        <div class='benefit-text'>
                            <strong>View & Pay Invoices:</strong> Access your rental invoices and make payments conveniently through your dashboard.
                        </div>
                    </div>
                    <div class='benefit-item'>
                        <div class='benefit-icon'>💬</div>
                        <div class='benefit-text'>
                            <strong>Chat with Admin:</strong> Communicate directly with our management team for any questions or concerns.
                        </div>
                    </div>
                    <div class='benefit-item'>
                        <div class='benefit-icon'>🔧</div>
                        <div class='benefit-text'>
                            <strong>Request Maintenance:</strong> Submit maintenance requests and track their status in real-time.
                        </div>
                    </div>
                    <div class='benefit-item'>
                        <div class='benefit-icon'>📊</div>
                        <div class='benefit-text'>
                            <strong>Track Your Rental:</strong> View your rental history, payment records, and important dates.
                        </div>
                    </div>
                    <div class='benefit-item'>
                        <div class='benefit-icon'>🔔</div>
                        <div class='benefit-text'>
                            <strong>Get Notifications:</strong> Receive email updates about payments, messages, and important announcements.
                        </div>
                    </div>
                </div>
                
                <div class='important-note'>
                    <strong>📌 Important Next Steps:</strong><br>
                    1. Log in to your dashboard using your username and password<br>
                    2. Review your invoice and payment terms<br>
                    3. Complete your first payment before the due date<br>
                    4. Update your profile information if needed
                </div>
                
                <div style='text-align: center; margin: 30px 0; padding: 20px; background: #f0fdf4; border-radius: 10px; border: 1px solid #10b981;'>
                    <p style='color: #065f46; font-size: 15px; margin: 0;'>
                        <strong>Need Help?</strong> Our support team is here for you! 
                        Contact us anytime at <span style='color: #10b981; font-weight: 600;'>management@asrt.space</span>
                    </p>
                </div>
            </div>
            
            <div class='footer'>
                <h3>ASRT Spaces</h3>
                <p>Thank you for choosing ASRT Spaces as your home. We're committed to providing you with the best rental experience.</p>
                <p>Questions? Contact us at <span class='support-info'>management@asrt.space</span></p>
                <p style='margin-top: 15px; font-size: 11px;'>© 2025 ASRT Spaces. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    $mail->AltBody = "Welcome to ASRT Spaces Community!\n\n"
        . "Hello {$safeName},\n\n"
        . "Congratulations! Your rental request has been approved.\n\n"
        . "Account Details:\n"
        . "- Full Name: {$safeName}\n"
        . "- Username: {$safeUsername}\n"
        . "- Email: {$safeEmail}\n"
        . "- Assigned Unit: {$safeUnitName}\n"
        . "- Approval Date: {$currentDate}\n\n"
        . "You can now:\n"
        . "- View and pay your invoices\n"
        . "- Chat with admin\n"
        . "- Request maintenance\n"
        . "- Track your rental history\n\n"
        . "Login to your dashboard: https://asrt.space/user/dashboard.php\n"
        . "View your invoice: https://asrt.space/user/view_invoice.php\n\n"
        . "Need help? Contact: management@asrt.space\n\n"
        . "Best regards,\n"
        . "ASRT Spaces Management Team";
    
    return $mail->send();
}

// --- Handle POST Request ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];

    if ($action == 'accept') {
        // Get request details before accepting (for email notification)
        $requestDetails = $db->getRentalRequestDetails($request_id);
        
        // Call the single, transactional method to accept the request
        if ($db->acceptRentalRequest($request_id)) {
            $_SESSION['admin_message'] = "Request #{$request_id} has been successfully approved and an invoice was generated.";
            
            // Send email notification if we have the client details
            if ($requestDetails && !empty($requestDetails['Client_Email'])) {
                try {
                    $emailSent = sendRentalAcceptanceNotification(
                        $requestDetails['Client_Email'],
                        $requestDetails['Client_fn'] ?? 'User',
                        $requestDetails['Client_ln'] ?? '',
                        $requestDetails['Username'] ?? $requestDetails['Client_Email'],
                        $requestDetails['UnitName'] ?? 'Your Unit'
                    );
                    
                    if ($emailSent) {
                        $_SESSION['admin_message'] .= " Welcome email sent to client.";
                        error_log("Welcome email sent to: " . $requestDetails['Client_Email'] . " for request ID: " . $request_id);
                    } else {
                        error_log("Failed to send welcome email to: " . $requestDetails['Client_Email'] . " for request ID: " . $request_id);
                    }
                } catch (Exception $e) {
                    error_log("Error sending welcome email: " . $e->getMessage());
                }
            }
        } else {
            $_SESSION['admin_error'] = "Failed to approve request #{$request_id}. It may have already been processed, or a database error occurred.";
        }
    } elseif ($action == 'reject') {
        // Call the method to reject the request
        if ($db->rejectRentalRequest($request_id)) {
            $_SESSION['admin_message'] = "Request #{$request_id} has been rejected.";
        } else {
            $_SESSION['admin_error'] = "Failed to reject request #{$request_id}.";
        }
    }
}

// Redirect back to the requests list to show the result
header('Location: view_rental_requests.php');
exit();
?>