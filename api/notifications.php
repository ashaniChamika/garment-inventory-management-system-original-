<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo  = db();
$user = currentUser();
$action = get('action', 'list');

try {
    if ($action === 'count') {
        jsonOk(['count' => unreadNotificationCount($user['id'])]);
    }

    if ($action === 'list') {
        $stmt = $pdo->prepare(
            'SELECT id, title, message, type, link, is_read, created_at
             FROM notifications
             WHERE (user_id = ? OR user_id IS NULL)
             ORDER BY created_at DESC LIMIT 20'
        );
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['time_ago'] = timeAgo($r['created_at']);
        }
        unset($r);

        jsonOk(['items' => $rows, 'unread' => unreadNotificationCount($user['id'])]);
    }

    if ($action === 'read' && isPost()) {
        requireCsrf();
        $id = (int)post('id');
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)');
            $stmt->execute([$id, $user['id']]);
        }
        jsonOk(null, 'Marked as read');
    }

    if ($action === 'read_all' && isPost()) {
        requireCsrf();
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND (user_id = ? OR user_id IS NULL)');
        $stmt->execute([$user['id']]);
        jsonOk(null, 'All marked as read');
    }

    jsonFail('Unknown action.', 400);
} catch (Throwable $e) {
    error_log('[GIMS NOTIF API] ' . $e->getMessage());
    jsonFail('Notification service error.', 500);
}