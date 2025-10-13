<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../database/database.php';
require '../class.phpmailer.php';
require '../class.smtp.php';
session_start();

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

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

// Get counts for sidebar badges
$rental_count = $db->getRow("SELECT COUNT(*) as count FROM rentalrequest WHERE Status = 'Pending' AND Flow_Status = 'new'")['count'];
$maintenance_count = $db->getRow("SELECT COUNT(*) as count FROM maintenancerequest WHERE Status = 'Submitted'")['count'];
$chat_count = $db->getRow("SELECT COUNT(*) as count FROM invoice_chat WHERE Sender_Type = 'client' AND is_read_admin = 0")['count'];

// MARK ALL PENDING REQUESTS AS SEEN WHEN ADMIN VIEWS THE PAGE
$db->executeStatement(
    "UPDATE rentalrequest SET admin_seen = 1 WHERE Status = 'Pending' AND admin_seen = 0"
);

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// --- Email notification function for rental acceptance ---
function sendRentalAcceptanceEmail($clientEmail, $clientFirstName, $spaceName, $startDate, $endDate, $monthlyRent) {
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
    
    $mail->setFrom($mail->Username, 'ASRT Spaces');
    $mail->addReplyTo('management@asrt.space', 'ASRT Spaces Management');
    $mail->addAddress($clientEmail);
    
    $mail->isHTML(true);
    $mail->Subject = "Welcome to ASRT Community! Your Rental Request Has Been Approved";
    
    $safeName = htmlspecialchars($clientFirstName, ENT_QUOTES, 'UTF-8');
    $safeSpaceName = htmlspecialchars($spaceName, ENT_QUOTES, 'UTF-8');
    $safeStartDate = htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8');
    $safeEndDate = htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8');
    $safeMonthlyRent = number_format($monthlyRent, 2);
    $approvalTime = date('F j, Y \a\t g:i A T');
    
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .container { 
                max-width: 650px; 
                margin: 0 auto; 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                background: #f8fafc;
                padding: 20px;
            }
            .email-wrapper {
                background: white;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            }
            .header { 
                background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
                color: white; 
                padding: 40px 30px; 
                text-align: center; 
            }
            .logo { 
                font-size: 32px; 
                font-weight: bold; 
                margin-bottom: 12px;
                letter-spacing: 1px;
            }
            .subtitle { 
                font-size: 18px; 
                opacity: 0.95;
                font-weight: 500;
            }
            .welcome-badge {
                display: inline-block;
                background: rgba(255, 255, 255, 0.2);
                padding: 8px 20px;
                border-radius: 30px;
                margin-top: 15px;
                font-size: 14px;
                font-weight: 600;
                letter-spacing: 1px;
                text-transform: uppercase;
            }
            .content { 
                padding: 40px 30px;
            }
            .greeting { 
                font-size: 24px; 
                margin-bottom: 20px; 
                color: #1e293b; 
                font-weight: 700;
            }
            .welcome-message {
                background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
                border-left: 5px solid #10b981;
                padding: 25px;
                margin: 25px 0;
                border-radius: 0 12px 12px 0;
                box-shadow: 0 2px 10px rgba(16, 185, 129, 0.1);
            }
            .welcome-title { 
                font-weight: bold; 
                color: #065f46; 
                margin-bottom: 12px; 
                font-size: 18px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .check-icon {
                display: inline-block;
                width: 24px;
                height: 24px;
                background: #10b981;
                border-radius: 50%;
                color: white;
                text-align: center;
                line-height: 24px;
                font-weight: bold;
            }
            .welcome-text {
                color: #047857;
                font-size: 15px;
                line-height: 1.7;
            }
            .rental-details {
                background: white;
                border: 2px solid #e2e8f0;
                border-radius: 12px;
                padding: 25px;
                margin: 25px 0;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            .detail-header {
                font-size: 18px;
                font-weight: bold;
                color: #10b981;
                margin-bottom: 20px;
                padding-bottom: 12px;
                border-bottom: 2px solid #10b981;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .detail-row { 
                display: flex; 
                justify-content: space-between; 
                padding: 12px 0;
                border-bottom: 1px solid #f1f5f9;
                align-items: center;
            }
            .detail-row:last-child { 
                border-bottom: none; 
                padding-bottom: 0;
            }
            .detail-label { 
                color: #64748b; 
                font-weight: 600;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .detail-value { 
                color: #1e293b; 
                font-weight: 700;
                font-size: 16px;
                text-align: right;
            }
            .price-highlight {
                color: #10b981;
                font-size: 20px;
            }
            .welcome-benefits {
                background: #fefce8;
                border-left: 5px solid #f59e0b;
                padding: 25px;
                margin: 25px 0;
                border-radius: 0 12px 12px 0;
            }
            .benefits-title {
                font-weight: bold;
                color: #92400e;
                margin-bottom: 15px;
                font-size: 16px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .benefit-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            .benefit-item {
                padding: 10px 0;
                color: #78350f;
                font-size: 14px;
                display: flex;
                align-items: start;
                gap: 12px;
            }
            .benefit-icon {
                color: #f59e0b;
                font-weight: bold;
                font-size: 18px;
                flex-shrink: 0;
            }
            .next-steps {
                background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
                border-left: 5px solid #3b82f6;
                padding: 25px;
                margin: 25px 0;
                border-radius: 0 12px 12px 0;
            }
            .next-steps-title {
                font-weight: bold;
                color: #1e40af;
                margin-bottom: 15px;
                font-size: 16px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .step-item {
                padding: 10px 0;
                color: #1e40af;
                font-size: 14px;
                display: flex;
                align-items: start;
                gap: 12px;
            }
            .step-number {
                background: #3b82f6;
                color: white;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 12px;
                flex-shrink: 0;
            }
            .cta-section {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                padding: 30px;
                border-radius: 12px;
                text-align: center;
                margin: 30px 0;
            }
            .cta-title { 
                font-size: 22px; 
                font-weight: bold; 
                margin-bottom: 12px;
            }
            .cta-text {
                font-size: 15px;
                opacity: 0.95;
                margin-bottom: 20px;
            }
            .cta-button {
                display: inline-block;
                background: white;
                color: #10b981;
                padding: 14px 32px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
                margin-top: 10px;
                transition: transform 0.2s;
                font-size: 16px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }
            .footer { 
                padding: 30px; 
                text-align: center; 
                background: #1e293b; 
                color: #94a3b8;
            }
            .footer h3 { 
                color: white; 
                margin-bottom: 15px; 
                font-size: 20px;
            }
            .footer p { 
                margin: 8px 0; 
                font-size: 13px;
                line-height: 1.6;
            }
            .contact-info {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }
            .support-info { 
                color: #10b981; 
                font-weight: 600;
            }
            .timestamp { 
                font-size: 12px; 
                color: #64748b; 
                margin-top: 25px; 
                font-style: italic;
                text-align: center;
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
            }
            @media (max-width: 600px) {
                .container { 
                    padding: 10px;
                }
                .content, .header, .footer { 
                    padding: 25px 20px;
                }
                .detail-row { 
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 5px;
                }
                .detail-value { 
                    text-align: left;
                }
                .greeting {
                    font-size: 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='email-wrapper'>
                <div class='header'>
                    <div class='logo'>🏠 ASRT Spaces</div>
                    <div class='subtitle'>Property Management Excellence</div>
                    <div class='welcome-badge'>✓ Rental Approved</div>
                </div>
                
                <div class='content'>
                    <div class='greeting'>Welcome, {$safeName}!</div>
                    
                    <div class='welcome-message'>
                        <div class='welcome-title'>
                            <span class='check-icon'>✓</span>
                            <span>Congratulations! You're Now Part of the ASRT Community</span>
                        </div>
                        <div class='welcome-text'>
                            We are thrilled to welcome you to our community! Your rental request has been approved, and we're excited to have you as our valued tenant. At ASRT Spaces, we're committed to providing you with excellent service and a comfortable living experience.
                        </div>
                    </div>
                    
                    <div class='rental-details'>
                        <div class='detail-header'>📋 Your Rental Details</div>
                        <div class='detail-row'>
                            <span class='detail-label'>Property:</span>
                            <span class='detail-value'>{$safeSpaceName}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Lease Start:</span>
                            <span class='detail-value'>{$safeStartDate}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Lease End:</span>
                            <span class='detail-value'>{$safeEndDate}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Monthly Rent:</span>
                            <span class='detail-value price-highlight'>₱{$safeMonthlyRent}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Approval Date:</span>
                            <span class='detail-value'>{$approvalTime}</span>
                        </div>
                    </div>
                    
                    <div class='welcome-benefits'>
                        <div class='benefits-title'>🌟 Your Benefits as an ASRT Tenant</div>
                        <ul class='benefit-list'>
                            <li class='benefit-item'>
                                <span class='benefit-icon'>✓</span>
                                <span><strong>24/7 Support:</strong> Our management team is always available to assist you</span>
                            </li>
                            <li class='benefit-item'>
                                <span class='benefit-icon'>✓</span>
                                <span><strong>Easy Maintenance Requests:</strong> Submit and track repair requests through your account</span>
                            </li>
                            <li class='benefit-item'>
                                <span class='benefit-icon'>✓</span>
                                <span><strong>Real-Time Chat:</strong> Communicate directly with management for any concerns</span>
                            </li>
                            <li class='benefit-item'>
                                <span class='benefit-icon'>✓</span>
                                <span><strong>Online Payments:</strong> Convenient invoice tracking and payment system</span>
                            </li>
                            <li class='benefit-item'>
                                <span class='benefit-icon'>✓</span>
                                <span><strong>Professional Service:</strong> Experienced handymen for quick repairs</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class='next-steps'>
                        <div class='next-steps-title'>📝 Next Steps</div>
                        <div class='step-item'>
                            <span class='step-number'>1</span>
                            <span>Log in to your account to view your rental details and first invoice</span>
                        </div>
                        <div class='step-item'>
                            <span class='step-number'>2</span>
                            <span>Review your lease agreement and payment schedule</span>
                        </div>
                        <div class='step-item'>
                            <span class='step-number'>3</span>
                            <span>Contact us if you have any questions or need assistance</span>
                        </div>
                        <div class='step-item'>
                            <span class='step-number'>4</span>
                            <span>Prepare for your move-in on the scheduled start date</span>
                        </div>
                    </div>
                    
                    <div class='cta-section'>
                        <div class='cta-title'>Ready to Get Started?</div>
                        <div class='cta-text'>Log in to your ASRT Spaces account to access all your rental information and start using our services.</div>
                        <a href='https://asrt.space' class='cta-button'>Access Your Account</a>
                    </div>
                    
                    <div class='timestamp'>Welcome email sent: {$approvalTime}</div>
                </div>
                
                <div class='footer'>
                    <h3>ASRT Spaces</h3>
                    <p>Thank you for choosing ASRT Spaces as your property management partner.</p>
                    <p>We're committed to making your rental experience exceptional.</p>
                    
                    <div class='contact-info'>
                        <p><strong>Need Help?</strong></p>
                        <p>Contact us at <span class='support-info'>management@asrt.space</span></p>
                        <p>General Luna Street, Barangay 10, Lipa City</p>
                    </div>
                    
                    <p style='margin-top: 20px; font-size: 11px; opacity: 0.8;'>
                        © 2025 ASRT Spaces. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    $mail->AltBody = "Welcome to ASRT Community, {$safeName}!\n\n" .
        "Congratulations! Your rental request has been APPROVED.\n\n" .
        "RENTAL DETAILS:\n" .
        "Property: {$safeSpaceName}\n" .
        "Lease Start: {$safeStartDate}\n" .
        "Lease End: {$safeEndDate}\n" .
        "Monthly Rent: ₱{$safeMonthlyRent}\n" .
        "Approval Date: {$approvalTime}\n\n" .
        "YOUR BENEFITS:\n" .
        "• 24/7 Support from our management team\n" .
        "• Easy maintenance request system\n" .
        "• Real-time chat with management\n" .
        "• Online payment and invoice tracking\n" .
        "• Professional handyman services\n\n" .
        "NEXT STEPS:\n" .
        "1. Log in to your account to view details\n" .
        "2. Review your lease agreement\n" .
        "3. Contact us with any questions\n" .
        "4. Prepare for your move-in date\n\n" .
        "Log in to your account at: https://asrt.space\n\n" .
        "Thank you for choosing ASRT Spaces!\n\n" .
        "Need help? Contact: management@asrt.space\n" .
        "ASRT Spaces - Property Management Excellence";
    
    return $mail->send();
}

// Handle rental request acceptance
if (isset($_POST['accept_request'])) {
    $requestId = intval($_POST['request_id']);
    
    // Get request details before accepting
    $requestDetails = $db->getRentalRequestById($requestId);
    
    if ($requestDetails) {
        // Accept the request
        $result = $db->acceptRentalRequest($requestId);
        
        if ($result) {
            // Try to send welcome email
            try {
                $emailSent = sendRentalAcceptanceEmail(
                    $requestDetails['Client_Email'],
                    $requestDetails['Client_fn'] ?: 'User',
                    $requestDetails['SpaceName'] ?: 'Your Unit',
                    $requestDetails['StartDate'],
                    $requestDetails['EndDate'],
                    $requestDetails['Price'] ?: 0
                );
                
                if ($emailSent) {
                    $_SESSION['admin_message'] = 'Rental request accepted successfully! Welcome email sent to client.';
                    error_log("Welcome email sent to: " . $requestDetails['Client_Email'] . " for request ID: " . $requestId);
                } else {
                    $_SESSION['admin_message'] = 'Rental request accepted successfully! (Email notification failed)';
                    error_log("Failed to send welcome email to: " . $requestDetails['Client_Email'] . " for request ID: " . $requestId);
                }
            } catch (Exception $e) {
                $_SESSION['admin_message'] = 'Rental request accepted successfully! (Email error: ' . $e->getMessage() . ')';
                error_log("Error sending welcome email: " . $e->getMessage());
            }
        } else {
            $_SESSION['admin_error'] = 'Failed to accept rental request. Please try again.';
        }
    } else {
        $_SESSION['admin_error'] = 'Rental request not found.';
    }
    
    header('Location: view_rental_requests.php');
    exit();
}

// Handle rental request rejection
if (isset($_POST['reject_request'])) {
    $requestId = intval($_POST['request_id']);
    $result = $db->rejectRentalRequest($requestId);
    
    if ($result) {
        $_SESSION['admin_message'] = 'Rental request rejected successfully.';
    } else {
        $_SESSION['admin_error'] = 'Failed to reject rental request. Please try again.';
    }
    
    header('Location: view_rental_requests.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Rental Requests | ASRT Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: var(--transition);
        }
        
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
        
        .table-container {
            overflow-x: auto;
            border-radius: var(--border-radius);
        }
        
        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .custom-table th {
            background-color: #f9fafb;
            padding: 0.75rem 1rem;
            font-weight: 600;
            text-align: left;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .custom-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        
        .custom-table tr:last-child td {
            border-bottom: none;
        }
        
        .custom-table tr:hover {
            background-color: #f9fafb;
        }
        
        .client-info {
            position: relative;
            cursor: help;
            transition: var(--transition);
        }
        
        .client-info:hover {
            color: var(--primary);
        }
        
        .client-tooltip {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 250px;
            pointer-events: none;
            bottom: 100%;
            margin-bottom: 8px;
        }
        
        .client-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: var(--dark);
        }
        
        .custom-table tr:first-child .client-tooltip {
            bottom: auto;
            top: 100%;
            margin-top: 8px;
            margin-bottom: 0;
        }
        
        .custom-table tr:first-child .client-tooltip::after {
            top: auto;
            bottom: 100%;
            border-top-color: transparent;
            border-bottom-color: var(--dark);
        }
        
        .client-info:hover .client-tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }
        
        .contact-item:last-child {
            margin-bottom: 0;
        }
        
        .contact-item i {
            width: 14px;
            color: var(--info);
        }
        
        .request-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            color: #d1d5db;
            font-size: 0.8rem;
        }
        
        .request-date i {
            color: var(--warning);
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-accept {
            background: var(--secondary);
            color: white;
            border: none;
        }
        
        .btn-accept:hover {
            background: #0da271;
            color: white;
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
            border: none;
        }
        
        .btn-reject:hover {
            background: #dc2626;
            color: white;
        }
        
        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
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
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
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
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                min-width: 120px;
            }
        }
        
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
            }
            
            .btn-action {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
            
            .client-tooltip {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                max-width: 280px;
                white-space: normal;
                text-align: center;
                margin: 0;
            }
            
            .client-tooltip::after {
                display: none;
            }
            
            .custom-table tr:nth-child(-n+2) .client-tooltip {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        #pendingRequestsContainer {
            min-height: 200px;
            position: relative;
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
                <a href="manage_user.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Manage Users & Units</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="view_rental_requests.php" class="nav-link active">
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

    <div class="main-content">
        <div class="dashboard-header">
            <div class="page-title">
                <div class="title-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h1>Pending Rental Requests</h1>
                    <p class="text-muted mb-0">Review and manage rental requests from clients</p>
                </div>
            </div>
        </div>
        
        <?php
        if (isset($_SESSION['admin_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    ' . htmlspecialchars($_SESSION['admin_message']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            unset($_SESSION['admin_message']);
        }
        if (isset($_SESSION['admin_error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ' . htmlspecialchars($_SESSION['admin_error']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            unset($_SESSION['admin_error']);
        }
        ?>
        
        <div class="alert alert-info animate-fade-in">
            <i class="fas fa-envelope me-2"></i>
            When you accept a rental request, the client will automatically receive a beautifully styled welcome email with their rental details and benefits.
        </div>
        
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-list-alt"></i>
                <span>Pending Requests</span>
                <span class="badge bg-primary ms-2" id="pendingCount">0</span>
            </div>
            <div class="card-body p-0" id="pendingRequestsContainer">
                <div class="empty-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <h4>Loading requests...</h4>
                </div>
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
    let isLoading = false;

    // Debug logging
    console.log('Rental Requests initialized');
    console.log('Initial counts - Unseen Rentals: <?= $unseen_rentals ?>, New Maintenance: <?= $new_maintenance_requests ?>, Unread Messages: <?= $unread_client_messages ?>');

    // Tab visibility handling
    document.addEventListener('visibilitychange', function() {
        isTabActive = !document.hidden;
        console.log('Tab visibility changed:', isTabActive ? 'active' : 'hidden');
        if (isTabActive) {
            fetchDashboardCounts();
            if (!isLoading) {
                fetchPendingRequests();
            }
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

    function fetchPendingRequests() {
        if (isLoading) return;
        
        isLoading = true;
        
        fetch('../AJAX/ajax_admin_pending_requests.php')
            .then(res => res.text())
            .then(html => {
                document.getElementById('pendingRequestsContainer').innerHTML = html;
                const container = document.getElementById('pendingRequestsContainer');
                const countElement = container.querySelector('[data-count]');
                if (countElement) {
                    document.getElementById('pendingCount').textContent = countElement.getAttribute('data-count');
                }
                isLoading = false;
            })
            .catch(err => {
                console.error('Error fetching requests:', err);
                document.getElementById('pendingRequestsContainer').innerHTML = 
                    '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h4>Error loading requests</h4></div>';
                isLoading = false;
            });
    }

    function confirmAccept(requestId, clientName, spaceName) {
        Swal.fire({
            title: 'Accept Rental Request?',
            html: `
                <p>You are about to accept the rental request from:</p>
                <p class="fw-bold">${clientName}</p>
                <p>For property: <span class="fw-bold">${spaceName}</span></p>
                <p class="text-muted mt-3">
                    <i class="fas fa-envelope me-2"></i>
                    A welcome email will be automatically sent to the client.
                </p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Accept & Send Welcome Email',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('acceptForm_' + requestId).submit();
            }
        });
    }
    
    function confirmReject(requestId, clientName, spaceName) {
        Swal.fire({
            title: 'Reject Rental Request?',
            html: `
                <p>You are about to reject the rental request from:</p>
                <p class="fw-bold">${clientName}</p>
                <p>For property: <span class="fw-bold">${spaceName}</span></p>
                <p class="text-danger mt-3">This action cannot be undone.</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-times me-2"></i>Reject Request',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('rejectForm_' + requestId).submit();
            }
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

    // Load immediately, then every 30 seconds (instead of 10)
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Rental Requests fully loaded with ENHANCED notification system');
        console.log('Test notifications with: testNotification("rental") or testNotification("maintenance") or testNotification("client_message")');
        
        fetchDashboardCounts();
        fetchPendingRequests();
        
        // Poll every 5 seconds for counts (faster response)
        setInterval(() => {
            if (isTabActive) {
                fetchDashboardCounts();
            }
        }, 5000);
        
        // Poll every 30 seconds for requests
        setInterval(function() {
            if (isTabActive && !isLoading) {
                fetchPendingRequests();
            }
        }, 30000); // 30 seconds instead of 10
    });
    
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
    </script>
</body>
</html>