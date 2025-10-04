<?php
session_start();
require 'database/database.php';

$db = new Database();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// Get selected month/year from request
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($selectedMonth < 1 || $selectedMonth > 12) $selectedMonth = date('m');
if ($selectedYear < 2020 || $selectedYear > 2030) $selectedYear = date('Y');

// Get date range for the selected month/year
$startDate = "$selectedYear-" . str_pad($selectedMonth, 2, "0", STR_PAD_LEFT) . "-01";
$endDate = date("Y-m-t", strtotime($startDate));
$monthName = date('F Y', strtotime($startDate));

// Get data for the report
$counts = $db->getAdminDashboardCounts($startDate, $endDate);
$monthlyStats = $db->getMonthlyEarningsStats($startDate, $endDate);
$chartData = $db->getAdminMonthChartData($startDate, $endDate);

// Extract values
$pending = $counts['pending_rentals'] ?? 0;
$pending_maintenance = $counts['pending_maintenance'] ?? 0;
$unpaid_invoices = $counts['unpaid_invoices'] ?? 0;
$overdue_invoices = $counts['overdue_invoices'] ?? 0;
$total_earnings = $monthlyStats['total_earnings'] ?? 0;
$paid_invoices_count = $monthlyStats['paid_invoices_count'] ?? 0;
$new_messages_count = $monthlyStats['new_messages_count'] ?? 0;

// Get rental and maintenance requests data
$rentalRequestsData = $db->getTotalRentalRequests($startDate, $endDate);
$maintenanceRequestsData = $db->getTotalMaintenanceRequests($startDate, $endDate);

// Get detailed data for the report
$paidInvoices = $db->getPaidInvoicesForPeriod($startDate, $endDate);
$rentalRequests = $db->getRentalRequestsForPeriod($startDate, $endDate);
$maintenanceRequests = $db->getMaintenanceRequestsForPeriod($startDate, $endDate);
$messages = $db->getMessagesForPeriod($startDate, $endDate);

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Function to format date
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="ASRT_Monthly_Report_' . $monthName . '.pdf"');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report - <?= $monthName ?> | ASRT Management</title>
    <style>
        @page {
            margin: 20px;
            size: letter;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #6366f1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #6366f1;
            font-size: 24px;
            margin: 0 0 5px 0;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 16px;
            margin: 0;
        }
        
        .header .period {
            color: #888;
            font-size: 14px;
            margin: 10px 0 0 0;
        }
        
        .summary-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            background: #6366f1;
            color: white;
            padding: 8px 15px;
            font-size: 14px;
            font-weight: bold;
            margin: 20px 0 15px 0;
            border-radius: 4px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
            background: #f9fafb;
        }
        
        .stat-card.primary { border-left: 4px solid #6366f1; }
        .stat-card.warning { border-left: 4px solid #f59e0b; }
        .stat-card.info { border-left: 4px solid #06b6d4; }
        .stat-card.danger { border-left: 4px solid #ef4444; }
        .stat-card.success { border-left: 4px solid #10b981; }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .financial-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .financial-title {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .financial-amount {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .financial-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .financial-stat {
            text-align: center;
        }
        
        .financial-stat-value {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .financial-stat-label {
            font-size: 11px;
            opacity: 0.8;
        }
        
        .table-container {
            margin-bottom: 25px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        
        .data-table th {
            background: #6366f1;
            color: white;
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
        }
        
        .data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .data-table tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-accepted { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-unpaid { background: #fef3c7; color: #92400e; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-in-progress { background: #dbeafe; color: #1e40af; }
        .status-submitted { background: #f3f4f6; color: #374151; }
        
        .breakdown-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .breakdown-card {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            background: #f9fafb;
        }
        
        .breakdown-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #6366f1;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 11px;
        }
        
        .breakdown-label {
            color: #666;
        }
        
        .breakdown-value {
            font-weight: 600;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
            background: #f9fafb;
            border-radius: 6px;
        }
        
        .summary-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .summary-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
        }
        
        .summary-item-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .summary-item-value {
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mb-20 {
            margin-bottom: 20px;
        }
        
        .mt-20 {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>ASRT Commercial Spaces</h1>
            <div class="subtitle">Monthly Management Report</div>
            <div class="period"><?= $monthName ?></div>
            <div style="font-size: 11px; color: #888; margin-top: 5px;">
                Generated on <?= date('F j, Y \a\t g:i A') ?>
            </div>
        </div>
        
        <!-- Executive Summary -->
        <div class="summary-section">
            <div class="section-title">Executive Summary</div>
            
            <!-- Financial Summary -->
            <div class="financial-summary">
                <div class="financial-title">Monthly Revenue</div>
                <div class="financial-amount"><?= formatCurrency($total_earnings) ?></div>
                <div class="financial-stats">
                    <div class="financial-stat">
                        <div class="financial-stat-value"><?= $paid_invoices_count ?></div>
                        <div class="financial-stat-label">Paid Invoices</div>
                    </div>
                    <div class="financial-stat">
                        <div class="financial-stat-value"><?= $rentalRequestsData['total'] ?? 0 ?></div>
                        <div class="financial-stat-label">Rental Requests</div>
                    </div>
                    <div class="financial-stat">
                        <div class="financial-stat-value"><?= $maintenanceRequestsData['total'] ?? 0 ?></div>
                        <div class="financial-stat-label">Maintenance Requests</div>
                    </div>
                </div>
            </div>
            
            <!-- Key Metrics -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-value"><?= $pending ?></div>
                    <div class="stat-label">Pending Rentals</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-value"><?= $pending_maintenance ?></div>
                    <div class="stat-label">Pending Maintenance</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-value"><?= $unpaid_invoices ?></div>
                    <div class="stat-label">Unpaid Invoices</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-value"><?= $overdue_invoices ?></div>
                    <div class="stat-label">Overdue Invoices</div>
                </div>
            </div>
        </div>
        
        <!-- Detailed Breakdown -->
        <div class="breakdown-section">
            <!-- Rental Requests Breakdown -->
            <div class="breakdown-card">
                <div class="breakdown-title">Rental Requests Breakdown</div>
                <div class="breakdown-item">
                    <span class="breakdown-label">Total Requests:</span>
                    <span class="breakdown-value"><?= $rentalRequestsData['total'] ?? 0 ?></span>
                </div>
                <div class="breakdown-item">
                    <span class="breakdown-label">Pending Approval:</span>
                    <span class="breakdown-value"><?= $rentalRequestsData['pending'] ?? 0 ?></span>
                </div>
                <div class="breakdown-item">
                    <span class="breakdown-label">Accepted:</span>
                    <span class="breakdown-value"><?= $rentalRequestsData['accepted'] ?? 0 ?></span>
                </div>
                <div class="breakdown-item">
                    <span class="breakdown-label">Rejected:</span>
                    <span class="breakdown-value"><?= $rentalRequestsData['rejected'] ?? 0 ?></span>
                </div>
                <div class="breakdown-item">
                    <span class="breakdown-label">Approval Rate:</span>
                    <span class="breakdown-value">
                        <?php 
                        $total = $rentalRequestsData['total'] ?? 1;
                        $accepted = $rentalRequestsData['accepted'] ?? 0;
                        echo $total > 0 ? round(($accepted / $total) * 100, 1) . '%' : '0%';
                        ?>
                    </span>
                </div>
            </div>
            
            <!-- Maintenance Requests Breakdown -->
            <div class="breakdown-card">
                <div class="breakdown-title">Maintenance Requests Breakdown</div>
                <div class="breakdown-item">
                    <span class="breakdown-label">Total Requests:</span>
                    <span class="breakdown-value"><?= $maintenanceRequestsData['total'] ?? 0 ?></span>
                </div>
                <div class="breakdown-item">
                    <span class="breakdown-label">Submitted:</span>
                    <span class="breakdown-value"><?= $maintenanceRequestsData['submitted'] ?? 0 ?></span>
                </div>
                <div class="breakdown-item">
                    <span class="breakdown-label">In Progress:</span>
                    <span class="breakdown-value"><?= $maintenanceRequestsData['in_progress'] ?? 0 ?></span>
                </div>
                <div class="breakdown-item">
                    <span class="breakdown-label">Completed:</span>
                    <span class="breakdown-value"><?= $maintenanceRequestsData['completed'] ?? 0 ?></span>
                </div>
                <div class="breakdown-item">
                    <span class="breakdown-label">Completion Rate:</span>
                    <span class="breakdown-value">
                        <?php 
                        $total = $maintenanceRequestsData['total'] ?? 1;
                        $completed = $maintenanceRequestsData['completed'] ?? 0;
                        echo $total > 0 ? round(($completed / $total) * 100, 1) . '%' : '0%';
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Paid Invoices Section -->
        <div class="section-title">Paid Invoices - <?= $monthName ?></div>
        <div class="table-container">
            <?php if (!empty($paidInvoices)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Client</th>
                            <th>Space</th>
                            <th>Amount</th>
                            <th>Payment Date</th>
                            <th>Period</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paidInvoices as $invoice): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($invoice['Invoice_ID']) ?></td>
                                <td>
                                    <?= htmlspecialchars($invoice['Client_fn'] ?? '') ?> 
                                    <?= htmlspecialchars($invoice['Client_ln'] ?? '') ?>
                                </td>
                                <td><?= htmlspecialchars($invoice['SpaceName'] ?? 'N/A') ?></td>
                                <td class="text-right"><?= formatCurrency($invoice['InvoiceTotal'] ?? 0) ?></td>
                                <td><?= formatDate($invoice['InvoiceDate'] ?? '') ?></td>
                                <td>
                                    <?= formatDate($invoice['InvoiceDate'] ?? '') ?> - 
                                    <?= formatDate($invoice['EndDate'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background: #e5e7eb; font-weight: bold;">
                            <td colspan="3" class="text-right">Total Revenue:</td>
                            <td class="text-right"><?= formatCurrency($total_earnings) ?></td>
                            <td colspan="2"><?= $paid_invoices_count ?> invoices</td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No paid invoices for <?= $monthName ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Rental Requests Details -->
        <div class="section-title">Rental Requests Details</div>
        <div class="table-container">
            <?php if (!empty($rentalRequests)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Client</th>
                            <th>Space</th>
                            <th>Requested Period</th>
                            <th>Status</th>
                            <th>Requested Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rentalRequests as $request): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($request['Request_ID']) ?></td>
                                <td>
                                    <?= htmlspecialchars($request['Client_fn'] ?? '') ?> 
                                    <?= htmlspecialchars($request['Client_ln'] ?? '') ?>
                                </td>
                                <td><?= htmlspecialchars($request['SpaceName'] ?? 'N/A') ?></td>
                                <td>
                                    <?= formatDate($request['StartDate'] ?? '') ?> - 
                                    <?= formatDate($request['EndDate'] ?? '') ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($request['Status'] ?? 'pending') ?>">
                                        <?= htmlspecialchars($request['Status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><?= formatDate($request['Requested_At'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No rental requests for <?= $monthName ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Maintenance Requests Details -->
        <div class="section-title">Maintenance Requests Details</div>
        <div class="table-container">
            <?php if (!empty($maintenanceRequests)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Client</th>
                            <th>Space</th>
                            <th>Request Date</th>
                            <th>Status</th>
                            <th>Handyman</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maintenanceRequests as $request): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($request['Request_ID']) ?></td>
                                <td>
                                    <?= htmlspecialchars($request['Client_fn'] ?? '') ?> 
                                    <?= htmlspecialchars($request['Client_ln'] ?? '') ?>
                                </td>
                                <td><?= htmlspecialchars($request['SpaceName'] ?? 'N/A') ?></td>
                                <td><?= formatDate($request['RequestDate'] ?? '') ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $request['Status'] ?? 'submitted')) ?>">
                                        <?= htmlspecialchars($request['Status'] ?? 'Submitted') ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($request['Handyman_fn'] ?? 'N/A') ?> 
                                    <?= htmlspecialchars($request['Handyman_ln'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No maintenance requests for <?= $monthName ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Messages Summary -->
        <div class="section-title">Customer Messages Summary</div>
        <div class="summary-row">
            <div class="summary-item">
                <div class="summary-item-label">Total Messages</div>
                <div class="summary-item-value"><?= $new_messages_count ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-item-label">Period</div>
                <div class="summary-item-value"><?= $monthName ?></div>
            </div>
        </div>
        
        <?php if (!empty($messages)): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Message Preview</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($messages, 0, 10) as $message): ?>
                            <tr>
                                <td><?= formatDate($message['Sent_At'] ?? '') ?></td>
                                <td><?= htmlspecialchars($message['Client_Name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($message['Client_Email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($message['Client_Phone'] ?? '') ?></td>
                                <td>
                                    <?= strlen($message['Message_Text'] ?? '') > 50 
                                        ? substr(htmlspecialchars($message['Message_Text'] ?? ''), 0, 50) . '...' 
                                        : htmlspecialchars($message['Message_Text'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($messages) > 10): ?>
                            <tr>
                                <td colspan="5" class="text-center" style="font-style: italic;">
                                    ... and <?= count($messages) - 10 ?> more messages
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data">No messages for <?= $monthName ?></div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <div>ASRT Commercial Spaces Management System</div>
            <div>Monthly Report - <?= $monthName ?></div>
            <div>Generated on <?= date('F j, Y \a\t g:i A') ?></div>
            <div style="margin-top: 10px;">
                This report contains confidential business information.
            </div>
        </div>
    </div>
</body>
</html>