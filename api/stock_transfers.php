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
            if ($status !== '') { $where[] = 't.status = ?'; $params[] = $status; }

            $sql = 'SELECT t.*, wf.name AS from_name, wt.name AS to_name,
                           u1.name AS requested_by_name, u2.name AS approved_by_name,
                           (SELECT COUNT(*) FROM stock_transfer_items i WHERE i.transfer_id = t.id) AS item_count
                    FROM stock_transfers t
                    JOIN warehouses wf ON wf.id = t.from_warehouse_id
                    JOIN warehouses wt ON wt.id = t.to_warehouse_id
                    LEFT JOIN users u1 ON u1.id = t.requested_by
                    LEFT JOIN users u2 ON u2.id = t.approved_by
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY t.id DESC LIMIT 200';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) $r['status_badge'] = statusBadge($r['status']);
            unset($r);
            jsonOk(['items' => $rows]);
            break;

        case 'get':
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare(
                'SELECT t.*, wf.name AS from_name, wt.name AS to_name
                 FROM stock_transfers t
                 JOIN warehouses wf ON wf.id = t.from_warehouse_id
                 JOIN warehouses wt ON wt.id = t.to_warehouse_id
                 WHERE t.id = ?'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonFail('Transfer not found.', 404);

            $i = $pdo->prepare(
                'SELECT i.*, p.name AS product_name, p.sku, p.unit
                 FROM stock_transfer_items i
                 JOIN products p ON p.id = i.product_id
                 WHERE i.transfer_id = ?'
            );
            $i->execute([$id]);
            $row['items'] = $i->fetchAll();

            jsonOk($row);
            break;

        case 'save':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);

            $id         = (int)post('id', 0);
            $fromWh     = (int)post('from_warehouse_id', 0);
            $toWh       = (int)post('to_warehouse_id', 0);
            $date       = post('transfer_date', date('Y-m-d'));
            $notes      = clean((string)post('notes', ''), 400);
            $items      = post('items', []);

            $errors = [];
            if ($fromWh <= 0) $errors[] = 'Source warehouse is required.';
            if ($toWh <= 0)   $errors[] = 'Destination warehouse is required.';
            if ($fromWh === $toWh) $errors[] = 'Source and destination must be different.';
            if (!is_array($items) || count($items) === 0) $errors[] = 'At least one item is required.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                $pdo->beginTransaction();

                if ($id > 0) {
                    $up = $pdo->prepare(
                        'UPDATE stock_transfers SET from_warehouse_id=?, to_warehouse_id=?, transfer_date=?, notes=?, updated_at=NOW()
                         WHERE id=? AND status IN ("draft","pending")'
                    );
                    $up->execute([$fromWh, $toWh, $date, $notes ?: null, $id]);
                    $tno = $pdo->query('SELECT transfer_no FROM stock_transfers WHERE id = ' . (int)$id)->fetchColumn();
                    $pdo->prepare('DELETE FROM stock_transfer_items WHERE transfer_id = ?')->execute([$id]);
                } else {
                    $tno = generateRef('TRF', 'stock_transfers', 'transfer_no');
                    $ins = $pdo->prepare(
                        'INSERT INTO stock_transfers (transfer_no, from_warehouse_id, to_warehouse_id, transfer_date,
                            status, notes, requested_by, created_at)
                         VALUES (?, ?, ?, ?, "pending", ?, ?, NOW())'
                    );
                    $ins->execute([$tno, $fromWh, $toWh, $date, $notes ?: null, currentUserId()]);
                    $id = (int)$pdo->lastInsertId();
                }

                $insItem = $pdo->prepare(
                    'INSERT INTO stock_transfer_items (transfer_id, product_id, variant_id, quantity, notes)
                     VALUES (?, ?, ?, ?, ?)'
                );

                foreach ($items as $row) {
                    $productId = (int)($row['product_id'] ?? 0);
                    $variantId = (int)($row['variant_id'] ?? 0);
                    $qty       = decimal_or($row['quantity'] ?? 0, 0);
                    if ($productId <= 0 || $qty <= 0) continue;
                    $insItem->execute([$id, $productId, $variantId, $qty, $row['notes'] ?? null]);
                }

                $pdo->commit();
                logActivity('Stock Transfer Saved', 'inventory', $id, "Saved $tno");
                jsonOk(['id' => $id, 'transfer_no' => $tno], 'Transfer saved.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail('Could not save transfer: ' . $ex->getMessage(), 500);
            }
            break;

        case 'complete':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            $id = (int)post('id', 0);

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('SELECT * FROM stock_transfers WHERE id = ? AND status IN ("pending","in_transit") FOR UPDATE');
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if (!$row) throw new RuntimeException('Transfer not found or already completed.');

                $items = $pdo->prepare('SELECT * FROM stock_transfer_items WHERE transfer_id = ?');
                $items->execute([$id]);
                $allItems = $items->fetchAll();

                if (empty($allItems)) throw new RuntimeException('No items to transfer.');

                foreach ($allItems as $it) {
                    $productId = (int)$it['product_id'];
                    $variantId = (int)$it['variant_id'];
                    $qty       = (float)$it['quantity'];

                    $cost = (float)$pdo->query("SELECT cost_price FROM products WHERE id = $productId")->fetchColumn();

                    // OUT from source
                    applyStockMovement($pdo, [
                        'product_id'     => $productId,
                        'variant_id'     => $variantId,
                        'warehouse_id'   => (int)$row['from_warehouse_id'],
                        'movement_type'  => 'transfer_out',
                        'direction'      => 'out',
                        'quantity'       => $qty,
                        'unit_cost'      => $cost,
                        'reference_type' => 'stock_transfer',
                        'reference_id'   => $id,
                        'reference_no'   => $row['transfer_no'],
                        'notes'          => 'Transfer to warehouse #' . $row['to_warehouse_id'],
                    ]);

                    // IN to destination
                    applyStockMovement($pdo, [
                        'product_id'     => $productId,
                        'variant_id'     => $variantId,
                        'warehouse_id'   => (int)$row['to_warehouse_id'],
                        'movement_type'  => 'transfer_in',
                        'direction'      => 'in',
                        'quantity'       => $qty,
                        'unit_cost'      => $cost,
                        'reference_type' => 'stock_transfer',
                        'reference_id'   => $id,
                        'reference_no'   => $row['transfer_no'],
                        'notes'          => 'Transfer from warehouse #' . $row['from_warehouse_id'],
                    ]);

                    $pdo->prepare('UPDATE stock_transfer_items SET received_qty = ? WHERE id = ?')
                        ->execute([$qty, $it['id']]);
                }

                $pdo->prepare('UPDATE stock_transfers SET status = "completed", approved_by = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([currentUserId(), $id]);

                $pdo->commit();

                logActivity('Stock Transfer Completed', 'inventory', $id, 'Completed ' . $row['transfer_no']);
                pushNotification('Stock Transfer Completed',
                    "Transfer {$row['transfer_no']} completed successfully.",
                    'success', 'inventory', BASE_URL . '/inventory/transfers.php');

                jsonOk(null, 'Transfer completed. Stock has been moved.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 400);
            }
            break;

        case 'cancel':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            $id = (int)post('id', 0);
            $pdo->prepare('UPDATE stock_transfers SET status = "cancelled", updated_at = NOW() WHERE id = ? AND status IN ("pending","draft")')
                ->execute([$id]);
            logActivity('Stock Transfer Cancelled', 'inventory', $id, 'Cancelled transfer');
            jsonOk(null, 'Transfer cancelled.');
            break;

        case 'delete':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT transfer_no, status FROM stock_transfers WHERE id = ?');
            $chk->execute([$id]);
            $row = $chk->fetch();
            if (!$row) jsonFail('Not found.', 404);
            if ($row['status'] === 'completed') jsonFail('Cannot delete a completed transfer.');
            $pdo->prepare('DELETE FROM stock_transfers WHERE id = ?')->execute([$id]);
            logActivity('Stock Transfer Deleted', 'inventory', $id, 'Deleted ' . $row['transfer_no']);
            jsonOk(null, 'Transfer deleted.');
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API TRANSFERS] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}