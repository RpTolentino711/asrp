<?php
// AJAX endpoint to get/set client typing status for a specific invoice
// Usage:
//   GET:  invoice_client_typing.php?invoice_id=123  => { "typing": true/false }
//   POST: invoice_client_typing.php?invoice_id=123&typing=1  (client sets typing)

session_start();
header('Content-Type: application/json');

$invoice_id = isset($_REQUEST['invoice_id']) ? intval($_REQUEST['invoice_id']) : 0;
if (!$invoice_id) {
    echo json_encode(["error" => "Missing invoice_id"]);
    exit;
}

$typing_dir = __DIR__ . '/../tmp_typing/';
if (!is_dir($typing_dir)) mkdir($typing_dir, 0777, true);
$typing_file = $typing_dir . 'client_typing_' . $invoice_id . '.txt';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only client should be able to set typing, but for demo, allow all
    $typing = isset($_POST['typing']) && $_POST['typing'] == '1';
    if ($typing) {
        file_put_contents($typing_file, time());
    } else {
        @unlink($typing_file);
    }
    echo json_encode(["success" => true]);
    exit;
}

// GET: check if client is typing (active in last 7 seconds)
$typing = false;
if (file_exists($typing_file)) {
    $last = intval(file_get_contents($typing_file));
    if ($last > (time() - 7)) {
        $typing = true;
    }
}
echo json_encode(["typing" => $typing]);
