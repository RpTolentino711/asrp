<?php
require 'database/database.php';

try {
    $db = new Database();
    $units = $db->getLiveAvailableUnits(3);
    echo "Success! Found " . count($units) . " units";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>