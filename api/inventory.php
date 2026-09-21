<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        /* ---------------- STOCK LIST ---------------- */
        case 'list':
            if (!hasPermission('inventory.view')) jsonFail('Forbidden', 403);

            $q           = trim((string)get('q', ''));
            $warehouseId = (int)get('warehouse_id', 0);
            $categoryId  = (int)get('category_id', 0);
            $filter      = trim((string)get('filter', '')); // low | out | ok
            $page        = max(1, (int)get('page', 1));
            $perPage     = 15;

            $where = ['p.deleted_at IS NULL'];
            $params = [];

            if ($q !== '') {
                $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.product_code LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like, $like);
            }
            if ($categoryId > 0) { $where[] = 'p.category_id = ?'; $params[] = $categoryId; }

            $whFilter = '';
            if ($warehouseId > 0) { $whFilter = ' AND s.warehouse_id = ' . $warehouseId; }

            $having = '';
            if ($filter === 'low')  $having = 'HAVING total_qty <= p.reorder_level AND total_qty > 0';
            if ($filter === 'out')  $having = 'HAVING total_qty <= 0';
            if ($filter === 'ok')   $having = 'HAVING total_qty > p.reorder_level';

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $countSql = "SELECT COUNT(*) FROM (
                            SELECT p.id
                            FROM products p
                            LEFT JOIN stock s ON s.product_id = p.id $whFilter
                            $whereSql
                            GROUP BY p.id
                            $having
                         ) AS t";
            $c = $pdo->prepare($countSql);
            $c->execute($params);
            $total = (int)$c->fetchColumn();

            $pg = paginate($total, $perPage, $page);

            $sql = "SELECT p.id, p.sku, p.name, p.unit, p.cost_price, p.reorder_level,
                           c.name AS category_name,
                           COALESCE(SUM(s.quantity),0) AS total_qty,
                           COALESCE(SUM(s.reserved_qty),0) AS reserved_qty,
                           COALESCE(SUM(s.damaged_qty),0) AS damaged_qty,
                           COALESCE(SUM(s.rejected_qty),0) AS rejected_qty,
                           COUNT(DISTINCT s.warehouse_id) AS warehouse_count
                    FROM products p
                    LEFT JOIN categories c ON c.id = p.category_id
                    LEFT JOIN stock s ON s.product_id = p.id $whFilter
                    $whereSql
                    GROUP BY p.id
                    $having
                    ORDER BY p.name ASC
                    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                $r['total_qty']    = (float)$r['total_qty'];
                $r['available']    = $r['total_qty'] - (float)$r['reserved_qty'] - (float)$r['damaged_qty'];
                $r['stock_value']  = $r['total_qty'] * (float)$r['cost_price'];
                $r['status_badge'] = $r['total_qty'] <= 0
                    ? '<span class="badge badge-soft-danger">Out of Stock</span>'
                    : ($r['total_qty'] <= (float)$r['reorder_level']
                        ? '<span class="badge badge-soft-warning">Low Stock</span>'
                        : '<span class="badge badge-soft-success">In Stock</span>');
            }
            unset($r);

            jsonOk(['items' => $rows, 'pagination' => $pg]);
            break;

        /* ---------------- DETAIL ---------------- */
        case 'detail':
            if (!hasPermission('inventory.view')) jsonFail('Forbidden', 403);

            $productId = (int)get('product_id', 0);
            if ($productId <= 0) jsonFail('Invalid product.');

            $p = $pdo->prepare('SELECT id, sku, name, unit, cost_price, reorder_level FROM products WHERE id = ? AND deleted_at IS NULL');
            $p->execute([$productId]);
            $product = $p->fetch();
            if (!$product) jsonFail('Product not found.', 404);

            $s = $pdo->prepare(
                'SELECT s.*, w.name AS warehouse_name, w.code AS warehouse_code, l.code AS location_code
                 FROM stock s
                 JOIN warehouses w ON w.id = s.warehouse_id
                 LEFT JOIN warehouse_locations l ON l.id = s.location_id
                 WHERE s.product_id = ?
                 ORDER BY w.name ASC'
            );
            $s->execute([$productId]);
            $stock = $s->fetchAll();

            jsonOk(['product' => $product, 'stock' => $stock]);
            break;

        /* ---------------- ADJUST ---------------- */
        case 'adjust':
            if (!hasPermission('inventory.manage')) jsonFail('Forbidden', 403);
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);

            $productId   = (int)post('product_id', 0);
            $variantId   = (int)post('variant_id', 0);
            $warehouseId = (int)post('warehouse_id', 0);
            $type        = post('adjustment_type', 'increase');
            $quantity    = decimal_or(post('quantity', '0'), 0);
            $reason      = clean((string)post('reason', ''), 255);

            $errors = [];
            if ($productId <= 0)   $errors[] = 'Product is required.';
            if ($warehouseId <= 0) $errors[] = 'Warehouse is required.';
            if ($quantity <= 0)    $errors[] = 'Quantity must be greater than zero.';
            if (!in_array($type, ['increase','decrease','damage'], true)) $errors[] = 'Invalid adjustment type.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            $pName = $pdo->prepare('SELECT name, unit, cost_price FROM products WHERE id = ?');
            $pName->execute([$productId]);
            $prod = $pName->fetch();
            if (!$prod) jsonFail('Product not found.', 404);

            try {
                $pdo->beginTransaction();

                $movementType = $type === 'increase' ? 'adjustment_in' : ($type === 'damage' ? 'damage_out' : 'adjustment_out');
                $direction    = $type === 'increase' ? 'in' : 'out';

                applyStockMovement($pdo, [
                    'product_id'     => $productId,
                    'variant_id'     => $variantId,
                    'warehouse_id'   => $warehouseId,
                    'movement_type'  => $movementType,
                    'direction'      => $direction,
                    'quantity'       => $quantity,
                    'unit_cost'      => (float)$prod['cost_price'],
                    'reference_type' => 'manual_adjustment',
                    'reference_no'   => 'ADJ-' . date('YmdHis'),
                    'notes'          => $reason ?: ('Manual ' . $type . ' by ' . (currentUser()['name'] ?? 'user')),
                ]);

                $pdo->commit();

                logActivity('Stock Adjusted', 'inventory', $productId,
                    "$type {$quantity} {$prod['unit']} of {$prod['name']}");

                maybeAlertLowStock($pdo, $productId);

                jsonOk(null, 'Stock adjusted successfully.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 400);
            }
            break;

        /* ---------------- MOVEMENTS ---------------- */
        case 'movements':
            if (!hasPermission('inventory.view')) jsonFail('Forbidden', 403);

            $productId   = (int)get('product_id', 0);
            $warehouseId = (int)get('warehouse_id', 0);
            $type        = trim((string)get('type', ''));
            $dateFrom    = trim((string)get('date_from', ''));
            $dateTo      = trim((string)get('date_to', ''));
            $page        = max(1, (int)get('page', 1));
            $perPage     = 20;

            $where = ['1=1'];
            $params = [];
            if ($productId > 0)   { $where[] = 'm.product_id = ?';   $params[] = $productId; }
            if ($warehouseId > 0) { $where[] = 'm.warehouse_id = ?'; $params[] = $warehouseId; }
            if ($type !== '')     { $where[] = 'm.movement_type = ?';$params[] = $type; }
            if ($dateFrom !== '') { $where[] = 'DATE(m.created_at) >= ?'; $params[] = $dateFrom; }
            if ($dateTo !== '')   { $where[] = 'DATE(m.created_at) <= ?'; $params[] = $dateTo; }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $c = $pdo->prepare("SELECT COUNT(*) FROM stock_movements m $whereSql");
            $c->execute($params);
            $total = (int)$c->fetchColumn();
            $pg = paginate($total, $perPage, $page);

            $sql = "SELECT m.*, p.name AS product_name, p.sku, p.unit,
                           w.name AS warehouse_name
                    FROM stock_movements m
                    JOIN products p ON p.id = m.product_id
                    JOIN warehouses w ON w.id = m.warehouse_id
                    $whereSql
                    ORDER BY m.id DESC
                    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['direction_class'] = $r['direction'] === 'in' ? 'text-success' : 'text-danger';
                $r['direction_sign']  = $r['direction'] === 'in' ? '+' : '−';
            }
            unset($r);

            jsonOk(['items' => $rows, 'pagination' => $pg]);
            break;

        /* ---------------- SUMMARY KPI ---------------- */
        case 'summary':
            if (!hasPermission('inventory.view')) jsonFail('Forbidden', 403);

            $warehouseId = (int)get('warehouse_id', 0);
            $whCond = $warehouseId > 0 ? 'AND s.warehouse_id = ' . $warehouseId : '';

            $totalValue = (float)$pdo->query(
                "SELECT COALESCE(SUM(s.quantity * p.cost_price), 0)
                 FROM stock s JOIN products p ON p.id = s.product_id
                 WHERE p.deleted_at IS NULL $whCond"
            )->fetchColumn();

            $totalQty = (float)$pdo->query(
                "SELECT COALESCE(SUM(s.quantity), 0) FROM stock s
                 JOIN products p ON p.id = s.product_id
                 WHERE p.deleted_at IS NULL $whCond"
            )->fetchColumn();

            $outOfStock = (int)$pdo->query(
                "SELECT COUNT(*) FROM (
                    SELECT p.id FROM products p
                    LEFT JOIN stock s ON s.product_id = p.id $whCond
                    WHERE p.deleted_at IS NULL AND p.status='active'
                    GROUP BY p.id HAVING COALESCE(SUM(s.quantity),0) <= 0
                ) t"
            )->fetchColumn();

            $lowStock = (int)$pdo->query(
                "SELECT COUNT(*) FROM (
                    SELECT p.id FROM products p
                    LEFT JOIN stock s ON s.product_id = p.id $whCond
                    WHERE p.deleted_at IS NULL AND p.status='active'
                    GROUP BY p.id, p.reorder_level
                    HAVING COALESCE(SUM(s.quantity),0) <= p.reorder_level AND COALESCE(SUM(s.quantity),0) > 0
                ) t"
            )->fetchColumn();

            jsonOk([
                'total_value' => $totalValue,
                'total_qty'   => $totalQty,
                'low_stock'   => $lowStock,
                'out_of_stock'=> $outOfStock,
            ]);
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API INVENTORY] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}