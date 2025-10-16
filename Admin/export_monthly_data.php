<?php
session_start();
require '../database/database.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

$db = new Database();

// Get selected month/year from request
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$exportType = $_GET['type'] ?? 'excel';

// Validate inputs
if ($selectedMonth < 1 || $selectedMonth > 12) $selectedMonth = date('m');
if ($selectedYear < 2020 || $selectedYear > 2030) $selectedYear = date('Y');

// Get date range
$startDate = "$selectedYear-" . str_pad($selectedMonth, 2, "0", STR_PAD_LEFT) . "-01";
$endDate = date("Y-m-t", strtotime($startDate));
$monthName = date('F Y', strtotime($startDate));

// Get all data for the selected month
$monthlyStats = $db->getMonthlyEarningsStats($startDate, $endDate);
$rentalRequestsData = $db->getTotalRentalRequests($startDate, $endDate);
$maintenanceRequestsData = $db->getTotalMaintenanceRequests($startDate, $endDate);

// Get detailed data
$detailedRentals = $db->getDetailedRentalData($startDate, $endDate);
$detailedMaintenance = $db->getDetailedMaintenanceData($startDate, $endDate);
$detailedInvoices = $db->getDetailedInvoiceData($startDate, $endDate);
$occupancyData = $db->getOccupancyData($startDate, $endDate);
$financialSummary = $db->getFinancialSummary($startDate, $endDate);

if ($exportType === 'excel') {
    exportToExcel($monthName, $monthlyStats, $rentalRequestsData, $maintenanceRequestsData, $detailedRentals, $detailedMaintenance, $detailedInvoices, $occupancyData, $financialSummary);
} else {
    exportToPDF($monthName, $monthlyStats, $rentalRequestsData, $maintenanceRequestsData, $detailedRentals, $detailedMaintenance, $detailedInvoices, $occupancyData, $financialSummary);
}

function exportToExcel($monthName, $monthlyStats, $rentalRequestsData, $maintenanceRequestsData, $detailedRentals, $detailedMaintenance, $detailedInvoices, $occupancyData, $financialSummary) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="ASRT_Monthly_Report_' . str_replace(' ', '_', $monthName) . '.xls"');
    
    // CORRECTED FINANCIAL CALCULATIONS
    $totalRevenue = $financialSummary['total_revenue'] ?? 0;
    $paidInvoices = $financialSummary['paid_count'] ?? 0;
    $overdueInvoices = $financialSummary['overdue_count'] ?? 0;
    $pendingInvoices = $financialSummary['pending_count'] ?? 0;
    $unpaidInvoices = $overdueInvoices + $pendingInvoices;
    $collectionRate = $financialSummary['collection_rate'] ?? 0;
    
    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #2c3e50; color: white; font-weight: bold; }
        .summary-header { background-color: #34495e; color: white; font-size: 14px; font-weight: bold; }
        .metric-header { background-color: #ecf0f1; font-weight: bold; }
        .positive { color: #27ae60; font-weight: bold; }
        .negative { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .section-title { background-color: #3498db; color: white; font-size: 16px; padding: 10px; }
        .financial-summary { background-color: #e8f6f3; }
        .operational-summary { background-color: #fef9e7; }
        .center { text-align: center; }
        .right { text-align: right; }
    </style>";
    echo "</head>";
    echo "<body>";
    
    // ==================== SHEET 1: EXECUTIVE SUMMARY ====================
    echo "<h1 style='text-align: center; color: #2c3e50;'>ASRT MANAGEMENT</h1>";
    echo "<h2 style='text-align: center; color: #34495e;'>MONTHLY PERFORMANCE DASHBOARD - " . strtoupper($monthName) . "</h2>";
    echo "<p style='text-align: center;'>Generated on: " . date('F j, Y g:i A') . "</p>";
    echo "<hr>";
    
    // FINANCIAL SUMMARY - UPDATED
    echo "<h3>📊 FINANCIAL SUMMARY</h3>";
    echo "<table>";
    echo "<tr class='financial-summary'><td>Total Revenue</td><td class='right positive'>₱" . number_format($totalRevenue, 2) . "</td></tr>";
    echo "<tr class='financial-summary'><td>Paid Invoices</td><td class='right positive'>" . $paidInvoices . "</td></tr>";
    echo "<tr class='financial-summary'><td>Unpaid Invoices</td><td class='right warning'>" . $unpaidInvoices . "</td></tr>";
    echo "<tr class='financial-summary'><td>Collection Rate</td><td class='right positive'>" . number_format($collectionRate, 1) . "%</td></tr>";
    echo "</table>";
    echo "<br>";
    
    // OPERATIONAL METRICS
    echo "<h3>⚙️ OPERATIONAL METRICS</h3>";
    echo "<table>";
    echo "<tr class='operational-summary'><td>Total Rental Requests</td><td class='right'>" . ($rentalRequestsData['total'] ?? 0) . "</td></tr>";
    echo "<tr class='operational-summary'><td>&nbsp;&nbsp;• Pending</td><td class='right warning'>" . ($rentalRequestsData['pending'] ?? 0) . "</td></tr>";
    echo "<tr class='operational-summary'><td>&nbsp;&nbsp;• Accepted</td><td class='right positive'>" . ($rentalRequestsData['accepted'] ?? 0) . "</td></tr>";
    echo "<tr class='operational-summary'><td>&nbsp;&nbsp;• Rejected</td><td class='right negative'>" . ($rentalRequestsData['rejected'] ?? 0) . "</td></tr>";
    echo "<tr class='operational-summary'><td>Total Maintenance Requests</td><td class='right'>" . ($maintenanceRequestsData['total'] ?? 0) . "</td></tr>";
    echo "<tr class='operational-summary'><td>&nbsp;&nbsp;• Submitted</td><td class='right warning'>" . ($maintenanceRequestsData['submitted'] ?? 0) . "</td></tr>";
    echo "<tr class='operational-summary'><td>&nbsp;&nbsp;• In Progress</td><td class='right warning'>" . ($maintenanceRequestsData['in_progress'] ?? 0) . "</td></tr>";
    echo "<tr class='operational-summary'><td>&nbsp;&nbsp;• Completed</td><td class='right positive'>" . ($maintenanceRequestsData['completed'] ?? 0) . "</td></tr>";
    echo "<tr class='operational-summary'><td>New Messages</td><td class='right'>" . ($monthlyStats['new_messages_count'] ?? 0) . "</td></tr>";
    echo "</table>";
    echo "<br><br>";
    
    // ==================== SHEET 2: RENTAL REQUESTS DETAIL ====================
    echo "<h3 class='section-title'>🏠 RENTAL REQUESTS DETAIL</h3>";
    echo "<table>";
    echo "<tr><th>Client Name</th><th>Unit</th><th>Request Date</th><th>Status</th><th>Start Date</th><th>End Date</th><th>Duration</th><th>Price</th><th>Contact</th></tr>";
    foreach ($detailedRentals as $rental) {
        $duration = date_diff(
            new DateTime($rental['StartDate']), 
            new DateTime($rental['EndDate'])
        )->days;
        
        $statusClass = '';
        if ($rental['Status'] == 'Accepted') $statusClass = 'positive';
        if ($rental['Status'] == 'Rejected') $statusClass = 'negative';
        if ($rental['Status'] == 'Pending') $statusClass = 'warning';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($rental['Client_fn'] . ' ' . $rental['Client_ln']) . "</td>";
        echo "<td>" . htmlspecialchars($rental['UnitName'] ?? 'N/A') . "</td>";
        echo "<td>" . date('M j, Y', strtotime($rental['Requested_At'])) . "</td>";
        echo "<td class='$statusClass'>" . htmlspecialchars($rental['Status']) . "</td>";
        echo "<td>" . date('M j, Y', strtotime($rental['StartDate'])) . "</td>";
        echo "<td>" . date('M j, Y', strtotime($rental['EndDate'])) . "</td>";
        echo "<td class='center'>" . $duration . " days</td>";
        echo "<td class='right'>₱" . number_format($rental['Price'] ?? 0, 2) . "</td>";
        echo "<td>" . htmlspecialchars($rental['Client_Email'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<br><br>";
    
    // ==================== SHEET 3: MAINTENANCE REQUESTS ====================
    echo "<h3 class='section-title'>🔧 MAINTENANCE REQUESTS</h3>";
    echo "<table>";
    echo "<tr><th>Client Name</th><th>Unit</th><th>Issue Date</th><th>Status</th><th>Handyman</th><th>Completion Date</th><th>Days to Resolve</th></tr>";
    foreach ($detailedMaintenance as $maintenance) {
        $completionDate = $maintenance['CompletionDate'] ?? null;
        $daysToResolve = 'N/A';
        
        if ($completionDate && $maintenance['Status'] == 'Completed') {
            $daysToResolve = date_diff(
                new DateTime($maintenance['RequestDate']),
                new DateTime($completionDate)
            )->days;
        }
        
        $statusClass = '';
        if ($maintenance['Status'] == 'Completed') $statusClass = 'positive';
        if ($maintenance['Status'] == 'In Progress') $statusClass = 'warning';
        if ($maintenance['Status'] == 'Submitted') $statusClass = 'negative';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($maintenance['Client_fn'] . ' ' . $maintenance['Client_ln']) . "</td>";
        echo "<td>" . htmlspecialchars($maintenance['UnitName'] ?? 'N/A') . "</td>";
        echo "<td>" . date('M j, Y', strtotime($maintenance['RequestDate'])) . "</td>";
        echo "<td class='$statusClass'>" . htmlspecialchars($maintenance['Status']) . "</td>";
        echo "<td>" . htmlspecialchars($maintenance['Handyman_fn'] . ' ' . $maintenance['Handyman_ln'] ?? 'Not Assigned') . "</td>";
        echo "<td>" . ($completionDate ? date('M j, Y', strtotime($completionDate)) : 'N/A') . "</td>";
        echo "<td class='center'>" . $daysToResolve . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<br><br>";
    
    // ==================== SHEET 4: FINANCIAL REPORT ====================
    echo "<h3 class='section-title'>💰 FINANCIAL REPORT</h3>";
    
    // INVOICE SUMMARY
    echo "<h4>Invoice Summary</h4>";
    echo "<table>";
    // REMOVED: Days Overdue column
    echo "<tr><th>Invoice ID</th><th>Client</th><th>Unit</th><th>Amount</th><th>Issue Date</th><th>Due Date</th><th>Status</th></tr>";
    
    foreach ($detailedInvoices as $invoice) {        
        $statusClass = '';
        if ($invoice['Status'] == 'paid') $statusClass = 'positive';
        if ($invoice['Status'] == 'unpaid') $statusClass = 'warning';
        
        echo "<tr>";
        echo "<td>INV-" . str_pad($invoice['Invoice_ID'], 4, '0', STR_PAD_LEFT) . "</td>";
        echo "<td>" . htmlspecialchars($invoice['Client_fn'] . ' ' . $invoice['Client_ln']) . "</td>";
        echo "<td>" . htmlspecialchars($invoice['UnitName'] ?? 'N/A') . "</td>";
        echo "<td class='right'>₱" . number_format($invoice['InvoiceTotal'], 2) . "</td>";
        echo "<td>" . date('M j, Y', strtotime($invoice['InvoiceDate'])) . "</td>";
        echo "<td>" . date('M j, Y', strtotime($invoice['EndDate'])) . "</td>";
        echo "<td class='$statusClass'>" . ucfirst($invoice['Status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<br>";
    
    // REVENUE BREAKDOWN - UPDATED
    echo "<h4>Revenue Breakdown</h4>";
    echo "<table>";
    echo "<tr class='metric-header'><td>Category</td><td class='right'>Amount</td><td class='right'>Percentage</td></tr>";
    
    $spaceRevenue = $financialSummary['space_revenue'] ?? 0;
    $apartmentRevenue = $financialSummary['apartment_revenue'] ?? 0;
    $potentialRevenue = $financialSummary['potential_revenue'] ?? 0;
    
    echo "<tr><td>Space Rentals</td><td class='right positive'>₱" . number_format($spaceRevenue, 2) . "</td><td class='right'>" . ($totalRevenue > 0 ? number_format(($spaceRevenue/$totalRevenue)*100, 1) : 0) . "%</td></tr>";
    echo "<tr><td>Apartment Rentals</td><td class='right positive'>₱" . number_format($apartmentRevenue, 2) . "</td><td class='right'>" . ($totalRevenue > 0 ? number_format(($apartmentRevenue/$totalRevenue)*100, 1) : 0) . "%</td></tr>";
    echo "<tr><td>Potential Revenue (Unpaid)</td><td class='right warning'>₱" . number_format($potentialRevenue, 2) . "</td><td class='right'>N/A</td></tr>";
    echo "<tr class='metric-header'><td><strong>Total Collected Revenue</strong></td><td class='right positive'><strong>₱" . number_format($totalRevenue, 2) . "</strong></td><td class='right'><strong>100%</strong></td></tr>";
    echo "</table>";
    echo "<br><br>";
    
    // ==================== SHEET 5: OCCUPANCY & UTILIZATION ====================
    echo "<h3 class='section-title'>📈 OCCUPANCY & UTILIZATION</h3>";
    
    // UNIT PERFORMANCE
    echo "<h4>Unit Performance</h4>";
    echo "<table>";
    echo "<tr><th>Unit</th><th>Type</th><th>Status</th><th>Client</th><th>Monthly Rate</th><th>Occupancy Days</th><th>Utilization %</th><th>Revenue</th></tr>";
    
    $totalUnits = 0;
    $occupiedUnits = 0;
    $totalUtilization = 0;
    
    foreach ($occupancyData as $unit) {
        $totalUnits++;
        if ($unit['Status'] == 'Occupied') $occupiedUnits++;
        
        $utilization = $unit['utilization_rate'] ?? 0;
        $totalUtilization += $utilization;
        
        $statusClass = $unit['Status'] == 'Occupied' ? 'positive' : 'negative';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($unit['Name']) . "</td>";
        echo "<td>" . htmlspecialchars($unit['SpaceTypeName']) . "</td>";
        echo "<td class='$statusClass'>" . htmlspecialchars($unit['Status']) . "</td>";
        echo "<td>" . htmlspecialchars($unit['Client_fn'] . ' ' . $unit['Client_ln'] ?? 'Vacant') . "</td>";
        echo "<td class='right'>₱" . number_format($unit['Price'], 2) . "</td>";
        echo "<td class='center'>" . ($unit['occupied_days'] ?? 0) . "/" . $unit['month_days'] . "</td>";
        echo "<td class='center'>" . number_format($utilization, 1) . "%</td>";
        echo "<td class='right positive'>₱" . number_format($unit['revenue'] ?? 0, 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<br>";
    
    // OCCUPANCY SUMMARY
    echo "<h4>Occupancy Summary</h4>";
    echo "<table>";
    $occupancyRate = $financialSummary['occupancy_rate'] ?? 0;
    $avgUtilization = $totalUnits > 0 ? $totalUtilization / $totalUnits : 0;
    
    echo "<tr class='metric-header'><td>Total Units</td><td class='right'>" . $totalUnits . "</td></tr>";
    echo "<tr class='metric-header'><td>Occupied Units</td><td class='right positive'>" . $occupiedUnits . "</td></tr>";
    echo "<tr class='metric-header'><td>Vacant Units</td><td class='right negative'>" . ($totalUnits - $occupiedUnits) . "</td></tr>";
    echo "<tr class='metric-header'><td>Overall Occupancy Rate</td><td class='right positive'>" . number_format($occupancyRate, 1) . "%</td></tr>";
    echo "<tr class='metric-header'><td>Average Utilization Rate</td><td class='right positive'>" . number_format($avgUtilization, 1) . "%</td></tr>";
    echo "</table>";
    
    echo "</body>";
    echo "</html>";
    exit;
}

function exportToPDF($monthName, $monthlyStats, $rentalRequestsData, $maintenanceRequestsData, $detailedRentals, $detailedMaintenance, $detailedInvoices, $occupancyData, $financialSummary) {
    // Simple PDF output (for basic printing)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="ASRT_Report_' . str_replace(' ', '_', $monthName) . '.pdf"');
    
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1, h2, h3 { color: #2c3e50; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .summary { background-color: #e8f4fd; }
            .header { text-align: center; margin-bottom: 30px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>ASRT Management</h1>
            <h2>Monthly Report - " . $monthName . "</h2>
            <p>Generated on: " . date('F j, Y g:i A') . "</p>
        </div>
        
        <h3>Executive Summary</h3>
        <table>
            <tr class='summary'><td>Total Revenue</td><td>₱" . number_format($financialSummary['total_revenue'] ?? 0, 2) . "</td></tr>
            <tr class='summary'><td>Paid Invoices</td><td>" . ($financialSummary['paid_count'] ?? 0) . "</td></tr>
            <tr class='summary'><td>Overdue Invoices</td><td>" . ($financialSummary['overdue_count'] ?? 0) . "</td></tr>
            <tr class='summary'><td>Collection Rate</td><td>" . number_format($financialSummary['collection_rate'] ?? 0, 1) . "%</td></tr>
            <tr class='summary'><td>Total Rental Requests</td><td>" . ($rentalRequestsData['total'] ?? 0) . "</td></tr>
            <tr class='summary'><td>Total Maintenance Requests</td><td>" . ($maintenanceRequestsData['total'] ?? 0) . "</td></tr>
        </table>
    ";
    
    $html .= "</body></html>";
    
    echo $html;
    exit;
}
?>