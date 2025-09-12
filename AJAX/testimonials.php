<?php
require_once '../database/database.php';

header('Content-Type: application/json');

$db = new Database();
$testimonials = $db->getHomepageTestimonials(6);

$result = [];
foreach ($testimonials as $fb) {
    $result[] = [
        'Rating' => $fb['Rating'],
        'Comments' => $fb['Comments'],
        'Client_fn' => $fb['Client_fn'],
        'Client_ln' => $fb['Client_ln']
    ];
}
echo json_encode($result);