<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('users.manage');

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        case 'list':
            $q = trim((string)get('q', ''));
            $roleId = (int)get('role_id', 0);
            $status = trim((string)get('status', ''));

            $where = ['u.deleted_at IS NULL'];
            $params = [];
            if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like); }
            if ($roleId > 0) { $where[] = 'u.role_id = ?'; $params[] = $roleId; }
            if ($status !== '') { $where[] = 'u.status = ?'; $params[] = $status; }

            $sql = 'SELECT u.id, u.name, u.email, u.phone, u.status, u.role_id, u.last_login, u.created_at,
                           r.name AS role_name, r.slug AS role_slug
                    FROM users u
                    JOIN roles r ON r.id = u.role_id
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY u.name ASC LIMIT 200';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) $r['status_badge'] = statusBadge($r['status']);
            unset($r);
            jsonOk(['items' => $rows]);
            break;

        case 'get':
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare('SELECT id, name, email, phone, role_id, status FROM users WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$id]);
            $u = $stmt->fetch();
            if (!$u) jsonFail('User not found.', 404);
            jsonOk($u);
            break;

        case 'save':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);

            $id     = (int)post('id', 0);
            $name   = clean((string)post('name', ''), 150);
            $email  = strtolower(clean((string)post('email', ''), 190));
            $phone  = clean((string)post('phone', ''), 30);
            $roleId = (int)post('role_id', 0);
            $status = in_array(post('status'), ['active','inactive','suspended'], true) ? post('status') : 'active';
            $pwd    = (string)post('password', '');
            $pwdC   = (string)post('password_confirm', '');

            $errors = [];
            if ($name === '') $errors[] = 'Name is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
            if ($roleId <= 0) $errors[] = 'Role is required.';
            if ($id === 0 && strlen($pwd) < 8) $errors[] = 'Password must be at least 8 characters.';
            if ($pwd !== '' && $pwd !== $pwdC) $errors[] = 'Passwords do not match.';

            if ($errors) jsonFail('Validation failed.', 422, $errors);

            // email uniqueness
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $chk->execute([$email, $id]);
            if ($chk->fetchColumn()) jsonFail('Email already in use.');

            // prevent self-lockout
            if ($id === currentUserId() && $status !== 'active') {
                jsonFail('You cannot deactivate your own account.');
            }
            if ($id === currentUserId() && $roleId !== currentUser()['role_id']) {
                // fine, allow but careful
            }

            try {
                if ($id > 0) {
                    $sql = 'UPDATE users SET name=?, email=?, phone=?, role_id=?, status=?, updated_at=NOW()';
                    $args = [$name, $email, $phone ?: null, $roleId, $status];
                    if ($pwd !== '') {
                        $sql .= ', password=?'; $args[] = password_hash($pwd, PASSWORD_DEFAULT);
                    }
                    $sql .= ' WHERE id=?';
                    $args[] = $id;
                    $pdo->prepare($sql)->execute($args);

                    // sync user_roles
                    $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$id]);
                    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$id, $roleId]);

                    logActivity('User Updated', 'users', $id, "Updated: $name");
                    jsonOk(['id' => $id], 'User updated.');
                } else {
                    $ins = $pdo->prepare('INSERT INTO users (name, email, password, phone, role_id, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())');
                    $ins->execute([$name, $email, password_hash($pwd, PASSWORD_DEFAULT), $phone ?: null, $roleId, $status]);
                    $newId = (int)$pdo->lastInsertId();

                    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$newId, $roleId]);

                    logActivity('User Created', 'users', $newId, "Created: $name");
                    jsonOk(['id' => $newId], 'User created.');
                }
            } catch (Throwable $ex) {
                error_log('[GIMS USER SAVE] ' . $ex->getMessage());
                jsonFail('Could not save user.', 500);
            }
            break;

        case 'delete':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            $id = (int)post('id', 0);
            if ($id === currentUserId()) jsonFail('You cannot delete your own account.');

            $n = $pdo->prepare('SELECT name FROM users WHERE id = ? AND deleted_at IS NULL');
            $n->execute([$id]);
            $name = $n->fetchColumn();
            if (!$name) jsonFail('User not found.', 404);

            $pdo->prepare('UPDATE users SET deleted_at = NOW(), status = "inactive" WHERE id = ?')->execute([$id]);
            logActivity('User Deleted', 'users', $id, "Deleted: $name");
            jsonOk(null, 'User deleted.');
            break;

        case 'roles_list':
            $rows = $pdo->query(
                'SELECT r.*,
                        (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS user_count,
                        (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perm_count
                 FROM roles r ORDER BY r.id ASC'
            )->fetchAll();
            jsonOk(['items' => $rows]);
            break;

        case 'permissions_list':
            $rows = $pdo->query('SELECT * FROM permissions ORDER BY module ASC, slug ASC')->fetchAll();
            // group by module
            $grouped = [];
            foreach ($rows as $r) $grouped[$r['module']][] = $r;
            jsonOk(['items' => $rows, 'grouped' => $grouped]);
            break;

        case 'role_permissions':
            $roleId = (int)get('role_id', 0);
            $stmt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
            $stmt->execute([$roleId]);
            jsonOk(['permission_ids' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
            break;

        case 'save_role_permissions':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            $roleId = (int)post('role_id', 0);
            $perms  = post('permissions', []);

            if ($roleId <= 0) jsonFail('Invalid role.');

            // super_admin permissions are locked
            $slug = $pdo->query("SELECT slug FROM roles WHERE id = " . (int)$roleId)->fetchColumn();
            if ($slug === 'super_admin') jsonFail('Super Admin permissions cannot be modified.');

            try {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
                $ins = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
                foreach ((array)$perms as $pid) {
                    $ins->execute([$roleId, (int)$pid]);
                }
                $pdo->commit();

                logActivity('Role Permissions Updated', 'users', $roleId, 'Updated permission set');
                jsonOk(null, 'Permissions updated. Users may need to re-login.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 500);
            }
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API USERS] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}