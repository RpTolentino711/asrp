<?php
require 'database/database.php';
session_start();
$db = new Database();

if (!isset($_SESSION['client_id'])): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Center - ASRT Commercial Spaces</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <?php require('links.php'); ?>
    
    <style>
    :root {
        --primary: #1e40af;
        --primary-light: #3b82f6;
        --accent: #ef4444;
        --success: #059669;
        --light: #f8fafc;
        --lighter: #ffffff;
        --gray: #64748b;
        --gray-light: #e2e8f0;
        --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
        --shadow-xl: 0 16px 40px rgba(0, 0, 0, 0.15);
        --border-radius: 16px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: var(--light);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        padding-top: 100px;
    }

    .main-content {
        flex: 1 0 auto;
    }

    .footer {
        flex-shrink: 0;
        margin-top: auto;
    }

    .login-required-section {
        max-width: 600px;
        margin: 0 auto;
        padding: 3rem 0;
    }

    .login-card {
        background: var(--lighter);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-xl);
        padding: 3rem 2rem;
        text-align: center;
        border: none;
    }

    .lock-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--accent) 0%, #f87171 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 2rem;
        color: white;
        font-size: 2rem;
    }

    .login-title {
        font-family: 'Playfair Display', serif;
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 1rem;
    }

    .login-description {
        color: var(--gray);
        font-size: 1.1rem;
        margin-bottom: 2rem;
        line-height: 1.6;
    }

    .modern-btn {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.875rem 2rem;
        font-weight: 600;
        font-size: 1rem;
        transition: var(--transition);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .modern-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        color: white;
    }

    .modern-btn i {
        margin-right: 0.5rem;
    }

    @media (max-width: 768px) {
        body {
            padding-top: 80px;
        }
        
        .login-card {
            padding: 2rem 1.5rem;
        }
        
        .login-title {
            font-size: 1.5rem;
        }
    }
    </style>
</head>
<body>
    <?php require('header.php'); ?>
    
    <div class="main-content">
        <div class="container">
            <div class="login-required-section">
                <div class="login-card">
                    <div class="lock-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h1 class="login-title">Payment Center</h1>
                    <p class="login-description">
                        Access your invoices, payment history, and communicate with our team about billing matters. 
                        Please log in to continue.
                    </p>
                    <button class="modern-btn" data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="bi bi-person"></i>Login to Continue
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php require('footer.php'); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
            }, 1000);
        });
    </script>
</body>
</html>
<?php exit; endif; ?>

<?php
$client_id = $_SESSION['client_id'];

// Fetch all invoices for this client (paid, unpaid, kicked, etc.)
$invoices = $db->getClientInvoiceHistory($client_id);
$invoice_ids = array_column($invoices, 'Invoice_ID');

// Separate invoices by status
$due_invoices = [];
$paid_invoices = [];
$kicked_invoices = [];
foreach ($invoices as $inv) {
    $status = strtolower($inv['Status']);
    if ($status === 'paid') {
        $paid_invoices[] = $inv;
    } elseif ($status === 'kicked') {
        $kicked_invoices[] = $inv;
    } else {
        $due_invoices[] = $inv;
    }
}

// Select invoice for chat (GET or POST)
$selected_invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : (isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0);
if (!$selected_invoice_id && count($due_invoices)) {
    $selected_invoice_id = $due_invoices[0]['Invoice_ID'];
} elseif (!$selected_invoice_id && count($paid_invoices)) {
    $selected_invoice_id = $paid_invoices[0]['Invoice_ID'];
} elseif (!$selected_invoice_id && count($kicked_invoices)) {
    $selected_invoice_id = $kicked_invoices[0]['Invoice_ID'];
}

// Fetch selected invoice details
$invoice = null;
foreach ($invoices as $inv) {
    if ($inv['Invoice_ID'] == $selected_invoice_id) {
        $invoice = $inv;
        break;
    }
}

// Fetch chat messages
$chat_messages = [];
if ($selected_invoice_id) {
    $chat_messages = $db->getInvoiceChatMessagesForClient($selected_invoice_id);
}

// Check invoice status
$is_paid = (strtolower($invoice['Status'] ?? '') === 'paid');
$is_kicked = (strtolower($invoice['Status'] ?? '') === 'kicked');

// Handle sending a message (only if not paid and not kicked)
if (isset($_POST['send_message']) && $selected_invoice_id && !$is_paid && !$is_kicked) {
    $msg = trim($_POST['message_text'] ?? '');
    $image_path = null;

    // File upload
    if (!empty($_FILES['image_file']['name'])) {
        $upload_dir = 'uploads/invoice_chat/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['image_file']['name']);
        $target_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_path)) {
            $image_path = $upload_dir . $file_name;
        }
    }

    $result = $db->sendInvoiceChat($selected_invoice_id, 'client', $client_id, $msg, $image_path);
    if (!$result) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Message Failed',
                    text: 'Failed to send message. Please contact support.'
                });
            });
        </script>";
    } else {
        header("Location: invoice_history.php?invoice_id=$selected_invoice_id");
        exit();
    }
}

$show_kicked_message_in_chat = $is_kicked;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Center - ASRT Commercial Spaces</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <?php require('links.php'); ?>
    
    <style>
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary: #0f172a;
            --accent: #ef4444;
            --accent-light: #f87171;
            --success: #059669;
            --warning: #d97706;
            --light: #f8fafc;
            --lighter: #ffffff;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --gray-dark: #334155;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
            --shadow-xl: 0 16px 40px rgba(0, 0, 0, 0.15);
            --border-radius: 16px;
            --border-radius-sm: 8px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light);
            color: var(--secondary);
            line-height: 1.6;
            padding-top: 100px;
        }

        .payment-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .payment-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" patternUnits="userSpaceOnUse" width="20" height="20"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
        }

        .payment-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .payment-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
            position: relative;
            z-index: 2;
        }

        .chat-container {
            background: var(--lighter);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-light);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .business-notice {
            background: linear-gradient(135deg, #fff3cd 0%, #fef3c7 100%);
            color: #92400e;
            border: 1px solid #fbbf24;
            border-radius: var(--border-radius-sm);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .business-notice i {
            font-size: 1.5rem;
            margin-right: 1rem;
            color: #d97706;
        }

        .invoice-selector {
            background: var(--light);
            border-radius: var(--border-radius-sm);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .selector-group {
            margin-bottom: 1rem;
        }

        .selector-group:last-child {
            margin-bottom: 0;
        }

        .selector-label {
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .modern-select {
            background: var(--lighter);
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: var(--transition);
            color: var(--secondary);
            min-width: 250px;
        }

        .modern-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
            outline: none;
        }

        .chat-messages {
            padding: 1.5rem 2rem;
            min-height: 400px;
            max-height: 600px;
            overflow-y: auto;
        }

        .chat-message {
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
        }

        .chat-message.client {
            align-items: flex-end;
        }

        .chat-message.admin {
            align-items: flex-start;
        }

        .chat-message.system {
            align-items: center;
        }

        .message-bubble {
            max-width: 70%;
            padding: 1rem 1.25rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.95rem;
            line-height: 1.5;
            position: relative;
        }

        .message-bubble.client {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        .message-bubble.admin {
            background: var(--light);
            color: var(--secondary);
            border: 1px solid var(--gray-light);
        }

        .message-bubble.system {
            background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 100%);
            color: #92400e;
            font-style: italic;
            text-align: center;
        }

        .message-image {
            max-width: 250px;
            max-height: 200px;
            border-radius: var(--border-radius-sm);
            margin-top: 0.5rem;
            box-shadow: var(--shadow-md);
            cursor: pointer;
            transition: var(--transition);
        }

        .message-image:hover {
            transform: scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .message-meta {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.5rem;
            opacity: 0.8;
        }

        .chat-input-section {
            background: var(--light);
            border-top: 1px solid var(--gray-light);
            padding: 1.5rem 2rem;
        }

        .message-form {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .message-textarea {
            flex: 1;
            background: var(--lighter);
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1rem;
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
            min-height: 60px;
            transition: var(--transition);
        }

        .message-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
            outline: none;
        }

        .file-input {
            background: var(--lighter);
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius-sm);
            padding: 0.75rem;
            font-size: 0.9rem;
            transition: var(--transition);
            width: 120px;
        }

        .file-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
            outline: none;
        }

        .send-button {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .send-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .send-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .status-alert {
            border-radius: var(--border-radius-sm);
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .status-alert i {
            font-size: 1.25rem;
            margin-right: 1rem;
        }

        .status-alert.warning {
            background: linear-gradient(135deg, #fff3cd 0%, #fbbf24 100%);
            color: #92400e;
            border: 1px solid #fbbf24;
        }

        .status-alert.danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fca5a5 100%);
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .unit-info {
            background: var(--light);
            border-radius: var(--border-radius-sm);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }

        .unit-info h6 {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .unit-info p {
            color: var(--gray-dark);
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        /* Loading states */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 16px;
            height: 16px;
            margin: -8px 0 0 -8px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding-top: 80px;
            }

            .payment-header {
                padding: 1.5rem 0;
                margin-bottom: 1.5rem;
            }

            .payment-header h1 {
                font-size: 2rem;
            }

            .chat-container {
                border-radius: var(--border-radius-sm);
                margin: 0 -15px;
                box-shadow: none;
                border-left: none;
                border-right: none;
            }

            .chat-messages,
            .chat-input-section {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .invoice-selector {
                margin-left: -15px;
                margin-right: -15px;
                border-radius: 0;
            }

            .modern-select {
                min-width: 200px;
                font-size: 0.9rem;
            }

            .message-form {
                flex-direction: column;
                gap: 0.75rem;
                align-items: stretch;
            }

            .file-input {
                width: 100%;
            }

            .message-bubble {
                max-width: 85%;
            }
        }

        @media (max-width: 576px) {
            body {
                padding-top: 70px;
            }

            .payment-header h1 {
                font-size: 1.75rem;
            }

            .business-notice {
                flex-direction: column;
                text-align: center;
                padding: 1rem;
            }

            .business-notice i {
                margin-right: 0;
                margin-bottom: 0.5rem;
            }
        }

        /* Scroll animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.8s ease-out;
        }

        .animate-on-scroll.animate {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>

<body>
    <?php require('header.php'); ?>

    <!-- Payment Header -->
    <section class="payment-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>Payment Center</h1>
                    <p>Manage your invoices and communicate with our billing team</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex align-items-center justify-content-md-end">
                        <i class="bi bi-credit-card fs-1 me-3"></i>
                        <div>
                            <div class="fw-bold">Invoice Chat</div>
                            <small class="opacity-75">Real-time Support</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <!-- Business Notice -->
        <div class="business-notice animate-on-scroll">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>Business Communication Only:</strong> All conversations here must be strictly about business, invoices, unit rental, payments, or related concerns. Thank you for your cooperation.
            </div>
        </div>

        <!-- Unit Information -->
        <?php if ($invoice): ?>
        <div class="unit-info animate-on-scroll">
            <h6><i class="bi bi-building me-2"></i>Current Unit</h6>
            <p><?= htmlspecialchars($invoice['SpaceName'] ?? 'N/A') ?></p>
        </div>
        <?php endif; ?>

        <!-- Invoice Selector -->
        <div class="invoice-selector animate-on-scroll">
            <div class="row">
                <div class="col-md-6">
                    <div class="selector-group">
                        <form method="get">
                            <label class="selector-label">
                                <i class="bi bi-exclamation-circle me-2 text-warning"></i>Outstanding Invoices
                            </label>
                            <select name="invoice_id" class="modern-select" onchange="this.form.submit()" id="dueInvoiceSelect">
                                <?php if (empty($due_invoices)): ?>
                                    <option value="">No outstanding invoices</option>
                                <?php else: ?>
                                    <?php foreach ($due_invoices as $inv): ?>
                                        <option value="<?= $inv['Invoice_ID'] ?>"
                                            <?= $inv['Invoice_ID'] == ($selected_invoice_id ?? 0) ? 'selected' : '' ?>
                                            data-invoice-id="<?= $inv['Invoice_ID'] ?>">
                                            <?= htmlspecialchars($inv['SpaceName']) ?> - <?= htmlspecialchars($inv['InvoiceDate']) ?>
                                            (<?= strtoupper($inv['Status']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="selector-group">
                        <form method="get">
                            <label class="selector-label">
                                <i class="bi bi-archive me-2 text-success"></i>Completed & Archived
                            </label>
                            <select name="invoice_id" class="modern-select" onchange="this.form.submit()" id="archivedInvoiceSelect">
                                <option value="">-- Select Invoice --</option>
                                <?php foreach ($paid_invoices as $inv): ?>
                                    <option value="<?= $inv['Invoice_ID'] ?>"
                                        <?= $inv['Invoice_ID'] == ($selected_invoice_id ?? 0) ? 'selected' : '' ?>
                                        data-invoice-id="<?= $inv['Invoice_ID'] ?>">
                                        <?= htmlspecialchars($inv['SpaceName']) ?> - <?= htmlspecialchars($inv['InvoiceDate']) ?> (PAID)
                                    </option>
                                <?php endforeach; ?>
                                <?php foreach ($kicked_invoices as $inv): ?>
                                    <option value="<?= $inv['Invoice_ID'] ?>"
                                        <?= $inv['Invoice_ID'] == ($selected_invoice_id ?? 0) ? 'selected' : '' ?>
                                        data-invoice-id="<?= $inv['Invoice_ID'] ?>">
                                        <?= htmlspecialchars($inv['SpaceName']) ?> - <?= htmlspecialchars($inv['InvoiceDate']) ?> (ARCHIVED)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Container -->
        <div class="chat-container animate-on-scroll">
            <!-- Chat Messages -->
            <div class="chat-messages" id="chatMessages">
                <!-- Chat messages will be loaded here by JavaScript -->
            </div>
<script>
// Show unread badge for invoices with unread admin messages
document.addEventListener('DOMContentLoaded', function() {
    const clientId = <?= json_encode($client_id) ?>;
    const invoiceOptions = [
        ...document.querySelectorAll('#dueInvoiceSelect option[data-invoice-id]'),
        ...document.querySelectorAll('#archivedInvoiceSelect option[data-invoice-id]')
    ];
    const invoiceIds = invoiceOptions.map(opt => opt.getAttribute('data-invoice-id'));
    if (invoiceIds.length === 0) return;
    fetch('AJAX/get_unread_admin_chat_counts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'client_id=' + encodeURIComponent(clientId) + '&invoice_ids=' + encodeURIComponent(JSON.stringify(invoiceIds))
    })
    .then(res => res.json())
    .then(counts => {
        invoiceOptions.forEach(opt => {
            const id = opt.getAttribute('data-invoice-id');
            if (counts[id] && counts[id] > 0) {
                opt.textContent += ' \uD83D\uDD14'; // Bell emoji as badge
            }
        });
    });
});
// Mark all admin messages as read for client
async function markChatReadClient(invoiceId) {
    if (!invoiceId) return;
    try {
        await fetch('AJAX/mark_invoice_chat_read_client.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'invoice_id=' + encodeURIComponent(invoiceId)
        });
    } catch (e) {}
}

// Live chat message loader with admin typing bubble
let adminTyping = false;
let lastTypingBubble = null;

async function loadChatMessages() {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;
    const invoiceId = <?= json_encode($selected_invoice_id) ?>;
    if (!invoiceId) return;
    try {
        const response = await fetch('AJAX/invoice_chat_messages.php?invoice_id=' + invoiceId);
        const data = await response.json();
        chatMessages.innerHTML = '';
        if (data.error) {
            chatMessages.innerHTML = `<div class='text-center text-danger py-4'>${data.error}</div>`;
            return;
        }
        if (data.length === 0) {
            chatMessages.innerHTML = `<div class='text-center text-muted py-4'><i class='bi bi-chat-dots fs-1 mb-3 d-block'></i><h5>No messages yet</h5><p>Start a conversation about your invoice or payment inquiry.</p></div>`;
            return;
        }
        data.forEach(msg => {
            const is_client = msg.Sender_Type === 'client';
            const is_admin = msg.Sender_Type === 'admin';
            const is_system = msg.Sender_Type === 'system';
            let bubbleClass = is_client ? 'client' : (is_admin ? 'admin' : 'system');
            let html = `<div class='chat-message ${bubbleClass}'>`;
            html += `<div class='message-bubble ${bubbleClass}'>${msg.Message.replace(/\n/g, '<br>')}`;
            if (msg.Image_Path) {
                html += `<img src='${msg.Image_Path}' class='message-image' alt='Chat attachment' onclick='showImageModal("${msg.Image_Path}")'>`;
            }
            html += `</div>`;
            if (!is_system) {
                html += `<div class='message-meta'><strong>${msg.SenderName || ''}</strong> <span class='text-muted ms-2'>${msg.Created_At || ''}</span></div>`;
            } else {
                html += `<div class='message-meta text-center'><span class='text-muted'>${msg.Created_At || ''}</span></div>`;
            }
            html += `</div>`;
            chatMessages.innerHTML += html;
        });
        // Add typing bubble if admin is typing
        if (adminTyping) {
            let typingHtml = `<div class='chat-message admin'>` +
                `<div class='message-bubble admin' style='opacity:0.7;'>` +
                `<span class='me-2'><i class='bi bi-three-dots'></i></span>Admin is typing...` +
                `</div></div>`;
            chatMessages.innerHTML += typingHtml;
        }
        scrollToBottom();
    } catch (err) {
        chatMessages.innerHTML = `<div class='text-center text-danger py-4'>Failed to load messages.</div>`;
    }
}

// Poll admin typing status
async function pollAdminTyping() {
    const invoiceId = <?= json_encode($selected_invoice_id) ?>;
    if (!invoiceId) return;
    try {
        const response = await fetch('AJAX/invoice_admin_typing.php?invoice_id=' + invoiceId);
        const data = await response.json();
        adminTyping = !!data.typing;
    } catch (e) {
        adminTyping = false;
    }
}

// On page load and invoice change, mark as read
document.addEventListener('DOMContentLoaded', function() {
    const invoiceId = <?= json_encode($selected_invoice_id) ?>;
    if (invoiceId) markChatReadClient(invoiceId);
    // Also mark as read on invoice selector change
    document.querySelectorAll('select[name="invoice_id"]').forEach(sel => {
        sel.addEventListener('change', function() {
            if (this.value) markChatReadClient(this.value);
        });
    });
});

loadChatMessages();
pollAdminTyping();
setInterval(() => {
    pollAdminTyping();
    loadChatMessages();
}, 5000); // Refresh every 5 seconds
</script>

            <!-- Status Alerts -->
            <?php if ($is_kicked): ?>
                <div class="status-alert danger">
                    <i class="bi bi-lock"></i>
                    <div>
                        <strong>Conversation Locked</strong><br>
                        This conversation is archived. Please contact our team directly for new inquiries.
                    </div>
                </div>
            <?php elseif ($is_paid): ?>
                <div class="status-alert warning">
                    <i class="bi bi-check-circle"></i>
                    <div>
                        <strong>Invoice Paid</strong><br>
                        This invoice has been marked as paid. You can no longer send messages for this billing period.
                    </div>
                </div>
            <?php endif; ?>

            <!-- Chat Input Section -->
            <?php if (!$is_paid && !$is_kicked): ?>
                <div class="chat-input-section">
                    <form method="post" enctype="multipart/form-data" class="message-form">
                        <textarea 
                            name="message_text" 
                            class="message-textarea client-chat-textarea" 
                            placeholder="Type your message about this invoice..."
                            rows="3"></textarea>
                        <input 
                            type="file" 
                            name="image_file" 
                            accept="image/*" 
                            class="file-input"
                            title="Attach image">
                        <input type="hidden" name="invoice_id" value="<?= $selected_invoice_id ?>">
                        <button type="submit" name="send_message" class="send-button">
                            <i class="bi bi-send"></i>
                            Send
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Help Section -->
        <div class="row mt-4 mb-5">
            <div class="col-md-6 animate-on-scroll">
                <div class="card h-100" style="border-radius: var(--border-radius); border: 1px solid var(--gray-light); box-shadow: var(--shadow-md);">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                                <i class="bi bi-telephone text-primary fs-4"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Need Help?</h5>
                                <p class="text-muted mb-0 small">Contact our support team</p>
                            </div>
                        </div>
                        <p class="text-muted mb-3">For urgent matters or technical issues, you can reach us directly.</p>
                        <div class="d-flex gap-2">
                            <a href="tel:+09451357685" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-telephone me-1"></i>Call
                            </a>
                            <a href="mailto:management@asrt.space" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-envelope me-1"></i>Email
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 animate-on-scroll">
                <div class="card h-100" style="border-radius: var(--border-radius); border: 1px solid var(--gray-light); box-shadow: var(--shadow-md);">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3 me-3">
                                <i class="bi bi-info-circle text-success fs-4"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Payment Info</h5>
                                <p class="text-muted mb-0 small">Payment methods & policies</p>
                            </div>
                        </div>
                        <p class="text-muted mb-3">Learn about our accepted payment methods and billing policies.</p>
                        <a href="#" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-book me-1"></i>View Guide
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: var(--border-radius); border: none; box-shadow: var(--shadow-xl);">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="imageModalLabel">Image Attachment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="modalImage" src="" alt="Full size image" class="img-fluid" style="max-height: 70vh;">
                </div>
            </div>
        </div>
    </div>

    <?php require('footer.php'); ?>

    <!-- Bootstrap JS -->

    <script>
        // Scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.animate-on-scroll').forEach((el) => {
            observer.observe(el);
        });

        // Auto-scroll to bottom of chat
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        // Image modal functionality
        function showImageModal(imageSrc) {
            const modalImage = document.getElementById('modalImage');
            modalImage.src = imageSrc;
            const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
            imageModal.show();
        }

        // Form submission with loading state
        function handleFormSubmit(form) {
            const submitButton = form.querySelector('button[type="submit"]');
            const messageTextarea = form.querySelector('textarea[name="message_text"]');
            const fileInput = form.querySelector('input[type="file"]');
            const hasImage = fileInput && fileInput.files.length > 0;
            const hasMessage = messageTextarea.value.trim().length > 0;

            // Allow empty message if image is attached
            if (!hasMessage && !hasImage) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Empty Message',
                    text: 'Please enter a message or attach an image before sending.',
                    confirmButtonColor: '#1e40af'
                });
                return false;
            }

            // Add loading state
            submitButton.classList.add('loading');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i>Sending...';

            // Allow form to submit normally
            return true;
        }

        // Auto-resize textarea
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('.message-textarea');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            }
            
            // Scroll to bottom on page load
            scrollToBottom();
        });

        // Enhanced file input feedback
        document.querySelector('.file-input')?.addEventListener('change', function() {
            const fileName = this.files[0]?.name;
            if (fileName) {
                // Show file selected feedback
                const toast = document.createElement('div');
                toast.className = 'position-fixed top-0 end-0 m-3 alert alert-info alert-dismissible fade show';
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    <i class="bi bi-image me-2"></i>Image selected: ${fileName}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        toast.remove();
                    }
                }, 3000);
            }
        });
    </script>
    <script>
    // Client typing indicator AJAX
    document.addEventListener('DOMContentLoaded', function() {
        const textarea = document.querySelector('.client-chat-textarea');
        const invoiceId = <?= json_encode($selected_invoice_id) ?>;
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
            fetch('AJAX/invoice_client_typing.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'invoice_id=' + encodeURIComponent(invoiceId) + '&typing=' + (isTyping ? '1' : '0')
            });
        }
    });
    </script>
</body>
</html>

