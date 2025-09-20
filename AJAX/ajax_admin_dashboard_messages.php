<?php
require_once '../database/database.php';
session_start();
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit('Forbidden');
}
$db = new Database();
$filter = $_GET['filter'] ?? 'recent';
if ($filter === 'all') {
    $free_messages = $db->getAllFreeMessages();
} else {
    $free_messages = $db->getRecentFreeMessages(5);
}
function timeAgo($datetime) {
    $sent = new DateTime($datetime);
    $now = new DateTime();
    $diff = $now->diff($sent);
    if ($diff->days > 0) return $diff->days . ' days ago';
    if ($diff->h > 0) return $diff->h . ' hours ago';
    if ($diff->i > 0) return $diff->i . ' minutes ago';
    return 'Just now';
}
if (empty($free_messages)) {
    echo '<div class="text-center p-4 text-muted"><i class="fas fa-envelope-open fa-2x mb-2"></i><p>No messages yet</p></div>';
} else {
    foreach ($free_messages as $msg) {
        echo '<div class="message-item ' . ($msg['is_deleted'] ? 'deleted' : '') . '">';
        echo '<div class="message-user">' . htmlspecialchars($msg['Client_Name']) . '</div>';
        echo '<div class="message-meta">' . htmlspecialchars(date('M d, Y H:i', strtotime($msg['Sent_At']))) . ' • ' . timeAgo($msg['Sent_At']);
        if ($filter === 'all' && $msg['is_deleted']) {
            echo ' <span class="badge bg-danger ms-2">Deleted</span>';
        }
        echo '</div>';
        echo '<div class="message-content">';
        echo '<div class="mb-1"><strong>Email:</strong> ' . htmlspecialchars($msg['Client_Email']) . '</div>';
        echo '<div class="mb-2"><strong>Phone:</strong> ' . htmlspecialchars($msg['Client_Phone'] ?? 'N/A') . '</div>';
        echo '<div>' . nl2br(htmlspecialchars($msg['Message_Text'])) . '</div>';
        echo '</div>';
        if (empty($msg['is_deleted']) || $msg['is_deleted'] == 0) {
            echo '<form method="post" class="mt-2">';
            echo '<input type="hidden" name="soft_delete_msg_id" value="' . $msg['Message_ID'] . '">';
            echo '<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this message?\')">';
            echo '<i class="fas fa-trash-alt me-1"></i> Delete';
            echo '</button></form>';
        }
        echo '</div>';
    }
}
