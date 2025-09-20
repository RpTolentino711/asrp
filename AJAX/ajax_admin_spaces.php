<?php
require_once '../database/database.php';
$db = new Database();
$spaces = $db->getAllSpacesWithDetails();
if (!empty($spaces)) {
    echo '<div class="table-container"><table class="custom-table"><thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Price (PHP)</th><th>Photos</th></tr></thead><tbody>';
    foreach ($spaces as $space) {
        echo '<tr>';
        echo '<td><span class="fw-medium">#' . $space['Space_ID'] . '</span></td>';
        echo '<td><div class="fw-medium">' . htmlspecialchars($space['Name']) . '</div></td>';
        echo '<td>' . htmlspecialchars($space['SpaceTypeName']) . '</td>';
        echo '<td>₱' . number_format($space['Price'], 2) . '</td>';
        echo '<td>Photos</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    echo '<span style="display:none" data-count="' . count($spaces) . '"></span>';
} else {
    echo '<div class="empty-state"><i class="fas fa-home"></i><h4>No spaces/units found</h4><p>There are no spaces or units in the system</p></div>';
    echo '<span style="display:none" data-count="0"></span>';
}
