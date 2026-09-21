<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo = db();
$user = currentUser();

$filter = trim((string)get('filter', ''));
$where = 'WHERE (user_id = ? OR user_id IS NULL)';
$params = [$user['id']];
if ($filter === 'unread') $where .= ' AND is_read = 0';
if ($filter === 'read')   $where .= ' AND is_read = 1';

$stmt = $pdo->prepare("SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$unread = unreadNotificationCount($user['id']);

$pageTitle    = 'Notifications';
$pageSubtitle = 'Your alerts and updates';
$breadcrumbs  = ['Notifications' => null];
$pageScripts  = [ASSETS_URL . '/js/notifications.js'];

$pageActions = '';
if ($unread > 0) {
    $pageActions = '<button class="btn btn-primary" id="markAllRead"><i class="bi bi-check2-all me-1"></i> Mark all as read</button>';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="gims-range-tabs">
        <a href="?filter=" class="gims-range-tab <?= $filter === '' ? 'active' : '' ?>">All</a>
        <a href="?filter=unread" class="gims-range-tab <?= $filter === 'unread' ? 'active' : '' ?>">
            Unread <?= $unread > 0 ? '<span class="badge badge-soft-danger ms-1">' . $unread . '</span>' : '' ?>
        </a>
        <a href="?filter=read" class="gims-range-tab <?= $filter === 'read' ? 'active' : '' ?>">Read</a>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="gims-empty py-5">
                <i class="bi bi-bell-slash"></i>
                <p>No notifications to display.</p>
            </div>
        <?php else: ?>
            <div class="gims-notif-list">
                <?php foreach ($rows as $n):
                    $iconMap = [
                        'info' => 'info-circle', 'success' => 'check-circle',
                        'warning' => 'exclamation-triangle', 'danger' => 'exclamation-octagon'
                    ];
                    $icon = $iconMap[$n['type']] ?? 'info-circle';
                ?>
                    <div class="gims-notif-item-lg <?= $n['is_read'] ? '' : 'unread' ?>" data-id="<?= (int)$n['id'] ?>">
                        <span class="gims-notif-icon-lg text-<?= e($n['type']) ?>">
                            <i class="bi bi-<?= $icon ?>"></i>
                        </span>
                        <div class="gims-notif-content">
                            <div class="d-flex justify-content-between align-items-start">
                                <strong><?= e($n['title']) ?></strong>
                                <small class="text-muted"><?= e(timeAgo($n['created_at'])) ?></small>
                            </div>
                            <p class="mb-2 small text-muted"><?= e($n['message']) ?></p>
                            <div class="d-flex gap-2">
                                <?php if ($n['link']): ?>
                                    <a href="<?= e($n['link']) ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>View
                                    </a>
                                <?php endif; ?>
                                <?php if (!$n['is_read']): ?>
                                    <button class="btn btn-sm btn-outline-secondary mark-read" data-id="<?= (int)$n['id'] ?>">
                                        <i class="bi bi-check2 me-1"></i>Mark read
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.gims-notif-list { display: flex; flex-direction: column; }
.gims-notif-item-lg {
    display: flex; gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    transition: background .15s ease;
}
.gims-notif-item-lg:hover { background: #f8fafc; }
.gims-notif-item-lg.unread { background: #eff6ff; }
.gims-notif-item-lg:last-child { border-bottom: none; }
.gims-notif-icon-lg {
    width: 40px; height: 40px; flex-shrink: 0;
    border-radius: 10px;
    background: #f1f5f9;
    display: grid; place-items: center;
    font-size: 18px;
}
.gims-notif-content { flex: 1; min-width: 0; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>