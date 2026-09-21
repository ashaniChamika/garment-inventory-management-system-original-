<?php
/**
 * Role based access control.
 */

declare(strict_types=1);

if (!defined('GIMS_APP')) { exit('Direct access denied'); }

function loadUserPermissions(int $userId): array
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT DISTINCT p.slug
             FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p       ON p.id = rp.permission_id
             WHERE u.id = ?'
        );
        $stmt->execute([$userId]);
        $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $slugs ?: [];
    } catch (Throwable $e) {
        error_log('[GIMS PERM ERROR] ' . $e->getMessage());
        return [];
    }
}

function userPermissions(): array
{
    return $_SESSION['permissions'] ?? [];
}

function isSuperAdmin(): bool
{
    $u = currentUser();
    return $u && ($u['role_slug'] ?? '') === 'super_admin';
}

function hasPermission(string $slug): bool
{
    if (isSuperAdmin()) return true;
    return in_array($slug, userPermissions(), true);
}

function hasAnyPermission(array $slugs): bool
{
    if (isSuperAdmin()) return true;
    return (bool)array_intersect($slugs, userPermissions());
}

function requirePermission(string $slug): void
{
    requireLogin();
    if (!hasPermission($slug)) {
        if (isAjax()) {
            jsonFail('You do not have permission to perform this action.', 403);
        }
        http_response_code(403);
        include BASE_PATH . '/403.php';
        exit;
    }
}

function requireAnyPermission(array $slugs): void
{
    requireLogin();
    if (!hasAnyPermission($slugs)) {
        if (isAjax()) {
            jsonFail('You do not have permission to perform this action.', 403);
        }
        http_response_code(403);
        include BASE_PATH . '/403.php';
        exit;
    }
}