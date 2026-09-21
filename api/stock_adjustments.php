<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('inventory.manage');
require_once __DIR__ . '/../includes/stock.php';

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        case 'list':
            $status = trim((string)get('status', ''));
            $where  = ['1=1'];
            $params = [];
            if ($status !== '') { $where[] = 'sa.status = ?'; $params[] = $status; }

            $sql = 'SELECT sa.*, w.name AS warehouse_name,
                           u1.name AS created_by_name, u2.name AS approved_by_name,
                           (SELECT COUNT(*) FROM stock_adjustment_items i WHERE i.adjustment_id = sa.id) AS item_count
                    FROM stock_adjustments sa
                    JOIN warehouses w ON w.id = sa.warehouse_id
                    LEFT JOIN users u1 ON u1.id = sa.created_by
                    LEFT JOIN users u2 ON u2.id = sa.approved_by
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY sa.id DESC LIMIT 200';
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
            $stmt = $pdo->prepare(
                'SELECT sa.*, w.name AS warehouse_name FROM stock_adjustments sa
                 JOIN warehouses w ON w.id = sa.warehouse_id
                 WHERE sa.id = ?'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonFail('Adjustment not found.', 404);

            $items = $pdo->prepare(
                'SELECT i.*, p.name AS product_name, p.sku, p.unit
                 FROM stock_adjustment_items i
                 JOIN products p ON p.id = i.product_id
                 WHERE i.adjustment_id = ?'
            );
            $items->execute([$id]);
            $row['items'] = $items->fetchAll();

            jsonOk($row);
            break;

        case 'save':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);

            $id           = (int)post('id', 0);
            $warehouseId  = (int)post('warehouse_id', 0);
            $adjDate      = post('adjustment_date', date('Y-m-d'));
            $type         = post('adjustment_type', 'recount');
            $reason       = clean((string)post('reason', ''), 255);
            $notes        = clean((string)post('notes', ''), 400);
            $items        = post('items', []);

            $errors = [];
            if ($warehouseId <= 0) $errors[] = 'Warehouse is required.';
            if (!in_array($type, ['increase','decrease','damage','recount'], true)) $errors[] = 'Invalid type.';
            if (!is_array($items) || count($items) === 0) $errors[] = 'At least one item is required.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                $pdo->beginTransaction();

                if ($id > 0) {
                    $up = $pdo->prepare(
                        'UPDATE stock_adjustments SET warehouse_id=?, adjustment_date=?, adjustment_type=?,
                             reason=?, notes=?, updated_at=NOW()
                         WHERE id=? AND status IN ("draft","pending")'
                    );
                    $up->execute([$warehouseId, $adjDate, $type, $reason ?: null, $notes ?: null, $id]);
                    $refNo = $pdo->query("SELECT reference_no FROM stock_adjustments WHERE id = " . (int)$id)->fetchColumn();
                    $pdo->prepare('DELETE FROM stock_adjustment_items WHERE adjustment_id = ?')->execute([$id]);
                } else {
                    $refNo = generateRef('ADJ', 'stock_adjustments', 'reference_no');
                    $ins = $pdo->prepare(
                        'INSERT INTO stock_adjustments (reference_no, warehouse_id, adjustment_date, adjustment_type,
                             reason, notes, status, created_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, "pending", ?, NOW())'
                    );
                    $ins->execute([$refNo, $warehouseId, $adjDate, $type, $reason ?: null, $notes ?: null, currentUserId()]);
                    $id = (int)$pdo->lastInsertId();
                }

                $insItem = $pdo->prepare(
                    'INSERT INTO stock_adjustment_items
                        (adjustment_id, product_id, variant_id, system_qty, counted_qty, difference_qty, unit_cost, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

                foreach ($items as $row) {
                    $productId = (int)($row['product_id'] ?? 0);
                    $variantId = (int)($row['variant_id'] ?? 0);
                    $counted   = decimal_or($row['counted_qty'] ?? 0, 0);
                    if ($productId <= 0) continue;

                    $system = getStockQty($pdo, $productId, $variantId, $warehouseId);
                    $diff   = $counted - $system;

                    $pstmt = $pdo->prepare('SELECT cost_price FROM products WHERE id = ?');
                    $pstmt->execute([$productId]);
                    $cost = (float)$pstmt->fetchColumn();

                    $insItem->execute([$id, $productId, $variantId, $system, $counted, $diff, $cost, $row['notes'] ?? null]);
                }

                $pdo->commit();
                logActivity('Stock Adjustment Saved', 'inventory', $id, "Saved $refNo");
                jsonOk(['id' => $id, 'reference_no' => $refNo], 'Adjustment saved successfully.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail('Could not save adjustment: ' . $ex->getMessage(), 500);
            }
            break;

        case 'approve':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            $id = (int)post('id', 0);
            if ($id <= 0) jsonFail('Invalid adjustment.');

            try {
                $pdo->beginTransaction();

                $adj = $pdo->prepare('SELECT * FROM stock_adjustments WHERE id = ? AND status IN ("pending","draft") FOR UPDATE');
                $adj->execute([$id]);
                $row = $adj->fetch();
                if (!$row) throw new RuntimeException('Adjustment not found or already processed.');

                $items = $pdo->prepare('SELECT * FROM stock_adjustment_items WHERE adjustment_id = ?');
                $items->execute([$id]);
                $allItems = $items->fetchAll();

                foreach ($allItems as $it) {
                    $diff = (float)$it['difference_qty'];
                    if ($diff == 0.0) continue;

                    $direction    = $diff > 0 ? 'in' : 'out';
                    $absQty       = abs($diff);
                    $movementType = $row['adjustment_type'] === 'damage'
                        ? 'damage_out'
                        : ($direction === 'in' ? 'adjustment_in' : 'adjustment_out');

                    applyStockMovement($pdo, [
                        'product_id'     => (int)$it['product_id'],
                        'variant_id'     => (int)$it['variant_id'],
                        'warehouse_id'   => (int)$row['warehouse_id'],
                        'movement_type'  => $movementType,
                        'direction'      => $direction,
                        'quantity'       => $absQty,
                        'unit_cost'      => (float)$it['unit_cost'],
                        'reference_type' => 'stock_adjustment',
                        'reference_id'   => $id,
                        'reference_no'   => $row['reference_no'],
                        'notes'          => $row['reason'] ?: 'Stock adjustment approved',
                    ]);
                }

                $pdo->prepare('UPDATE stock_adjustments SET status = "approved", approved_by = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([currentUserId(), $id]);

                $pdo->commit();

                logActivity('Stock Adjustment Approved', 'inventory', $id, 'Approved ' . $row['reference_no']);
                pushNotification('Stock Adjustment Approved',
                    "Adjustment {$row['reference_no']} has been approved and stock updated.",
                    'success', 'inventory', BASE_URL . '/inventory/adjustments.php');

                jsonOk(null, 'Adjustment approved. Stock has been updated.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 400);
            }
            break;

        case 'reject':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            $id = (int)post('id', 0);
            $pdo->prepare('UPDATE stock_adjustments SET status = "rejected", approved_by = ?, updated_at = NOW() WHERE id = ? AND status = "pending"')
                ->execute([currentUserId(), $id]);
            logActivity('Stock Adjustment Rejected', 'inventory', $id, 'Rejected adjustment');
            jsonOk(null, 'Adjustment rejected.');
            break;

        case 'delete':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT reference_no, status FROM stock_adjustments WHERE id = ?');
            $chk->execute([$id]);
            $row = $chk->fetch();
            if (!$row) jsonFail('Not found.', 404);
            if ($row['status'] === 'approved') jsonFail('Cannot delete an approved adjustment.');
            $pdo->prepare('DELETE FROM stock_adjustments WHERE id = ?')->execute([$id]);
            logActivity('Stock Adjustment Deleted', 'inventory', $id, 'Deleted ' . $row['reference_no']);
            jsonOk(null, 'Adjustment deleted.');
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API ADJUSTMENTS] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}