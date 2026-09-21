<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        case 'list':
            if (!hasPermission('product.view')) jsonFail('Forbidden', 403);

            $q        = trim((string)get('q', ''));
            $parent   = get('parent', '');
            $status   = trim((string)get('status', ''));

            $where  = ['c.deleted_at IS NULL'];
            $params = [];

            if ($q !== '') {
                $where[] = '(c.name LIKE ? OR c.slug LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like);
            }
            if ($parent === 'root') { $where[] = 'c.parent_id IS NULL'; }
            elseif ($parent !== '' && (int)$parent > 0) { $where[] = 'c.parent_id = ?'; $params[] = (int)$parent; }
            if ($status !== '') { $where[] = 'c.status = ?'; $params[] = $status; }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $sql = "SELECT c.*,
                           pc.name AS parent_name,
                           (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS product_count,
                           (SELECT COUNT(*) FROM categories cc WHERE cc.parent_id = c.id AND cc.deleted_at IS NULL) AS child_count
                    FROM categories c
                    LEFT JOIN categories pc ON pc.id = c.parent_id
                    $whereSql
                    ORDER BY (c.parent_id IS NULL) DESC, c.name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                $r['status_badge'] = statusBadge($r['status']);
            }
            unset($r);

            jsonOk(['items' => $rows]);
            break;

        case 'get':
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$id]);
            $c = $stmt->fetch();
            if (!$c) jsonFail('Category not found.', 404);
            jsonOk($c);
            break;

        case 'save':
            if (isPost() && !verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('product.create') && !hasPermission('product.edit')) jsonFail('Forbidden', 403);

            $id          = (int)post('id', 0);
            $name        = clean((string)post('name', ''), 150);
            $parentId    = (int)post('parent_id', 0);
            $description = clean((string)post('description', ''), 400);
            $status      = post('status', 'active') === 'inactive' ? 'inactive' : 'active';

            $errors = [];
            if ($name === '') $errors[] = 'Name is required.';
            if ($parentId > 0 && $parentId === $id) $errors[] = 'A category cannot be its own parent.';

            $slug = slugify($name);

            // ensure slug uniqueness
            $chk = $pdo->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ? LIMIT 1');
            $chk->execute([$slug, $id]);
            if ($chk->fetchColumn()) {
                $slug .= '-' . randomCode(4);
            }

            if ($errors) jsonFail('Validation failed.', 422, $errors);

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE categories SET name=?, slug=?, parent_id=?, description=?, status=?, updated_at=NOW()
                                       WHERE id=? AND deleted_at IS NULL');
                $stmt->execute([$name, $slug, $parentId ?: null, $description ?: null, $status, $id]);
                logActivity('Category Updated', 'category', $id, "Updated category: $name");
                jsonOk(['id' => $id], 'Category updated successfully.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO categories (name, slug, parent_id, description, status, created_at)
                                       VALUES (?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$name, $slug, $parentId ?: null, $description ?: null, $status]);
                $newId = (int)$pdo->lastInsertId();
                logActivity('Category Created', 'category', $newId, "Created category: $name");
                jsonOk(['id' => $newId], 'Category created successfully.');
            }
            break;

        case 'delete':
            if (isPost() && !verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('product.delete')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            if ($id <= 0) jsonFail('Invalid category ID.');

            // check for children
            $chk = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ? AND deleted_at IS NULL');
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) {
                jsonFail('Cannot delete a category with sub-categories. Delete children first.');
            }
            // check products
            $chk = $pdo->prepare('SELECT COUNT(*) FROM products WHERE (category_id = ? OR sub_category_id = ?) AND deleted_at IS NULL');
            $chk->execute([$id, $id]);
            if ((int)$chk->fetchColumn() > 0) {
                jsonFail('Cannot delete a category with products assigned to it.');
            }

            $name = $pdo->prepare('SELECT name FROM categories WHERE id = ? AND deleted_at IS NULL');
            $name->execute([$id]);
            $cname = $name->fetchColumn();
            if (!$cname) jsonFail('Category not found.', 404);

            $up = $pdo->prepare('UPDATE categories SET deleted_at = NOW(), status = "inactive" WHERE id = ?');
            $up->execute([$id]);

            logActivity('Category Deleted', 'category', $id, "Deleted category: $cname");
            jsonOk(null, 'Category deleted successfully.');
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API CATEGORIES] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}