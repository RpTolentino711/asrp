<?php
require '../database/database.php';
require '../class.phpmailer.php';
require '../class.smtp.php';
session_start();

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

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
                transition: transform 0.2s;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            .cta-button:hover { transform: translateY(-2px); }
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
            .benefit-icon {
                color: #10b981;
                font-size: 20px;
                min-width: 24px;
            }
            .benefit-text {
                color: #374151;
                font-size: 14px;
                line-height: 1.6;
            }
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
            .important-note strong { color: #78350f; }
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

// $pending_requests = $db->getPendingRentalRequests();
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
        
        /* Client Info with Dynamic Tooltip Positioning */
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
        }
        
        /* Default: Show tooltip above (for most rows) */
        .client-tooltip {
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
        
        /* First row only: Show tooltip below */
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
        
        /* Button Styling */
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
        
        /* Status Badges */
        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.75rem;
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
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .action-buttons {
                display: flex;
                gap: 0.5rem;
            }
            
            .btn-action {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
            
            /* Mobile tooltip adjustments */
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
            
            /* Override the nth-child rule for mobile */
            .custom-table tr:nth-child(-n+2) .client-tooltip {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
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
                </a>
            </div>
            
            <div class="nav-item">
                <a href="manage_maintenance.php" class="nav-link">
                    <i class="fas fa-tools"></i>
                    <span>Maintenance</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="generate_invoice.php" class="nav-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
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
        
        <!-- Info Alert -->
        <div class="alert alert-info animate-fade-in">
            <i class="fas fa-info-circle me-2"></i>
            When you accept a rental request, the client will receive a welcome email with their account details, username, email, and assigned unit. They can then access their dashboard and view invoices.
        </div>
        
        <!-- Requests Table -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-list-alt"></i>
                <span>Pending Requests</span>
                <span class="badge bg-primary ms-2" id="pendingCount">0</span>
            </div>
            <div class="card-body p-0" id="pendingRequestsContainer">
                <!-- Pending requests table will be loaded here via AJAX -->
                <div class="empty-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <h4>Loading pending requests...</h4>
                    <p>Please wait while we fetch the latest data</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // --- LIVE ADMIN: AJAX Polling for Pending Rental Requests ---
    function fetchPendingRequests() {
        fetch('../AJAX/ajax_admin_pending_requests.php')
            .then(res => res.text())
            .then(html => {
                document.getElementById('pendingRequestsContainer').innerHTML = html;
                // Update count badge
                const match = html.match(/data-count="(\d+)"/);
                if (match) document.getElementById('pendingCount').textContent = match[1];
            })
            .catch(err => {
                console.error('Error fetching requests:', err);
                document.getElementById('pendingRequestsContainer').innerHTML = 
                    '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h4>Error loading requests</h4><p>Please refresh the page</p></div>';
            });
    }
    setInterval(fetchPendingRequests, 10000); // every 10s
    document.addEventListener('DOMContentLoaded', fetchPendingRequests);
    
    // SweetAlert for success/error messages
    <?php if (isset($_SESSION['admin_message'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= addslashes($_SESSION['admin_message']) ?>',
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['admin_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['admin_error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= addslashes($_SESSION['admin_error']) ?>',
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['admin_error']); ?>
    <?php endif; ?>
    
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
    </script>
</body>
</html>