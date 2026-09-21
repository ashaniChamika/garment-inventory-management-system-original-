<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        /* ---------------------- LIST ---------------------- */
        case 'list':
            if (!hasPermission('product.view')) jsonFail('Forbidden', 403);

            $q          = trim((string)get('q', ''));
            $categoryId = (int)get('category_id', 0);
            $type       = trim((string)get('type', ''));
            $status     = trim((string)get('status', ''));
            $stockF     = trim((string)get('stock', ''));  // low | out | normal
            $page       = max(1, (int)get('page', 1));
            $perPage    = 15;

            $where  = ['p.deleted_at IS NULL'];
            $params = [];

            if ($q !== '') {
                $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.product_code LIKE ? OR p.brand LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like, $like, $like);
            }
            if ($categoryId > 0) { $where[] = 'p.category_id = ?'; $params[] = $categoryId; }
            if ($type !== '')    { $where[] = 'p.product_type = ?'; $params[] = $type; }
            if ($status !== '')  { $where[] = 'p.status = ?'; $params[] = $status; }

            $having = '';
            if ($stockF === 'low') {
                $having = 'HAVING stock_qty <= p.reorder_level AND stock_qty > 0';
            } elseif ($stockF === 'out') {
                $having = 'HAVING stock_qty <= 0';
            }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            // Count
            $countSql = "SELECT COUNT(*) FROM (
                            SELECT p.id
                            FROM products p
                            LEFT JOIN stock s ON s.product_id = p.id
                            $whereSql
                            GROUP BY p.id
                            $having
                         ) AS t";
            $cStmt = $pdo->prepare($countSql);
            $cStmt->execute($params);
            $total = (int)$cStmt->fetchColumn();

            $pg = paginate($total, $perPage, $page);

            $sql = "SELECT p.id, p.sku, p.product_code, p.name, p.product_type, p.brand,
                           p.unit, p.cost_price, p.selling_price, p.reorder_level, p.status, p.image,
                           p.has_variants,
                           c.name AS category_name,
                           s2.company_name AS supplier_name,
                           COALESCE(SUM(s.quantity), 0) AS stock_qty
                    FROM products p
                    LEFT JOIN categories c  ON c.id = p.category_id
                    LEFT JOIN suppliers s2  ON s2.id = p.supplier_id
                    LEFT JOIN stock s       ON s.product_id = p.id
                    $whereSql
                    GROUP BY p.id
                    $having
                    ORDER BY p.id DESC
                    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                $r['stock_qty']   = (float)$r['stock_qty'];
                $r['cost_price']  = (float)$r['cost_price'];
                $r['selling_price'] = (float)$r['selling_price'];
                $r['reorder_level'] = (float)$r['reorder_level'];
                $r['status_label']  = statusBadge($r['status']);
                $r['stock_badge'] = $r['stock_qty'] <= 0
                    ? '<span class="badge badge-soft-danger">Out of Stock</span>'
                    : ($r['stock_qty'] <= $r['reorder_level']
                        ? '<span class="badge badge-soft-warning">Low Stock</span>'
                        : '<span class="badge badge-soft-success">In Stock</span>');
            }
            unset($r);

            jsonOk(['items' => $rows, 'pagination' => $pg]);
            break;

        /* ---------------------- GET ONE ---------------------- */
        case 'get':
            if (!hasPermission('product.view')) jsonFail('Forbidden', 403);
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) jsonFail('Product not found.', 404);
            jsonOk($p);
            break;

        /* ---------------------- DELETE ---------------------- */
        case 'delete':
            if (isPost() && !verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('product.delete')) jsonFail('Forbidden', 403);

            $id = (int)(post('id', get('id', 0)));
            if ($id <= 0) jsonFail('Invalid product ID.');

            $stmt = $pdo->prepare('SELECT name FROM products WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$id]);
            $name = $stmt->fetchColumn();
            if (!$name) jsonFail('Product not found.', 404);

            $up = $pdo->prepare('UPDATE products SET deleted_at = NOW(), status = "inactive" WHERE id = ?');
            $up->execute([$id]);

            logActivity('Product Deleted', 'product', $id, "Deleted product: $name");
            jsonOk(null, 'Product deleted successfully.');
            break;

        /* ---------------------- TOGGLE STATUS ---------------------- */
        case 'toggle_status':
            if (isPost() && !verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('product.edit')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            if ($id <= 0) jsonFail('Invalid product ID.');

            $stmt = $pdo->prepare('SELECT status, name FROM products WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonFail('Product not found.', 404);

            $new = $row['status'] === 'active' ? 'inactive' : 'active';
            $up  = $pdo->prepare('UPDATE products SET status = ? WHERE id = ?');
            $up->execute([$new, $id]);

            logActivity('Product Status Changed', 'product', $id, "{$row['name']} → $new");
            jsonOk(['status' => $new], 'Status updated.');
            break;

        /* ---------------------- LOOKUP (for selects) ---------------------- */
        case 'lookup':
            $q  = trim((string)get('q', ''));
            $ty = trim((string)get('type', ''));
            $where = ['deleted_at IS NULL', 'status = "active"'];
            $params = [];
            if ($q !== '') {
                $where[] = '(name LIKE ? OR sku LIKE ? OR product_code LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like, $like);
            }
            if ($ty !== '') { $where[] = 'product_type = ?'; $params[] = $ty; }
            $sql = 'SELECT id, sku, product_code, name, unit, cost_price, selling_price
                    FROM products WHERE ' . implode(' AND ', $where) . ' ORDER BY name ASC LIMIT 30';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            jsonOk(['items' => $stmt->fetchAll()]);
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API PRODUCTS] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}