<?php
session_start();
require '../database/database.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$db = new Database();

// Mark messages as read if requested
if (isset($_GET['mark_seen']) && $_GET['mark_seen'] == 'true') {
    $db->markClientMessagesAsRead();
}

// Get latest client messages
$messages = $db->getLatestClientMessages(5);
$unread_count = $db->getUnreadClientMessagesCount();

// Return HTML response
?>
<div class="message-board" data-count="<?= count($messages) ?>" data-unread-count="<?= $unread_count ?>">
    <?php if (empty($messages)): ?>
        <div class="text-center p-4 text-muted">
            <i class="fas fa-comments fa-2x mb-2"></i>
            <p>No client messages</p>
        </div>
    <?php else: ?>
        <?php foreach ($messages as $message): ?>
            <div class="message-item <?= $message['is_read_admin'] == 0 ? 'new-request-flash' : '' ?>" 
                 data-message-id="<?= $message['Chat_ID'] ?>">
                <div class="message-user">
                    <?= htmlspecialchars($message['Client_fn'] . ' ' . $message['Client_ln']) ?>
                    <?php if ($message['is_read_admin'] == 0): ?>
                        <span class="badge bg-primary ms-2 new-request-indicator">New</span>
                    <?php endif; ?>
                </div>
                <div class="message-meta">
                    <small>
                        <i class="fas fa-clock me-1"></i><?= timeAgo($message['Created_At']) ?>
                        <?php if ($message['UnitName']): ?>
                            <span class="ms-2">
                                <i class="fas fa-home me-1"></i><?= htmlspecialchars($message['UnitName']) ?>
                            </span>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="message-content">
                    <?= htmlspecialchars($message['Message']) ?>
                    <?php if ($message['Image_Path']): ?>
                        <div class="mt-2">
                            <i class="fas fa-image text-muted me-1"></i>
                            <small class="text-muted">Image attached</small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="message-actions">
                    <a href="generate_invoice.php?chat_invoice_id=<?= $message['Invoice_ID'] ?>" 
                       class="btn btn-sm btn-primary">
                        <i class="fas fa-reply me-1"></i>Reply
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
function timeAgo($datetime) {
    $sent = new DateTime($datetime);
    $now = new DateTime();
    $diff = $now->diff($sent);

    if ($diff->days > 0) return $diff->days . ' days ago';
    if ($diff->h > 0) return $diff->h . ' hours ago';
    if ($diff->i > 0) return $diff->i . ' minutes ago';
    return 'Just now';
}
?>