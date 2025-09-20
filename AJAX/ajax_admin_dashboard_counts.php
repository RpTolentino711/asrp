<?php
require_once '../database/database.php';
session_start();
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit('Forbidden');
}
$db = new Database();
$counts = $db->getAdminDashboardCounts();
header('Content-Type: application/json');
echo json_encode($counts);
