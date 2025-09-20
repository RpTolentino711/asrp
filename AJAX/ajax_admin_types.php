<?php
require_once '../database/database.php';
$db = new Database();
$spacetypes = $db->getAllSpaceTypes();
if (!empty($spacetypes)) {
    echo '<div class="table-container"><table class="custom-table"><thead><tr><th>ID</th><th>Name</th></tr></thead><tbody>';
    foreach ($spacetypes as $type) {
        echo '<tr>';
        echo '<td><span class="fw-medium">#' . $type['SpaceType_ID'] . '</span></td>';
        echo '<td><div class="fw-medium">' . htmlspecialchars($type['SpaceTypeName']) . '</div></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    echo '<span style="display:none" data-count="' . count($spacetypes) . '"></span>';
} else {
    echo '<div class="empty-state"><i class="fas fa-tag"></i><h4>No space types found</h4><p>There are no space types in the system</p></div>';
    echo '<span style="display:none" data-count="0"></span>';
}
