<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();
require_once __DIR__ . '/../includes/stock.php';

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        /* ==================================================
           PRODUCTION ORDERS
           ================================================== */
        case 'list':
            if (!hasPermission('production.manage')) jsonFail('Forbidden', 403);

            $q        = trim((string)get('q', ''));
            $status   = trim((string)get('status', ''));
            $productId= (int)get('product_id', 0);
            $dateFrom = trim((string)get('date_from', ''));
            $dateTo   = trim((string)get('date_to', ''));
            $page     = max(1, (int)get('page', 1));
            $perPage  = 15;

            $where  = ['po.deleted_at IS NULL'];
            $params = [];
            if ($q !== '') { $where[] = '(po.order_no LIKE ? OR p.name LIKE ? OR p.sku LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like,$like); }
            if ($status !== '') { $where[] = 'po.status = ?'; $params[] = $status; }
            if ($productId > 0) { $where[] = 'po.product_id = ?'; $params[] = $productId; }
            if ($dateFrom !== '') { $where[] = 'po.start_date >= ?'; $params[] = $dateFrom; }
            if ($dateTo !== '')   { $where[] = 'po.start_date <= ?'; $params[] = $dateTo; }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $c = $pdo->prepare("SELECT COUNT(*) FROM production_orders po JOIN products p ON p.id=po.product_id $whereSql");
            $c->execute($params);
            $total = (int)$c->fetchColumn();
            $pg = paginate($total, $perPage, $page);

            $sql = "SELECT po.*, p.name AS product_name, p.sku, p.unit,
                           w.name AS warehouse_name, e.full_name AS supervisor_name,
                           b.name AS bom_name
                    FROM production_orders po
                    JOIN products p ON p.id = po.product_id
                    JOIN warehouses w ON w.id = po.warehouse_id
                    LEFT JOIN employees e ON e.id = po.supervisor_id
                    LEFT JOIN bom b ON b.id = po.bom_id
                    $whereSql
                    ORDER BY po.id DESC
                    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                $r['status_badge'] = statusBadge($r['status']);
                $r['progress'] = $r['quantity'] > 0 ? round(($r['produced_qty'] / $r['quantity']) * 100, 1) : 0;
            }
            unset($r);
            jsonOk(['items' => $rows, 'pagination' => $pg]);
            break;

        case 'get':
            if (!hasPermission('production.manage')) jsonFail('Forbidden', 403);
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare(
                'SELECT po.*, p.name AS product_name, p.sku, p.unit,
                        w.name AS warehouse_name, e.full_name AS supervisor_name,
                        b.name AS bom_name
                 FROM production_orders po
                 JOIN products p ON p.id = po.product_id
                 JOIN warehouses w ON w.id = po.warehouse_id
                 LEFT JOIN employees e ON e.id = po.supervisor_id
                 LEFT JOIN bom b ON b.id = po.bom_id
                 WHERE po.id = ?'
            );
            $stmt->execute([$id]);
            $po = $stmt->fetch();
            if (!$po) jsonFail('Production order not found.', 404);

            $m = $pdo->prepare(
                'SELECT pm.*, p.name AS material_name, p.sku, p.unit AS material_unit
                 FROM production_materials pm
                 JOIN products p ON p.id = pm.material_id
                 WHERE pm.production_order_id = ?'
            );
            $m->execute([$id]);
            $po['materials'] = $m->fetchAll();

            $o = $pdo->prepare('SELECT * FROM production_outputs WHERE production_order_id = ? ORDER BY id ASC');
            $o->execute([$id]);
            $po['outputs'] = $o->fetchAll();

            jsonOk($po);
            break;

        case 'save':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('production.manage')) jsonFail('Forbidden', 403);

            $id           = (int)post('id', 0);
            $productId    = (int)post('product_id', 0);
            $variantId    = (int)post('variant_id', 0);
            $bomId        = (int)post('bom_id', 0);
            $quantity     = decimal_or(post('quantity', 0), 0);
            $size         = clean((string)post('size', ''), 30);
            $color        = clean((string)post('color', ''), 50);
            $warehouseId  = (int)post('warehouse_id', 0);
            $startDate    = post('start_date') ?: null;
            $expectedDate = post('expected_date') ?: null;
            $supervisor   = (int)post('supervisor_id', 0);
            $notes        = clean((string)post('notes', ''), 500);

            $errors = [];
            if ($productId <= 0)   $errors[] = 'Product is required.';
            if ($quantity <= 0)    $errors[] = 'Quantity must be greater than zero.';
            if ($warehouseId <= 0) $errors[] = 'Warehouse is required.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                $pdo->beginTransaction();

                if ($id > 0) {
                    $up = $pdo->prepare(
                        'UPDATE production_orders SET product_id=?, variant_id=?, bom_id=?, quantity=?, size=?, color=?,
                            warehouse_id=?, start_date=?, expected_date=?, supervisor_id=?, notes=?, updated_at=NOW()
                         WHERE id=? AND status IN ("planned","paused") AND deleted_at IS NULL'
                    );
                    $up->execute([$productId, $variantId, $bomId ?: null, $quantity, $size ?: null, $color ?: null,
                                  $warehouseId, $startDate, $expectedDate, $supervisor ?: null, $notes ?: null, $id]);
                    $orderNo = $pdo->query('SELECT order_no FROM production_orders WHERE id = ' . (int)$id)->fetchColumn();
                    $pdo->prepare('DELETE FROM production_materials WHERE production_order_id = ?')->execute([$id]);
                } else {
                    $orderNo = generateRef('PRD', 'production_orders', 'order_no');
                    $ins = $pdo->prepare(
                        'INSERT INTO production_orders (order_no, product_id, variant_id, bom_id, quantity, size, color,
                            warehouse_id, start_date, expected_date, supervisor_id, notes, status, created_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "planned", ?, NOW())'
                    );
                    $ins->execute([$orderNo, $productId, $variantId, $bomId ?: null, $quantity, $size ?: null,
                                   $color ?: null, $warehouseId, $startDate, $expectedDate, $supervisor ?: null,
                                   $notes ?: null, currentUserId()]);
                    $id = (int)$pdo->lastInsertId();
                }

                // Populate materials from BOM (if any)
                if ($bomId > 0) {
                    $bi = $pdo->prepare('SELECT * FROM bom_items WHERE bom_id = ?');
                    $bi->execute([$bomId]);
                    $bomItems = $bi->fetchAll();

                    $insMat = $pdo->prepare(
                        'INSERT INTO production_materials (production_order_id, material_id, required_qty, issued_qty, unit, unit_cost, status)
                         VALUES (?, ?, ?, 0, ?, ?, "pending")'
                    );
                    foreach ($bomItems as $bm) {
                        $required = (float)$bm['quantity'] * $quantity * (1 + (float)$bm['wastage_pct'] / 100);
                        $costStmt = $pdo->prepare('SELECT cost_price FROM products WHERE id = ?');
                        $costStmt->execute([$bm['material_id']]);
                        $cost = (float)$costStmt->fetchColumn();
                        $insMat->execute([$id, $bm['material_id'], $required, $bm['unit'], $cost]);
                    }
                }

                $pdo->commit();
                logActivity($id ? 'Production Order Updated' : 'Production Order Created', 'production', $id, "PO: $orderNo");
                jsonOk(['id' => $id, 'order_no' => $orderNo], 'Production order saved.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail('Could not save production order: ' . $ex->getMessage(), 500);
            }
            break;

        case 'update_status':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('production.manage')) jsonFail('Forbidden', 403);

            $id     = (int)post('id', 0);
            $status = post('status', '');
            if (!in_array($status, ['planned','in_progress','paused','completed','cancelled'], true)) {
                jsonFail('Invalid status.');
            }

            $stmt = $pdo->prepare('SELECT order_no, status FROM production_orders WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonFail('Order not found.', 404);

            $up = $pdo->prepare('UPDATE production_orders SET status=?, updated_at=NOW() WHERE id=?');
            if ($status === 'completed') {
                $up = $pdo->prepare('UPDATE production_orders SET status=?, actual_date=CURDATE(), updated_at=NOW() WHERE id=?');
            }
            $up->execute([$status, $id]);

            logActivity('Production Status Changed', 'production', $id, "{$row['order_no']} → $status");
            jsonOk(null, 'Status updated.');
            break;

        /* ==================================================
           MATERIAL ISSUE
           ================================================== */
        case 'issue_material':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('production.manage')) jsonFail('Forbidden', 403);

            $poId       = (int)post('production_order_id', 0);
            $materialId = (int)post('material_id', 0);
            $qty        = decimal_or(post('quantity', 0), 0);
            $warehouseId= (int)post('warehouse_id', 0);

            $errors = [];
            if ($poId <= 0) $errors[] = 'Production order is required.';
            if ($materialId <= 0) $errors[] = 'Material is required.';
            if ($qty <= 0) $errors[] = 'Quantity must be > 0.';
            if ($warehouseId <= 0) $errors[] = 'Warehouse is required.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                $pdo->beginTransaction();

                $chk = $pdo->prepare('SELECT * FROM production_materials WHERE production_order_id = ? AND material_id = ?');
                $chk->execute([$poId, $materialId]);
                $mat = $chk->fetch();
                if (!$mat) throw new RuntimeException('Material line not found on this production order.');

                $orderNo = $pdo->query('SELECT order_no FROM production_orders WHERE id = ' . (int)$poId)->fetchColumn();

                applyStockMovement($pdo, [
                    'product_id'     => $materialId,
                    'variant_id'     => 0,
                    'warehouse_id'   => $warehouseId,
                    'movement_type'  => 'production_out',
                    'direction'      => 'out',
                    'quantity'       => $qty,
                    'unit_cost'      => (float)$mat['unit_cost'],
                    'reference_type' => 'production_order',
                    'reference_id'   => $poId,
                    'reference_no'   => $orderNo,
                    'notes'          => 'Material issued to production',
                ]);

                $newIssued = (float)$mat['issued_qty'] + $qty;
                $newStatus = $newIssued >= (float)$mat['required_qty'] - 0.0001 ? 'issued'
                           : ($newIssued > 0 ? 'partially_issued' : 'pending');

                $pdo->prepare('UPDATE production_materials SET issued_qty = ?, status = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$newIssued, $newStatus, $mat['id']]);

                $pdo->commit();

                logActivity('Material Issued', 'production', $poId, "Issued $qty of material #$materialId");
                jsonOk(null, 'Material issued and stock deducted.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 400);
            }
            break;

        /* ==================================================
           PRODUCTION OUTPUT
           ================================================== */
        case 'add_output':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('production.manage')) jsonFail('Forbidden', 403);

            $poId       = (int)post('production_order_id', 0);
            $productId  = (int)post('product_id', 0);
            $variantId  = (int)post('variant_id', 0);
            $qty        = decimal_or(post('quantity', 0), 0);
            $warehouseId= (int)post('warehouse_id', 0);
            $outputDate = post('output_date', date('Y-m-d'));

            $errors = [];
            if ($poId <= 0 || $productId <= 0 || $qty <= 0 || $warehouseId <= 0) {
                $errors[] = 'All fields are required.';
            }
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                $pdo->beginTransaction();

                $ins = $pdo->prepare(
                    'INSERT INTO production_outputs (production_order_id, product_id, variant_id, quantity, passed_qty, rejected_qty, warehouse_id, output_date, status, created_at)
                     VALUES (?, ?, ?, ?, 0, 0, ?, ?, "qc_pending", NOW())'
                );
                $ins->execute([$poId, $productId, $variantId, $qty, $warehouseId, $outputDate]);

                // Increment produced_qty on order
                $pdo->prepare('UPDATE production_orders SET produced_qty = produced_qty + ? WHERE id = ?')
                    ->execute([$qty, $poId]);

                $pdo->commit();
                logActivity('Production Output Logged', 'production', $poId, "Output $qty logged for QC");
                pushNotification('QC Pending', "New production output awaiting QC inspection.", 'warning', 'quality',
                    BASE_URL . '/quality-control/inspections.php');
                jsonOk(null, 'Output logged. Awaiting QC.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 500);
            }
            break;

        case 'delete':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('production.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT order_no, status FROM production_orders WHERE id = ?');
            $chk->execute([$id]);
            $row = $chk->fetch();
            if (!$row) jsonFail('Order not found.', 404);
            if (!in_array($row['status'], ['planned', 'cancelled'], true)) {
                jsonFail('Only planned or cancelled orders can be deleted.');
            }
            $pdo->prepare('UPDATE production_orders SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
            logActivity('Production Order Deleted', 'production', $id, 'Deleted ' . $row['order_no']);
            jsonOk(null, 'Production order deleted.');
            break;

        /* ==================================================
           SUMMARY
           ================================================== */
        case 'summary':
            if (!hasPermission('production.manage')) jsonFail('Forbidden', 403);

            $planned    = (int)$pdo->query("SELECT COUNT(*) FROM production_orders WHERE status='planned' AND deleted_at IS NULL")->fetchColumn();
            $inProgress = (int)$pdo->query("SELECT COUNT(*) FROM production_orders WHERE status='in_progress' AND deleted_at IS NULL")->fetchColumn();
            $completed  = (int)$pdo->query("SELECT COUNT(*) FROM production_orders WHERE status='completed' AND deleted_at IS NULL")->fetchColumn();
            $totalOut   = (float)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM production_outputs")->fetchColumn();

            jsonOk([
                'planned' => $planned,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'total_output' => $totalOut,
            ]);
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API PRODUCTION] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}