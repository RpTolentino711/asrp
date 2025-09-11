<?php
require_once '../database/database.php';
header('Content-Type: application/json');

$db = new Database();
$units = $db->getHomepageAvailableUnits(10); // You can adjust the limit as needed

echo json_encode($units);
