<?php
session_start();
require '../database/database.php';

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

$db = new Database();

// Get statistics for the selected period
$counts = $db->getAdminDashboardCounts($startDate, $endDate);
$monthlyStats = $db->getMonthlyEarningsStats($startDate, $endDate);
$rentalRequestsData = $db->getTotalRentalRequests($startDate, $endDate);
$maintenanceRequestsData = $db->getTotalMaintenanceRequests($startDate, $endDate);

// Extract values
$pending = $counts['pending_rentals'] ?? 0;
$pending_maintenance = $counts['pending_maintenance'] ?? 0;
$unpaid_invoices = $counts['unpaid_invoices'] ?? 0;
$overdue_invoices = $counts['overdue_invoices'] ?? 0;
$total_earnings = $monthlyStats['total_earnings'] ?? 0;
$new_messages_count = $monthlyStats['new_messages_count'] ?? 0;

// Get detailed data for the report
$rentalRequests = $db->getRows("
    SELECT rr.*, c.Client_fn, c.Client_ln, c.Client_Email, s.Name as Space_Name 
    FROM rentalrequest rr 
    LEFT JOIN client c ON rr.Client_ID = c.Client_ID 
    LEFT JOIN space s ON rr.Space_ID = s.Space_ID 
    WHERE rr.Requested_At BETWEEN ? AND ? 
    ORDER BY rr.Requested_At DESC
", [$startDate, $endDate . ' 23:59:59']);

$maintenanceRequests = $db->getRows("
    SELECT mr.*, c.Client_fn, c.Client_ln, s.Name as Space_Name 
    FROM maintenancerequest mr 
    LEFT JOIN client c ON mr.Client_ID = c.Client_ID 
    LEFT JOIN space s ON mr.Space_ID = s.Space_ID 
    WHERE mr.RequestDate BETWEEN ? AND ? 
    ORDER BY mr.RequestDate DESC
", [$startDate, $endDate . ' 23:59:59']);

$freeMessages = $db->getRows("
    SELECT * FROM free_message 
    WHERE Sent_At BETWEEN ? AND ? 
    ORDER BY Sent_At DESC
", [$startDate, $endDate . ' 23:59:59']);

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="monthly_report_' . $selectedMonth . '_' . $selectedYear . '.pdf"');

// Create PDF content (simplified version - you might want to use a PDF library like TCPDF)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Monthly Report - <?= $monthName ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .section { margin-bottom: 25px; }
        .section-title { background: #f5f5f5; padding: 8px; font-weight: bold; border-left: 4px solid #333; }
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 15px 0; }
        .stat-card { border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
        .stat-value { font-size: 24px; font-weight: bold; }
        .stat-label { color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
        .breakdown { font-size: 12px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ASRT Management - Monthly Report</h1>
        <h2><?= $monthName ?></h2>
        <p>Period: <?= date('M d, Y', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?></p>
    </div>

    <div class="section">
        <div class="section-title">Executive Summary</div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">₱<?= number_format($total_earnings, 2) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $rentalRequestsData['total'] ?? 0 ?></div>
                <div class="stat-label">Rental Requests</div>
                <div class="breakdown">P:<?= $rentalRequestsData['pending'] ?? 0 ?> A:<?= $rentalRequestsData['accepted'] ?? 0 ?> R:<?= $rentalRequestsData['rejected'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $maintenanceRequestsData['total'] ?? 0 ?></div>
                <div class="stat-label">Maintenance Requests</div>
                <div class="breakdown">S:<?= $maintenanceRequestsData['submitted'] ?? 0 ?> IP:<?= $maintenanceRequestsData['in_progress'] ?? 0 ?> C:<?= $maintenanceRequestsData['completed'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $new_messages_count ?></div>
                <div class="stat-label">Messages Received</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Pending Actions</div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $pending ?></div>
                <div class="stat-label">Pending Rental Approvals</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $pending_maintenance ?></div>
                <div class="stat-label">Active Maintenance Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $unpaid_invoices ?></div>
                <div class="stat-label">Unpaid Invoices</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $overdue_invoices ?></div>
                <div class="stat-label">Overdue Invoices</div>
            </div>
        </div>
    </div>

    <?php if (!empty($rentalRequests)): ?>
    <div class="section">
        <div class="section-title">Rental Requests Details</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Space</th>
                    <th>Period</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rentalRequests as $request): ?>
                <tr>
                    <td><?= date('M d, Y', strtotime($request['Requested_At'])) ?></td>
                    <td><?= htmlspecialchars($request['Client_fn'] . ' ' . $request['Client_ln']) ?></td>
                    <td><?= htmlspecialchars($request['Space_Name'] ?? 'N/A') ?></td>
                    <td><?= date('M d, Y', strtotime($request['StartDate'])) ?> - <?= date('M d, Y', strtotime($request['EndDate'])) ?></td>
                    <td><?= $request['Status'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($maintenanceRequests)): ?>
    <div class="section">
        <div class="section-title">Maintenance Requests Details</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Space</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($maintenanceRequests as $request): ?>
                <tr>
                    <td><?= date('M d, Y', strtotime($request['RequestDate'])) ?></td>
                    <td><?= htmlspecialchars($request['Client_fn'] . ' ' . $request['Client_ln']) ?></td>
                    <td><?= htmlspecialchars($request['Space_Name'] ?? 'N/A') ?></td>
                    <td><?= $request['Status'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($freeMessages)): ?>
    <div class="section">
        <div class="section-title">Messages Received</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Message Preview</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($freeMessages as $message): ?>
                <tr>
                    <td><?= date('M d, Y H:i', strtotime($message['Sent_At'])) ?></td>
                    <td><?= htmlspecialchars($message['Client_Name']) ?></td>
                    <td><?= htmlspecialchars($message['Client_Email']) ?></td>
                    <td><?= htmlspecialchars(substr($message['Message_Text'], 0, 50)) ?>...</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="section">
        <div class="section-title">Report Information</div>
        <p><strong>Generated on:</strong> <?= date('F j, Y \a\t g:i A') ?></p>
        <p><strong>Report Period:</strong> <?= $monthName ?></p>
        <p><strong>Total Records:</strong> 
            <?= count($rentalRequests) ?> rental requests, 
            <?= count($maintenanceRequests) ?> maintenance requests, 
            <?= count($freeMessages) ?> messages
        </p>
    </div>
</body>
</html>
<?php
// Note: This generates an HTML file that can be printed as PDF. 
// For actual PDF generation, consider using libraries like TCPDF, Dompdf, or mPDF.
?>