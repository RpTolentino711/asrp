<?php
// Require your single database file. Adjust path to go up one level.
require '../database/database.php';
session_start();

$db = new Database();

// --- Authentication Check ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) { 
    header('Location: login.php'); 
    exit(); 
}

// --- Handle POST Request ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];

    if ($action == 'accept') {
        // Call the single, transactional method to accept the request
        if ($db->acceptRentalRequest($request_id)) {
            $_SESSION['admin_message'] = "Request #{$request_id} has been successfully approved and an invoice was generated.";
        } else {
            $_SESSION['admin_error'] = "Failed to approve request #{$request_id}. It may have already been processed, or a database error occurred.";
        }
    } elseif ($action == 'reject') {
        // Call the method to reject the request
        if ($db->rejectRentalRequest($request_id)) {
            $_SESSION['admin_message'] = "Request #{$request_id} has been rejected.";
        } else {
            $_SESSION['admin_error'] = "Failed to reject request #{$request_id}.";
        }
    }
}

// Redirect back to the requests list to show the result
header('Location: view_rental_requests.php');
exit();
?>