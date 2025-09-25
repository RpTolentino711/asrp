<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../database/database.php';
require '../class.phpmailer.php';
require '../class.smtp.php';
session_start();

$db = new Database();

// Set Philippine timezone for accurate timestamps
date_default_timezone_set('Asia/Manila');

// --- Email notification function for admin messages ---
function sendAdminMessageNotification($clientEmail, $clientFirstName, $adminMessage, $invoiceId, $unitName) {
    $mail = new PHPMailer;
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    
    $mail->Username = 'management@asrt.space';
    $mail->Password = '@Pogilameg10'; // Move to environment variable
    
    $mail->Timeout = 30;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ],
    ];
    
    $mail->setFrom($mail->Username, 'ASRT Spaces Admin');
    $mail->addReplyTo('no-reply@asrt.space', 'ASRT Spaces');
    $mail->addAddress($clientEmail);
    
    $mail->isHTML(true);
    $mail->Subject = "New Message from ASRT Spaces Admin - Invoice #" . $invoiceId;
    
    $safeName = htmlspecialchars($clientFirstName, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($adminMessage, ENT_QUOTES, 'UTF-8');
    $safeUnitName = htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8');
    $messageTime = date('F j, Y \a\t g:i A T');
    
    // Truncate message for email preview (show first 100 characters)
    $messagePreview = strlen($adminMessage) > 100 ? substr($adminMessage, 0, 100) . '...' : $adminMessage;
    $safeMessagePreview = htmlspecialchars($messagePreview, ENT_QUOTES, 'UTF-8');
    
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .container { max-width: 600px; margin: 0 auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; }
            .header { 
                background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
                border-radius: 12px 12px 0 0;
            }
            .logo { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
            .subtitle { font-size: 16px; opacity: 0.9; }
            .content { padding: 30px; background: #f8fafc; }
            .greeting { font-size: 18px; margin-bottom: 20px; color: #1e293b; font-weight: 600; }
            .notification-box {
                background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 100%);
                border-left: 4px solid #0ea5e9;
                padding: 20px;
                margin: 25px 0;
                border-radius: 0 8px 8px 0;
                box-shadow: 0 2px 10px rgba(14, 165, 233, 0.1);
            }
            .notification-title { font-weight: bold; color: #0c4a6e; margin-bottom: 10px; font-size: 16px; }
            .invoice-details {
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 15px;
                margin: 15px 0;
            }
            .detail-row { 
                display: flex; 
                justify-content: space-between; 
                margin-bottom: 8px;
                padding: 5px 0;
                border-bottom: 1px solid #f1f5f9;
            }
            .detail-row:last-child { border-bottom: none; margin-bottom: 0; }
            .detail-label { color: #64748b; font-weight: 500; }
            .detail-value { color: #1e293b; font-weight: 600; }
            .message-box {
                background: white;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .message-header { 
                color: #6366f1; 
                font-weight: bold; 
                margin-bottom: 15px;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .admin-message { 
                color: #374151; 
                font-size: 15px; 
                line-height: 1.6;
                white-space: pre-wrap;
                word-wrap: break-word;
            }
            .cta-section {
                background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
                color: white;
                padding: 25px;
                border-radius: 8px;
                text-align: center;
                margin: 25px 0;
            }
            .cta-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
            .cta-button {
                display: inline-block;
                background: white;
                color: #6366f1;
                padding: 12px 25px;
                text-decoration: none;
                border-radius: 6px;
                font-weight: bold;
                margin-top: 15px;
                transition: transform 0.2s;
            }
            .cta-button:hover { transform: translateY(-2px); }
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
            .timestamp { font-size: 12px; color: #64748b; margin-top: 20px; font-style: italic; }
            @media (max-width: 600px) {
                .container { margin: 10px; }
                .content, .header, .footer { padding: 20px 15px; }
                .detail-row { flex-direction: column; }
                .detail-value { margin-top: 5px; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>ASRT Spaces</div>
                <div class='subtitle'>New Message from Admin</div>
            </div>
            
            <div class='content'>
                <div class='greeting'>Hello {$safeName}!</div>
                
                <div class='notification-box'>
                    <div class='notification-title'>You have a new message from our admin team</div>
                    <p>Our admin team has sent you a message regarding your rental invoice. Please log in to your account to view the full conversation and respond if needed.</p>
                </div>
                
                <div class='invoice-details'>
                    <div class='detail-row'>
                        <span class='detail-label'>Invoice ID:</span>
                        <span class='detail-value'>#{$invoiceId}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Unit:</span>
                        <span class='detail-value'>{$safeUnitName}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Message Time:</span>
                        <span class='detail-value'>{$messageTime}</span>
                    </div>
                </div>
                
                <div class='message-box'>
                    <div class='message-header'>Admin Message Preview:</div>
                    <div class='admin-message'>{$safeMessagePreview}</div>
                </div>
                
                <div class='cta-section'>
                    <div class='cta-title'>Ready to respond?</div>
                    <p>Log in to your ASRT Spaces account to view the full message and continue the conversation.</p>
                    <a href='#' class='cta-button'>View Full Message</a>
                </div>
                
                <div style='background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 6px; color: #92400e; font-size: 14px;'>
                    <strong>Quick Tip:</strong> You can respond directly through your account dashboard. Our admin team will be notified immediately of your reply.
                </div>
                
                <div class='timestamp'>Message received: {$messageTime}</div>
            </div>
            
            <div class='footer'>
                <h3>ASRT Spaces</h3>
                <p>This notification was sent because an admin sent you a message about your rental invoice.</p>
                <p>Need help? Contact us at <span class='support-info'>management@asrt.space</span></p>
                <p style='margin-top: 15px; font-size: 11px;'>© 2025 ASRT Spaces. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    $mail->AltBody = "Hello {$safeName}!\n\nYou have a new message from ASRT Spaces Admin.\n\nInvoice Details:\nInvoice ID: #{$invoiceId}\nUnit: {$safeUnitName}\nMessage Time: {$messageTime}\n\nAdmin Message Preview:\n{$messagePreview}\n\nPlease log in to your ASRT Spaces account to view the full message and respond.\n\nBest regards,\nASRT Spaces Admin Team\n\nNeed help? Contact: management@asrt.space";
    
    return $mail->send();
}

// --- Restrict access: Only allow logged in admin ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

// --- Enhanced Chat functionality with email notification ---
if (isset($_POST['send_message']) && isset($_POST['invoice_id'])) {
    $invoice_id = intval($_POST['invoice_id']);
    $admin_id = $_SESSION['admin_id'] ?? 0;
    $message_text = trim($_POST['message_text'] ?? '');
    $image_path = null;

    // Handle image upload
    if (!empty($_FILES['image_file']['name'])) {
        $upload_dir = '../uploads/invoice_chat/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['image_file']['name']);
        $target_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_path)) {
            $image_path = 'uploads/invoice_chat/' . $file_name;
        }
    }

    // Send the chat message
    $messageSuccess = $db->sendInvoiceChat($invoice_id, 'admin', $admin_id, $message_text, $image_path);
    
    // Send email notification to client if message was sent successfully and has content
    if ($messageSuccess && !empty($message_text)) {
        try {
            // Get invoice and client details for email notification
            $invoice = $db->getSingleInvoiceForDisplay($invoice_id);
            if ($invoice && !empty($invoice['Client_Email'])) {
                $clientEmail = $invoice['Client_Email'];
                $clientFirstName = $invoice['Client_fn'] ?: 'User';
                $unitName = $invoice['UnitName'] ?: 'Your Unit';
                
                // Send email notification
                $emailSent = sendAdminMessageNotification(
                    $clientEmail, 
                    $clientFirstName, 
                    $message_text, 
                    $invoice_id, 
                    $unitName
                );
                
                if ($emailSent) {
                    error_log("Admin message email notification sent to: " . $clientEmail . " for invoice ID: " . $invoice_id);
                } else {
                    error_log("Failed to send admin message email notification to: " . $clientEmail . " for invoice ID: " . $invoice_id);
                }
            }
        } catch (Exception $e) {
            error_log("Error sending admin message notification email: " . $e->getMessage());
        }
    }
    
    header("Location: generate_invoice.php?chat_invoice_id=" . $invoice_id . "&status=" . ($_GET['status'] ?? 'new'));
    exit();
}

// --- Mark as Paid (send paid message in old invoice chat, create new invoice with chat continuity) ---
if (isset($_GET['toggle_status']) && isset($_GET['invoice_id'])) {
    $invoice_id = intval($_GET['invoice_id']);

    // Fetch the invoice to check status
    $invoice = $db->getSingleInvoiceForDisplay($invoice_id);
    if (!$invoice) {
        exit("Invoice not found.");
    }

    if (strtolower($invoice['Status']) === 'paid' || strtolower($invoice['Flow_Status']) === 'done') {
        // Already paid!
        header("Location: generate_invoice.php?chat_invoice_id=$invoice_id&status=" . ($_GET['status'] ?? 'new'));
        exit();
    }

    // 1. Mark invoice as paid in the database (updates Status and Flow_Status)
    $db->markInvoiceAsPaid($invoice_id);

    // 1b. Also mark latest rentalrequest as done for this invoice's client/unit
    $db->markRentalRequestDone($invoice['Client_ID'], $invoice['Space_ID']);

    // 2. Post PAID message in old chat (serves as a receipt)
    $paid_msg = "This rent has been PAID on " . date('Y-m-d') . ".";
    $db->sendInvoiceChat($invoice_id, 'system', null, $paid_msg, null);

    // 3. Create next invoice with correct period (next month after the most recent invoice's EndDate)
    $new_invoice_id = $db->createNextRecurringInvoiceWithChat($invoice_id);

    // 4. Optionally, add a system message in the NEW invoice chat
    // $db->sendInvoiceChat($new_invoice_id, 'system', null, "Previous rent was PAID on " . date('Y-m-d') . ".", null);

    // --- FIX: Only redirect if new invoice was actually created ---
    if ($new_invoice_id) {
        header("Location: generate_invoice.php?chat_invoice_id=$new_invoice_id&status=" . ($_GET['status'] ?? 'new'));
    } else {
        // fallback: stay in old invoice chat and show error (or just fallback)
        header("Location: generate_invoice.php?chat_invoice_id=$invoice_id&status=" . ($_GET['status'] ?? 'new') . "&error=recurring_invoice_failed");
    }
    exit();
}

// --- Display Logic ---
$invoices = [];
$show_chat = false;
$chat_invoice_id = null;
$chat_messages = [];
$invoice = null;

// --- Invoice filter logic ---
$allowed_statuses = ['new', 'done', 'all'];
$status_filter = $_GET['status'] ?? 'new';
if (!in_array($status_filter, $allowed_statuses)) $status_filter = 'new';

if (!$show_chat) {
    if ($status_filter === 'all') {
        $invoices = array_merge(
            $db->getInvoicesByFlowStatus('new'),
            $db->getInvoicesByFlowStatus('done')
        );
    } else {
        $invoices = $db->getInvoicesByFlowStatus($status_filter);
    }
}

if (isset($_GET['chat_invoice_id'])) {
    $show_chat = true;
    $chat_invoice_id = intval($_GET['chat_invoice_id']);
}
if ($show_chat && $chat_invoice_id) {
    // Mark all messages as read for admin
    $db->executeStatement('UPDATE invoice_chat SET is_read_admin = 1 WHERE Invoice_ID = ?', [$chat_invoice_id]);
    $invoice = $db->getSingleInvoiceForDisplay($chat_invoice_id);
    $chat_messages = $db->getInvoiceChatMessagesForClient($chat_invoice_id);
}

// Helper for countdown (output JS or static string)
function renderCountdown($due_date) {
    $due = strtotime($due_date);
    $now = time();
    $diff = $due - $now;
    if ($diff <= 0) {
        return '<span class="badge bg-danger">OVERDUE</span>';
    }
    $id = 'countdown_' . uniqid();
    return '<span id="'.$id.'" class="badge bg-warning text-dark" data-duedate="'.$due_date.'"></span>
<script>
(function(){
    function updateCountdown_'.$id.'() {
        var due = new Date("'.$due_date.'T23:59:59").getTime();
        var now = new Date().getTime();
        var diff = due - now;
        var el = document.getElementById("'.$id.'");
        if (!el) return;
        if (diff <= 0) {
            el.textContent = "OVERDUE";
            el.className = "badge bg-danger";
            return;
        }
        var d = Math.floor(diff / (1000*60*60*24));
        var h = Math.floor((diff%(1000*60*60*24))/(1000*60*60));
        var m = Math.floor((diff%(1000*60*60))/(1000*60));
        var s = Math.floor((diff%(1000*60))/1000);
        el.textContent = "Due in " + (d>0?d + "d ":"") + (h>0?h + "h ":"") + (m>0?m + "m ":"") + s + "s";
        setTimeout(updateCountdown_'.$id.', 1000);
    }
    updateCountdown_'.$id.'();
})();
</script>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, minimum-scale=1.0, maximum-scale=5.0">
    <title>Invoice Management | ASRT Management</title>
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
            font-size: 0.95rem;
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

        .page-title p {
            font-size: 0.9rem;
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
            min-width: 700px;
        }
        
        .custom-table th {
            background-color: #f9fafb;
            padding: 0.75rem 1rem;
            font-weight: 600;
            text-align: left;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
        }
        
        .custom-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .custom-table tr:last-child td {
            border-bottom: none;
        }
        
        .custom-table tr:hover {
            background-color: #f9fafb;
        }
        
        /* Chat Interface */
        .chat-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .chat-meta {
            padding: 1rem;
            background: #f9fafb;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .chat-messages {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #fafafa;
            border-radius: var(--border-radius);
        }
        
        .chat-message {
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            max-width: 80%;
        }
        
        .chat-message.admin {
            background: rgba(99, 102, 241, 0.1);
            border-left: 4px solid var(--primary);
            margin-left: auto;
        }
        
        .chat-message.client {
            background: rgba(107, 114, 128, 0.1);
            border-left: 4px solid #6b7280;
        }
        
        .chat-message.system {
            background: rgba(245, 158, 11, 0.1);
            border-left: 4px solid var(--warning);
            text-align: center;
            margin: 0 auto;
            font-style: italic;
        }
        
        .message-sender {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .chat-image {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            margin-top: 0.5rem;
        }
        
        .chat-form {
            background: #f9fafb;
            padding: 1rem;
            border-radius: var(--border-radius);
        }
        
        /* Filter Buttons */
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            text-decoration: none;
            border: 1px solid #e5e7eb;
            cursor: pointer;
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .filter-btn:not(.active) {
            background: #f8f9fa;
            color: #6b7280;
        }

        .filter-btn:not(.active):hover {
            background: #e9ecef;
            color: var(--dark);
        }
        
        /* Action Buttons */
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            cursor: pointer;
        }
        
        .btn-chat {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .btn-chat:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-paid {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .btn-paid:hover {
            background: var(--secondary);
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

        /* Mobile Card Layout */
        .mobile-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            padding: 1rem;
            border-left: 4px solid var(--primary);
        }

        .mobile-card.overdue {
            border-left-color: var(--danger);
        }

        .mobile-card.warning {
            border-left-color: var(--warning);
        }

        .mobile-card.completed {
            border-left-color: var(--secondary);
        }

        .mobile-card-header {
            font-weight: 600;
            font-size: 1rem;
            color: var(--dark);
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .mobile-card-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            align-items: flex-start;
        }

        .mobile-card-detail .label {
            font-weight: 500;
            color: #6b7280;
            min-width: 80px;
        }

        .mobile-card-detail .value {
            color: var(--dark);
            text-align: right;
            flex: 1;
        }

        .mobile-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .mobile-actions .btn-action {
            flex: 1;
            justify-content: center;
            min-width: 120px;
        }

        /* Chat Mobile Optimizations */
        .mobile-chat-meta {
            background: rgba(99, 102, 241, 0.05);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary);
        }

        .mobile-chat-form {
            background: #f9fafb;
            padding: 1rem;
            border-radius: var(--border-radius);
        }

        /* Hide desktop table on mobile */
        .table-mobile {
            display: none;
        }
        
        /* Mobile Responsive */
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
                margin-bottom: 1.5rem;
            }

            .page-title h1 {
                font-size: 1.5rem;
            }

            .title-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .custom-table {
                display: none;
            }

            .table-mobile {
                display: block;
            }

            .card-body {
                padding: 1rem;
            }

            .card-header {
                padding: 1rem;
                font-size: 1rem;
            }

            .chat-container {
                padding: 1rem;
            }

            .chat-messages {
                max-height: 300px;
            }

            .chat-message {
                max-width: 95%;
            }

            .filter-buttons {
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 0.75rem;
            }

            .chat-form .row {
                flex-direction: column;
            }

            .chat-form .col-md-8,
            .chat-form .col-md-2 {
                margin-bottom: 0.5rem;
            }

            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .mobile-actions {
                flex-direction: column;
            }

            .mobile-actions .btn-action {
                min-width: auto;
            }

            .filter-buttons {
                flex-direction: column;
            }

            .filter-btn {
                text-align: center;
            }

            .chat-image {
                max-width: 150px;
                max-height: 100px;
            }

            .chat-meta .row {
                flex-direction: column;
            }

            .chat-meta .col-md-6:last-child {
                text-align: left;
                margin-top: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .page-title h1 {
                font-size: 1.3rem;
            }

            .dashboard-card {
                border-radius: 8px;
            }

            .btn {
                font-size: 0.9rem;
                padding: 0.75rem 1.5rem;
            }

            .form-control, .form-select {
                padding: 0.75rem;
            }

            .chat-messages {
                max-height: 250px;
                padding: 0.75rem;
            }

            .chat-message {
                padding: 0.5rem 0.75rem;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn-action, .nav-link, .mobile-menu-btn, .filter-btn {
                min-height: 44px;
                min-width: 44px;
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

        /* Loading state */
        .loading-state {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }

        .loading-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid #f3f4f6;
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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

    <!-- Sidebar -->
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
                <a href="view_rental_requests.php" class="nav-link">
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
                <a href="generate_invoice.php" class="nav-link active">
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
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <h1>Invoice Management</h1>
                    <p class="text-muted mb-0">Manage invoices and communicate with clients</p>
                </div>
            </div>
        </div>
        
        <!-- Info Alert -->
        <div class="alert alert-info animate-fade-in">
            <i class="fas fa-info-circle me-2"></i>
            Mark an invoice as paid to confirm payment. The system will send a chat message as a receipt and create a new invoice for the next rental period. Clients will receive email notifications when you send them messages.
        </div>
        
        <?php if ($show_chat && $invoice): ?>
            <!-- Chat Interface -->
            <div class="chat-container animate-fade-in">
                <div class="chat-meta d-none d-md-block">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="fw-bold">Client: <?= htmlspecialchars($invoice['Client_fn'] ?? '') . ' ' . htmlspecialchars($invoice['Client_ln'] ?? '') ?></div>
                            <div class="text-muted small">Unit: <?= htmlspecialchars($invoice['UnitName'] ?? '') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-end">
                                <div class="fw-bold">Issued: <?= htmlspecialchars($invoice['InvoiceDate'] ?? '') ?></div>
                                <div class="fw-bold">Due: <?= htmlspecialchars($invoice['EndDate'] ?? $invoice['InvoiceDate'] ?? '') ?></div>
                                <?= isset($invoice['EndDate']) ? renderCountdown($invoice['EndDate']) : '' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Chat Meta -->
                <div class="mobile-chat-meta d-md-none">
                    <div class="fw-bold mb-2">Client: <?= htmlspecialchars($invoice['Client_fn'] ?? '') . ' ' . htmlspecialchars($invoice['Client_ln'] ?? '') ?></div>
                    <div class="text-muted small mb-2">Unit: <?= htmlspecialchars($invoice['UnitName'] ?? '') ?></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">Issued: <?= htmlspecialchars($invoice['InvoiceDate'] ?? '') ?></div>
                            <div class="small">Due: <?= htmlspecialchars($invoice['EndDate'] ?? $invoice['InvoiceDate'] ?? '') ?></div>
                        </div>
                        <div>
                            <?= isset($invoice['EndDate']) ? renderCountdown($invoice['EndDate']) : '' ?>
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages" id="adminChatMessages">
                    <!-- Chat messages will be loaded here by JavaScript -->
                    <div class="loading-state">
                        <div class="loading-spinner"></div>
                        <p class="mt-2">Loading messages...</p>
                    </div>
                </div>

<script>
// Live admin chat message loader with client typing bubble
let clientTyping = false;

async function loadAdminChatMessages() {
    const chatMessages = document.getElementById('adminChatMessages');
    if (!chatMessages) return;
    const invoiceId = <?= json_encode($chat_invoice_id) ?>;
    if (!invoiceId) return;
    try {
        const response = await fetch('../AJAX/admin_invoice_chat_messages.php?invoice_id=' + invoiceId);
        const data = await response.json();
        chatMessages.innerHTML = '';
        if (data.error) {
            chatMessages.innerHTML = `<div class='text-center text-danger py-4'>${data.error}</div>`;
            return;
        }
        if (data.length === 0) {
            chatMessages.innerHTML = `<div class='text-center text-muted py-4'><i class='fas fa-comments fa-3x mb-3 d-block opacity-50'></i><h5>No messages yet</h5><p>Start a conversation about this invoice.</p></div>`;
            return;
        }
        data.forEach(msg => {
            const is_admin = msg.Sender_Type === 'admin';
            const is_system = msg.Sender_Type === 'system';
            const is_client = msg.Sender_Type === 'client';
            let bubbleClass = is_admin ? 'admin' : (is_system ? 'system' : (is_client ? 'client' : ''));
            let sender = msg.SenderName || (is_system ? 'System' : (is_admin ? 'Admin' : 'Client'));
            let html = `<div class='chat-message ${bubbleClass}'>`;
            html += `<div class='message-sender'>${sender}</div>`;
            html += `<div class='message-text'>${msg.Message.replace(/\n/g, '<br>')}`;
            if (msg.Image_Path) {
                html += `<img src='../${msg.Image_Path}' class='chat-image mt-2' alt='chat photo'>`;
            }
            html += `</div>`;
            html += `<div class='message-time'>${msg.Created_At || ''}</div>`;
            html += `</div>`;
            chatMessages.innerHTML += html;
        });
        // Add typing bubble if client is typing
        if (clientTyping) {
            let typingHtml = `<div class='chat-message client'>` +
                `<div class='message-sender'>Client</div>` +
                `<div class='message-text' style='opacity:0.7;'><span class='me-2'><i class='fas fa-ellipsis-h'></i></span>Client is typing...</div>` +
                `<div class='message-time'></div></div>`;
            chatMessages.innerHTML += typingHtml;
        }
        // Optional: auto-scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    } catch (err) {
        chatMessages.innerHTML = `<div class='text-center text-danger py-4'>Failed to load messages.</div>`;
    }
}

// Poll client typing status
async function pollClientTyping() {
    const invoiceId = <?= json_encode($chat_invoice_id) ?>;
    if (!invoiceId) return;
    try {
        const response = await fetch('../AJAX/invoice_client_typing.php?invoice_id=' + invoiceId);
        const data = await response.json();
        clientTyping = !!data.typing;
    } catch (e) {
        clientTyping = false;
    }
}

loadAdminChatMessages();
pollClientTyping();
setInterval(() => {
    pollClientTyping();
    loadAdminChatMessages();
}, 5000); // Refresh every 5 seconds
</script>
                
                <form method="post" enctype="multipart/form-data" class="chat-form mobile-chat-form">
                    <div class="row g-2">
                        <div class="col-12 col-md-8">
                            <textarea name="message_text" class="form-control admin-chat-textarea" rows="2" placeholder="Type your message... (Client will receive email notification)"></textarea>
                        </div>
                        <div class="col-12 col-md-2">
                            <input type="file" name="image_file" accept="image/*" class="form-control">
                        </div>
                        <div class="col-12 col-md-2">
                            <input type="hidden" name="invoice_id" value="<?= $chat_invoice_id ?>">
                            <button type="submit" name="send_message" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-1"></i> Send & Notify
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <button class="btn-action btn-paid" onclick="confirmPaid(this)"
                        data-href="generate_invoice.php?toggle_status=paid&invoice_id=<?= $invoice['Invoice_ID'] ?>&status=<?= htmlspecialchars($status_filter) ?>">
                        <i class="fas fa-check-circle"></i> Mark as Paid
                    </button>
                    <a href="generate_invoice.php?status=<?= htmlspecialchars($status_filter) ?>" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Invoices
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$show_chat): ?>
            <!-- Invoice List -->
            <div class="dashboard-card animate-fade-in">
                <div class="card-header">
                    <i class="fas fa-list"></i>
                    <span>Invoices</span>
                    <span class="badge bg-primary ms-2"><?= count($invoices) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="filter-buttons p-3 border-bottom">
                        <a href="?status=new" class="filter-btn <?= $status_filter === 'new' ? 'active' : '' ?>">
                            New Invoices
                        </a>
                        <a href="?status=done" class="filter-btn <?= $status_filter === 'done' ? 'active' : '' ?>">
                            Completed
                        </a>
                        <a href="?status=all" class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">
                            All Invoices
                        </a>
                    </div>
                    
                    <?php if (!empty($invoices)): ?>
                        <!-- Desktop Table -->
                        <div class="table-container">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Client</th>
                                        <th>Unit</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $ctr = 1; foreach ($invoices as $row): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-medium">#<?= $ctr++ ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars(($row['Client_fn'] ?? '') . ' ' . ($row['Client_ln'] ?? '')) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($row['UnitName'] ?? '') ?></td>
                                        <td>
                                            <?= isset($row['EndDate']) ? htmlspecialchars($row['EndDate']) : '<span class="text-muted">N/A</span>' ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (isset($row['Flow_Status']) && strtolower($row['Flow_Status']) === 'done') {
                                                echo '<span class="badge bg-success">Completed</span>';
                                            } elseif (isset($row['EndDate'])) {
                                                echo renderCountdown($row['EndDate']);
                                            } else {
                                                echo '<span class="text-muted">N/A</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="generate_invoice.php?chat_invoice_id=<?= $row['Invoice_ID'] ?>&status=<?= htmlspecialchars($status_filter) ?>" class="btn-action btn-chat position-relative" data-invoice-id="<?= $row['Invoice_ID'] ?>">
                                                <i class="fas fa-comments"></i> Chat
                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="unread-badge-<?= $row['Invoice_ID'] ?>"></span>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Card Layout -->
                        <div class="table-mobile">
                            <?php $ctr = 1; foreach ($invoices as $row): 
                                $isOverdue = false;
                                $isWarning = false;
                                $isCompleted = false;
                                
                                if (isset($row['Flow_Status']) && strtolower($row['Flow_Status']) === 'done') {
                                    $isCompleted = true;
                                } elseif (isset($row['EndDate'])) {
                                    $due = strtotime($row['EndDate']);
                                    $now = time();
                                    $diff = $due - $now;
                                    if ($diff <= 0) {
                                        $isOverdue = true;
                                    } elseif ($diff < 86400 * 3) { // 3 days
                                        $isWarning = true;
                                    }
                                }
                                
                                $cardClass = 'mobile-card';
                                if ($isOverdue) $cardClass .= ' overdue';
                                elseif ($isWarning) $cardClass .= ' warning';
                                elseif ($isCompleted) $cardClass .= ' completed';
                            ?>
                            <div class="<?= $cardClass ?>">
                                <div class="mobile-card-header">
                                    <div>
                                        <strong><?= htmlspecialchars(($row['Client_fn'] ?? '') . ' ' . ($row['Client_ln'] ?? '')) ?></strong>
                                        <span class="badge bg-primary ms-2">#<?= $ctr++ ?></span>
                                    </div>
                                    <div>
                                        <?php
                                        if ($isCompleted) {
                                            echo '<span class="badge bg-success">Completed</span>';
                                        } elseif (isset($row['EndDate'])) {
                                            echo renderCountdown($row['EndDate']);
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Unit:</span>
                                    <span class="value"><?= htmlspecialchars($row['UnitName'] ?? '') ?></span>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Due Date:</span>
                                    <span class="value"><?= isset($row['EndDate']) ? htmlspecialchars($row['EndDate']) : '<span class="text-muted">N/A</span>' ?></span>
                                </div>

                                <div class="mobile-actions">
                                    <a href="generate_invoice.php?chat_invoice_id=<?= $row['Invoice_ID'] ?>&status=<?= htmlspecialchars($status_filter) ?>" class="btn-action btn-chat position-relative" data-invoice-id="<?= $row['Invoice_ID'] ?>">
                                        <i class="fas fa-comments"></i> Open Chat
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="unread-badge-mobile-<?= $row['Invoice_ID'] ?>"></span>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice"></i>
                            <h4>No invoices found</h4>
                            <p>There are no invoices matching the selected filter</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live poll unread client messages for admin (desktop and mobile)
        function pollAdminUnreadBadges() {
            const invoiceLinks = document.querySelectorAll('.btn-chat[data-invoice-id]');
            const invoiceIds = Array.from(invoiceLinks).map(link => link.getAttribute('data-invoice-id'));
            if (invoiceIds.length === 0) return;
            fetch('../AJAX/get_unread_client_chat_counts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'invoice_ids=' + encodeURIComponent(JSON.stringify(invoiceIds))
            })
            .then(res => res.json())
            .then(counts => {
                invoiceIds.forEach(id => {
                    // Desktop badge
                    const badge = document.getElementById('unread-badge-' + id);
                    if (badge) {
                        const count = counts[id] || 0;
                        if (count > 0) {
                            badge.textContent = count;
                            badge.classList.remove('d-none');
                        } else {
                            badge.textContent = '';
                            badge.classList.add('d-none');
                        }
                    }
                    // Mobile badge
                    const badgeMobile = document.getElementById('unread-badge-mobile-' + id);
                    if (badgeMobile) {
                        const count = counts[id] || 0;
                        if (count > 0) {
                            badgeMobile.textContent = count;
                            badgeMobile.classList.remove('d-none');
                        } else {
                            badgeMobile.textContent = '';
                            badgeMobile.classList.add('d-none');
                        }
                    }
                });
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            pollAdminUnreadBadges();
            setInterval(pollAdminUnreadBadges, 5000);

            // Mark messages as read for admin via AJAX when chat is opened
            document.querySelectorAll('.btn-chat[data-invoice-id]').forEach(link => {
                link.addEventListener('click', function(e) {
                    const invoiceId = this.getAttribute('data-invoice-id');
                    if (!invoiceId) return;
                    fetch('../AJAX/mark_admin_chat_read.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'invoice_id=' + encodeURIComponent(invoiceId)
                    });
                });
            });
        });

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

        function confirmPaid(button) {
            Swal.fire({
                title: 'Are you sure?',
                text: "Mark this invoice as PAID? The system will send a chat message as a receipt and create the next invoice for the next rental period with chat continuity.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, mark it as paid!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = button.getAttribute('data-href');
                }
            });
        }

        // Admin typing indicator AJAX
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('.admin-chat-textarea');
            const invoiceId = <?= json_encode($chat_invoice_id) ?>;
            let typing = false;
            let typingTimeout = null;
            if (textarea && invoiceId) {
                textarea.addEventListener('input', function() {
                    if (!typing) {
                        typing = true;
                        sendTypingStatus(1);
                    }
                    clearTimeout(typingTimeout);
                    typingTimeout = setTimeout(() => {
                        typing = false;
                        sendTypingStatus(0);
                    }, 3000); // 3 seconds after last input
                });
                // On blur, clear typing
                textarea.addEventListener('blur', function() {
                    typing = false;
                    sendTypingStatus(0);
                });
            }
            function sendTypingStatus(isTyping) {
                fetch('../AJAX/invoice_admin_typing.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'invoice_id=' + encodeURIComponent(invoiceId) + '&typing=' + (isTyping ? '1' : '0')
                });
            }
        });

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