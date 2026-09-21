<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        case 'list':
            if (!hasPermission('bom.manage') && !hasPermission('production.manage')) jsonFail('Forbidden', 403);

            $q         = trim((string)get('q', ''));
            $productId = (int)get('product_id', 0);
            $status    = trim((string)get('status', ''));

            $where  = ['b.deleted_at IS NULL'];
            $params = [];
            if ($q !== '') { $where[] = '(b.name LIKE ? OR b.version LIKE ? OR p.name LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like,$like); }
            if ($productId > 0) { $where[] = 'b.product_id = ?'; $params[] = $productId; }
            if ($status !== '') { $where[] = 'b.status = ?'; $params[] = $status; }

            $sql = 'SELECT b.*, p.name AS product_name, p.sku,
                           u.name AS created_by_name,
                           (SELECT COUNT(*) FROM bom_items bi WHERE bi.bom_id = b.id) AS item_count
                    FROM bom b
                    JOIN products p ON p.id = b.product_id
                    LEFT JOIN users u ON u.id = b.created_by
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY b.id DESC LIMIT 200';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) $r['status_badge'] = statusBadge($r['status']);
            unset($r);
            jsonOk(['items' => $rows]);
            break;

        case 'get':
            if (!hasPermission('bom.manage') && !hasPermission('production.manage')) jsonFail('Forbidden', 403);
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare('SELECT b.*, p.name AS product_name, p.sku FROM bom b JOIN products p ON p.id = b.product_id WHERE b.id = ?');
            $stmt->execute([$id]);
            $bom = $stmt->fetch();
            if (!$bom) jsonFail('BOM not found.', 404);

            $i = $pdo->prepare(
                'SELECT bi.*, m.name AS material_name, m.sku AS material_sku, m.unit AS material_unit, m.cost_price
                 FROM bom_items bi
                 JOIN products m ON m.id = bi.material_id
                 WHERE bi.bom_id = ?
                 ORDER BY bi.id ASC'
            );
            $i->execute([$id]);
            $bom['items'] = $i->fetchAll();
            jsonOk($bom);
            break;

        case 'save':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('bom.manage')) jsonFail('Forbidden', 403);

            $id        = (int)post('id', 0);
            $productId = (int)post('product_id', 0);
            $name      = clean((string)post('name', ''), 150);
            $version   = clean((string)post('version', '1.0'), 30);
            $notes     = clean((string)post('notes', ''), 500);
            $status    = post('status', 'active') === 'inactive' ? 'inactive' : 'active';
            $items     = post('items', []);

            $errors = [];
            if ($productId <= 0) $errors[] = 'Product is required.';
            if ($name === '')    $errors[] = 'BOM name is required.';
            if (!is_array($items) || count($items) === 0) $errors[] = 'At least one material line is required.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                $pdo->beginTransaction();

                if ($id > 0) {
                    $up = $pdo->prepare('UPDATE bom SET product_id=?, name=?, version=?, notes=?, status=?, updated_at=NOW()
                                         WHERE id=? AND deleted_at IS NULL');
                    $up->execute([$productId, $name, $version, $notes ?: null, $status, $id]);
                    $pdo->prepare('DELETE FROM bom_items WHERE bom_id = ?')->execute([$id]);
                } else {
                    $ins = $pdo->prepare('INSERT INTO bom (product_id, name, version, notes, status, created_by, created_at)
                                          VALUES (?, ?, ?, ?, ?, ?, NOW())');
                    $ins->execute([$productId, $name, $version, $notes ?: null, $status, currentUserId()]);
                    $id = (int)$pdo->lastInsertId();
                }

                $insItem = $pdo->prepare('INSERT INTO bom_items (bom_id, material_id, quantity, unit, wastage_pct, notes)
                                          VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($items as $row) {
                    $mid = (int)($row['material_id'] ?? 0);
                    $qty = decimal_or($row['quantity'] ?? 0, 0);
                    if ($mid <= 0 || $qty <= 0) continue;
                    $insItem->execute([$id, $mid, $qty, $row['unit'] ?? 'pcs',
                                       decimal_or($row['wastage_pct'] ?? 0, 0), $row['notes'] ?? null]);
                }

                $pdo->commit();
                logActivity($id ? 'BOM Updated' : 'BOM Created', 'production', $id, "BOM: $name");
                jsonOk(['id' => $id], 'BOM saved successfully.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail('Could not save BOM: ' . $ex->getMessage(), 500);
            }
            break;

        case 'delete':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('bom.manage')) jsonFail('Forbidden', 403);
            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT COUNT(*) FROM production_orders WHERE bom_id = ? AND status IN ("planned","in_progress","paused") AND deleted_at IS NULL');
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) jsonFail('Cannot delete BOM referenced by active production orders.');
            $pdo->prepare('UPDATE bom SET deleted_at = NOW(), status = "inactive" WHERE id = ?')->execute([$id]);
            logActivity('BOM Deleted', 'production', $id, 'BOM deleted');
            jsonOk(null, 'BOM deleted.');
            break;

        case 'calculate':
            // Calculate required materials for a BOM × quantity
            $bomId = (int)get('bom_id', 0);
            $qty   = decimal_or(get('quantity', 1), 1);
            if ($bomId <= 0 || $qty <= 0) jsonFail('Invalid BOM or quantity.');

            $stmt = $pdo->prepare(
                'SELECT bi.*, m.name AS material_name, m.sku, m.cost_price,
                        COALESCE((SELECT SUM(quantity) FROM stock WHERE product_id = bi.material_id), 0) AS stock_qty
                 FROM bom_items bi
                 JOIN products m ON m.id = bi.material_id
                 WHERE bi.bom_id = ?'
            );
            $stmt->execute([$bomId]);
            $items = $stmt->fetchAll();

            $result = [];
            $totalCost = 0;
            foreach ($items as $it) {
                $required = (float)$it['quantity'] * $qty;
                $withWaste = $required * (1 + (float)$it['wastage_pct'] / 100);
                $cost = $withWaste * (float)$it['cost_price'];
                $totalCost += $cost;
                $result[] = [
                    'material_id' => (int)$it['material_id'],
                    'material_name' => $it['material_name'],
                    'sku' => $it['sku'],
                    'base_qty' => (float)$it['quantity'],
                    'required_qty' => round($withWaste, 4),
                    'unit' => $it['unit'],
                    'stock_qty' => (float)$it['stock_qty'],
                    'shortage' => max(0, $withWaste - (float)$it['stock_qty']),
                    'cost' => round($cost, 2),
                ];
            }

            jsonOk(['items' => $result, 'total_cost' => round($totalCost, 2), 'quantity' => $qty]);
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API BOM] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}