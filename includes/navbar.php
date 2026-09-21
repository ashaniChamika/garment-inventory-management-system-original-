<?php
declare(strict_types=1);
if (!defined('GIMS_APP')) { exit('Direct access denied'); }

$__user = currentUser();
$__notifCount = unreadNotificationCount($__user['id'] ?? null);

try {
    $stmt = db()->prepare(
        'SELECT id, title, message, type, link, is_read, created_at
         FROM notifications
         WHERE (user_id = ? OR user_id IS NULL)
         ORDER BY created_at DESC
         LIMIT 6'
    );
    $stmt->execute([$__user['id'] ?? null]);
    $__notifications = $stmt->fetchAll();
} catch (Throwable $e) {
    $__notifications = [];
}
?>
<header class="gims-navbar">
    <button class="gims-nav-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>

    <div class="gims-nav-search d-none d-md-block">
        <i class="bi bi-search"></i>
        <input type="text" id="globalSearch" placeholder="Search products, orders, customers…" autocomplete="off">
    </div>

    <div class="gims-nav-actions">

        <button class="gims-nav-icon" id="themeToggle" title="Toggle theme">
            <i class="bi bi-moon-stars"></i>
        </button>

        <div class="dropdown">
            <button class="gims-nav-icon position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="bi bi-bell"></i>
                <?php if ($__notifCount > 0): ?>
                    <span class="gims-notif-dot" id="notifBadge"><?= $__notifCount > 99 ? '99+' : (int)$__notifCount ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end gims-notif-menu shadow-lg">
                <div class="gims-notif-head">
                    <span>Notifications</span>
                    <a href="<?= BASE_URL ?>/notifications/index.php" class="small">View all</a>
                </div>
                <div class="gims-notif-body" id="notifList">
                    <?php if (empty($__notifications)): ?>
                        <div class="text-center text-muted py-4 small">
                            <i class="bi bi-inbox fs-4 d-block mb-2"></i>No notifications
                        </div>
                    <?php else: foreach ($__notifications as $n): ?>
                        <a href="<?= e($n['link'] ?: '#') ?>" class="gims-notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
                            <span class="gims-notif-icon text-<?= e($n['type']) ?>">
                                <i class="bi bi-<?= $n['type'] === 'danger' ? 'exclamation-octagon' : ($n['type'] === 'warning' ? 'exclamation-triangle' : ($n['type'] === 'success' ? 'check-circle' : 'info-circle')) ?>"></i>
                            </span>
                            <span class="gims-notif-text">
                                <strong><?= e($n['title']) ?></strong>
                                <small><?= e(mb_strimwidth((string)$n['message'], 0, 60, '…')) ?></small>
                                <em><?= e(timeAgo($n['created_at'])) ?></em>
                            </span>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <div class="dropdown">
            <button class="gims-user-btn" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="gims-avatar">
                    <?= e(strtoupper(substr($__user['name'] ?? 'U', 0, 1))) ?>
                </span>
                <span class="gims-user-meta d-none d-sm-flex">
                    <strong><?= e($__user['name'] ?? 'User') ?></strong>
                    <small><?= e($__user['role_name'] ?? '') ?></small>
                </span>
                <i class="bi bi-chevron-down small d-none d-sm-inline"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li class="dropdown-header small text-uppercase text-muted">Signed in as</li>
                <li class="px-3 pb-2"><strong><?= e($__user['email'] ?? '') ?></strong></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/users/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <?php if (hasPermission('settings.manage')): ?>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/settings/index.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</header>

<div class="gims-page-head">
    <div>
        <h1 class="gims-page-title"><?= e($__pageTitle) ?></h1>
        <?php if (!empty($pageSubtitle)): ?>
            <p class="gims-page-sub mb-0"><?= e($pageSubtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($pageActions)): ?>
        <div class="gims-page-actions"><?= $pageActions ?></div>
    <?php endif; ?>
</div>