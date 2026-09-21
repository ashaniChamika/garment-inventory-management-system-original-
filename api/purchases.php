<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();
require_once __DIR__ . '/../includes/stock.php';

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        /* ==================================================
           PURCHASE ORDERS
           ================================================== */
        case 'list':
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $q          = trim((string)get('q', ''));
            $supplierId = (int)get('supplier_id', 0);
            $status     = trim((string)get('status', ''));
            $dateFrom   = trim((string)get('date_from', ''));
            $dateTo     = trim((string)get('date_to', ''));
            $page       = max(1, (int)get('page', 1));
            $perPage    = 15;

            $where  = ['po.deleted_at IS NULL'];
            $params = [];
            if ($q !== '') { $where[] = '(po.po_number LIKE ? OR s.company_name LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like); }
            if ($supplierId > 0) { $where[] = 'po.supplier_id = ?'; $params[] = $supplierId; }
            if ($status !== '')  { $where[] = 'po.status = ?'; $params[] = $status; }
            if ($dateFrom !== ''){ $where[] = 'po.order_date >= ?'; $params[] = $dateFrom; }
            if ($dateTo !== '')  { $where[] = 'po.order_date <= ?'; $params[] = $dateTo; }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $c = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id $whereSql");
            $c->execute($params);
            $total = (int)$c->fetchColumn();
            $pg = paginate($total, $perPage, $page);

            $sql = "SELECT po.*, s.company_name, w.name AS warehouse_name,
                           u1.name AS created_by_name, u2.name AS approved_by_name,
                           (SELECT COUNT(*) FROM purchase_order_items i WHERE i.po_id = po.id) AS item_count
                    FROM purchase_orders po
                    JOIN suppliers s ON s.id = po.supplier_id
                    JOIN warehouses w ON w.id = po.warehouse_id
                    LEFT JOIN users u1 ON u1.id = po.created_by
                    LEFT JOIN users u2 ON u2.id = po.approved_by
                    $whereSql
                    ORDER BY po.id DESC
                    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                $r['status_badge'] = statusBadge($r['status']);
                $r['total']        = (float)$r['total'];
                $r['paid_amount']  = (float)$r['paid_amount'];
                $r['balance']      = $r['total'] - $r['paid_amount'];
            }
            unset($r);
            jsonOk(['items' => $rows, 'pagination' => $pg]);
            break;

        case 'get':
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare(
                'SELECT po.*, s.company_name, s.contact_person, s.phone AS supplier_phone, s.email AS supplier_email,
                        w.name AS warehouse_name
                 FROM purchase_orders po
                 JOIN suppliers s ON s.id = po.supplier_id
                 JOIN warehouses w ON w.id = po.warehouse_id
                 WHERE po.id = ? AND po.deleted_at IS NULL'
            );
            $stmt->execute([$id]);
            $po = $stmt->fetch();
            if (!$po) jsonFail('PO not found.', 404);

            $i = $pdo->prepare(
                'SELECT i.*, p.name AS product_name, p.sku, p.unit
                 FROM purchase_order_items i
                 JOIN products p ON p.id = i.product_id
                 WHERE i.po_id = ?'
            );
            $i->execute([$id]);
            $po['items'] = $i->fetchAll();
            jsonOk($po);
            break;

        case 'save_po':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $id          = (int)post('id', 0);
            $supplierId  = (int)post('supplier_id', 0);
            $warehouseId = (int)post('warehouse_id', 0);
            $orderDate   = post('order_date', date('Y-m-d'));
            $expected    = post('expected_date') ?: null;
            $discount    = decimal_or(post('discount', '0'), 0);
            $tax         = decimal_or(post('tax', '0'), 0);
            $shipping    = decimal_or(post('shipping', '0'), 0);
            $notes       = clean((string)post('notes', ''), 500);
            $items       = post('items', []);

            $errors = [];
            if ($supplierId <= 0)  $errors[] = 'Supplier is required.';
            if ($warehouseId <= 0) $errors[] = 'Warehouse is required.';
            if (!is_array($items) || count($items) === 0) $errors[] = 'At least one line item is required.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                $pdo->beginTransaction();

                // Compute subtotal
                $subtotal = 0;
                $cleanItems = [];
                foreach ($items as $row) {
                    $pid = (int)($row['product_id'] ?? 0);
                    $qty = decimal_or($row['quantity'] ?? 0, 0);
                    $price = decimal_or($row['unit_price'] ?? 0, 0);
                    if ($pid <= 0 || $qty <= 0) continue;

                    $lineDisc = decimal_or($row['discount'] ?? 0, 0);
                    $lineTax  = decimal_or($row['tax'] ?? 0, 0);
                    $lineTotal = ($qty * $price) - $lineDisc + $lineTax;
                    $subtotal += $qty * $price;

                    $cleanItems[] = [
                        'product_id' => $pid,
                        'variant_id' => (int)($row['variant_id'] ?? 0),
                        'quantity'   => $qty,
                        'unit_price' => $price,
                        'discount'   => $lineDisc,
                        'tax'        => $lineTax,
                        'total'      => $lineTotal,
                    ];
                }

                if (empty($cleanItems)) throw new RuntimeException('No valid line items provided.');

                $grandTotal = $subtotal - $discount + $tax + $shipping;

                if ($id > 0) {
                    $up = $pdo->prepare(
                        'UPDATE purchase_orders SET supplier_id=?, warehouse_id=?, order_date=?, expected_date=?,
                            subtotal=?, discount=?, tax=?, shipping=?, total=?, notes=?, updated_at=NOW()
                         WHERE id=? AND status IN ("draft","pending") AND deleted_at IS NULL'
                    );
                    $up->execute([$supplierId, $warehouseId, $orderDate, $expected,
                                  $subtotal, $discount, $tax, $shipping, $grandTotal, $notes ?: null, $id]);
                    $poNo = $pdo->query('SELECT po_number FROM purchase_orders WHERE id = ' . (int)$id)->fetchColumn();
                    $pdo->prepare('DELETE FROM purchase_order_items WHERE po_id = ?')->execute([$id]);
                } else {
                    $poNo = generateRef('PO', 'purchase_orders', 'po_number');
                    $ins = $pdo->prepare(
                        'INSERT INTO purchase_orders (po_number, supplier_id, warehouse_id, order_date, expected_date,
                            subtotal, discount, tax, shipping, total, notes, status, created_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "draft", ?, NOW())'
                    );
                    $ins->execute([$poNo, $supplierId, $warehouseId, $orderDate, $expected,
                                   $subtotal, $discount, $tax, $shipping, $grandTotal, $notes ?: null, currentUserId()]);
                    $id = (int)$pdo->lastInsertId();
                }

                $insItem = $pdo->prepare(
                    'INSERT INTO purchase_order_items (po_id, product_id, variant_id, quantity, received_qty, unit_price, discount, tax, total)
                     VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)'
                );
                foreach ($cleanItems as $it) {
                    $insItem->execute([$id, $it['product_id'], $it['variant_id'], $it['quantity'],
                                       $it['unit_price'], $it['discount'], $it['tax'], $it['total']]);
                }

                $pdo->commit();
                logActivity('Purchase Order Saved', 'purchase', $id, "Saved $poNo");
                jsonOk(['id' => $id, 'po_number' => $poNo], 'Purchase order saved.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail('Could not save PO: ' . $ex->getMessage(), 500);
            }
            break;

        case 'submit_po':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT po_number FROM purchase_orders WHERE id = ? AND status = "draft"');
            $chk->execute([$id]);
            $no = $chk->fetchColumn();
            if (!$no) jsonFail('PO not found or already submitted.');

            $pdo->prepare('UPDATE purchase_orders SET status = "pending", updated_at = NOW() WHERE id = ?')->execute([$id]);
            logActivity('PO Submitted', 'purchase', $id, "Submitted $no for approval");
            pushNotification('New Purchase Order', "PO $no is awaiting approval.", 'info', 'purchase',
                BASE_URL . '/purchases/purchase-order-view.php?id=' . $id);
            jsonOk(null, 'Purchase order submitted for approval.');
            break;

        case 'approve_po':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT po_number FROM purchase_orders WHERE id = ? AND status IN ("draft","pending")');
            $chk->execute([$id]);
            $no = $chk->fetchColumn();
            if (!$no) jsonFail('PO not found or not in an approvable state.');

            $pdo->prepare('UPDATE purchase_orders SET status = "approved", approved_by = ?, updated_at = NOW() WHERE id = ?')
                ->execute([currentUserId(), $id]);
            logActivity('PO Approved', 'purchase', $id, "Approved $no");
            jsonOk(null, 'Purchase order approved.');
            break;

        case 'cancel_po':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT po_number, status FROM purchase_orders WHERE id = ?');
            $chk->execute([$id]);
            $row = $chk->fetch();
            if (!$row) jsonFail('PO not found.');
            if (in_array($row['status'], ['received', 'cancelled'], true)) jsonFail('Cannot cancel this PO.');

            $pdo->prepare('UPDATE purchase_orders SET status = "cancelled", updated_at = NOW() WHERE id = ?')->execute([$id]);
            logActivity('PO Cancelled', 'purchase', $id, "Cancelled {$row['po_number']}");
            jsonOk(null, 'Purchase order cancelled.');
            break;

        case 'delete_po':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT po_number, status FROM purchase_orders WHERE id = ?');
            $chk->execute([$id]);
            $row = $chk->fetch();
            if (!$row) jsonFail('PO not found.');
            if (!in_array($row['status'], ['draft', 'cancelled'], true)) {
                jsonFail('Only draft or cancelled purchase orders can be deleted.');
            }
            $pdo->prepare('UPDATE purchase_orders SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
            logActivity('PO Deleted', 'purchase', $id, "Deleted {$row['po_number']}");
            jsonOk(null, 'Purchase order deleted.');
            break;

        /* ==================================================
           GOODS RECEIVED
           ================================================== */
        case 'grn_list':
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $q        = trim((string)get('q', ''));
            $status   = trim((string)get('status', ''));
            $page     = max(1, (int)get('page', 1));
            $perPage  = 15;

            $where  = ['1=1'];
            $params = [];
            if ($q !== '') { $where[] = '(g.grn_number LIKE ? OR s.company_name LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like); }
            if ($status !== '') { $where[] = 'g.status = ?'; $params[] = $status; }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $c = $pdo->prepare("SELECT COUNT(*) FROM goods_received g JOIN suppliers s ON s.id=g.supplier_id $whereSql");
            $c->execute($params);
            $total = (int)$c->fetchColumn();
            $pg = paginate($total, $perPage, $page);

            $sql = "SELECT g.*, s.company_name, w.name AS warehouse_name, po.po_number,
                           (SELECT COUNT(*) FROM goods_received_items i WHERE i.grn_id = g.id) AS item_count,
                           (SELECT COALESCE(SUM(i.quantity * i.unit_cost),0) FROM goods_received_items i WHERE i.grn_id = g.id) AS total
                    FROM goods_received g
                    JOIN suppliers s ON s.id = g.supplier_id
                    JOIN warehouses w ON w.id = g.warehouse_id
                    LEFT JOIN purchase_orders po ON po.id = g.po_id
                    $whereSql
                    ORDER BY g.id DESC
                    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                $r['status_badge'] = statusBadge($r['status']);
                $r['total'] = (float)$r['total'];
            }
            unset($r);
            jsonOk(['items' => $rows, 'pagination' => $pg]);
            break;

        case 'grn_get':
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare(
                'SELECT g.*, s.company_name, w.name AS warehouse_name, po.po_number
                 FROM goods_received g
                 JOIN suppliers s ON s.id = g.supplier_id
                 JOIN warehouses w ON w.id = g.warehouse_id
                 LEFT JOIN purchase_orders po ON po.id = g.po_id
                 WHERE g.id = ?'
            );
            $stmt->execute([$id]);
            $g = $stmt->fetch();
            if (!$g) jsonFail('GRN not found.', 404);

            $i = $pdo->prepare(
                'SELECT i.*, p.name AS product_name, p.sku, p.unit
                 FROM goods_received_items i
                 JOIN products p ON p.id = i.product_id
                 WHERE i.grn_id = ?'
            );
            $i->execute([$id]);
            $g['items'] = $i->fetchAll();
            jsonOk($g);
            break;

        case 'save_grn':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $id          = (int)post('id', 0);
            $poId        = (int)post('po_id', 0);
            $supplierId  = (int)post('supplier_id', 0);
            $warehouseId = (int)post('warehouse_id', 0);
            $receivedDate= post('received_date', date('Y-m-d'));
            $invoiceNo   = clean((string)post('invoice_no', ''), 80);
            $notes       = clean((string)post('notes', ''), 500);
            $items       = post('items', []);
            $completeNow = post('complete_now') ? true : false;

            $errors = [];
            if ($supplierId <= 0)  $errors[] = 'Supplier is required.';
            if ($warehouseId <= 0) $errors[] = 'Warehouse is required.';
            if (!is_array($items) || count($items) === 0) $errors[] = 'At least one line item is required.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                $pdo->beginTransaction();

                if ($id > 0) {
                    $up = $pdo->prepare(
                        'UPDATE goods_received SET po_id=?, supplier_id=?, warehouse_id=?, received_date=?, invoice_no=?, notes=?, updated_at=NOW()
                         WHERE id=? AND status="draft"'
                    );
                    $up->execute([$poId ?: null, $supplierId, $warehouseId, $receivedDate, $invoiceNo ?: null, $notes ?: null, $id]);
                    $grnNo = $pdo->query('SELECT grn_number FROM goods_received WHERE id = ' . (int)$id)->fetchColumn();
                    $pdo->prepare('DELETE FROM goods_received_items WHERE grn_id = ?')->execute([$id]);
                } else {
                    $grnNo = generateRef('GRN', 'goods_received', 'grn_number');
                    $ins = $pdo->prepare(
                        'INSERT INTO goods_received (grn_number, po_id, supplier_id, warehouse_id, received_date, invoice_no, notes, status, created_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, "draft", ?, NOW())'
                    );
                    $ins->execute([$grnNo, $poId ?: null, $supplierId, $warehouseId, $receivedDate,
                                   $invoiceNo ?: null, $notes ?: null, currentUserId()]);
                    $id = (int)$pdo->lastInsertId();
                }

                $insItem = $pdo->prepare(
                    'INSERT INTO goods_received_items (grn_id, po_item_id, product_id, variant_id, quantity, rejected_qty, unit_cost, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

                foreach ($items as $row) {
                    $pid  = (int)($row['product_id'] ?? 0);
                    $qty  = decimal_or($row['quantity'] ?? 0, 0);
                    $rej  = decimal_or($row['rejected_qty'] ?? 0, 0);
                    $cost = decimal_or($row['unit_cost'] ?? 0, 0);
                    if ($pid <= 0 || $qty <= 0) continue;

                    $insItem->execute([
                        $id,
                        !empty($row['po_item_id']) ? (int)$row['po_item_id'] : null,
                        $pid,
                        (int)($row['variant_id'] ?? 0),
                        $qty,
                        $rej,
                        $cost,
                        !empty($row['notes']) ? clean((string)$row['notes'], 255) : null,
                    ]);
                }

                // If complete now, apply stock
                if ($completeNow) {
                    applyGrnStock($pdo, $id);
                }

                $pdo->commit();
                logActivity('GRN Saved', 'purchase', $id, "Saved $grnNo");
                jsonOk(['id' => $id, 'grn_number' => $grnNo, 'completed' => $completeNow],
                    $completeNow ? 'Goods received and stock updated.' : 'GRN saved as draft.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 400);
            }
            break;

        case 'complete_grn':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            try {
                $pdo->beginTransaction();
                applyGrnStock($pdo, $id);
                $pdo->commit();

                $grnNo = $pdo->query('SELECT grn_number FROM goods_received WHERE id = ' . (int)$id)->fetchColumn();
                logActivity('GRN Completed', 'purchase', $id, "Completed $grnNo");
                pushNotification('Goods Received', "GRN $grnNo completed - stock updated.", 'success', 'purchase',
                    BASE_URL . '/purchases/goods-received.php');

                jsonOk(null, 'GRN completed. Stock has been updated.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 400);
            }
            break;

        case 'cancel_grn':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT grn_number, status FROM goods_received WHERE id = ?');
            $chk->execute([$id]);
            $row = $chk->fetch();
            if (!$row) jsonFail('GRN not found.');
            if ($row['status'] === 'completed') jsonFail('Cannot cancel a completed GRN.');

            $pdo->prepare('UPDATE goods_received SET status = "cancelled", updated_at = NOW() WHERE id = ?')->execute([$id]);
            logActivity('GRN Cancelled', 'purchase', $id, "Cancelled {$row['grn_number']}");
            jsonOk(null, 'GRN cancelled.');
            break;

        /* ==================================================
           PURCHASE RETURNS
           ================================================== */
        case 'returns_list':
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $status = trim((string)get('status', ''));
            $where  = ['1=1'];
            $params = [];
            if ($status !== '') { $where[] = 'r.status = ?'; $params[] = $status; }

            $sql = "SELECT r.*, s.company_name, w.name AS warehouse_name, po.po_number,
                           (SELECT COUNT(*) FROM purchase_return_items i WHERE i.return_id = r.id) AS item_count
                    FROM purchase_returns r
                    JOIN suppliers s ON s.id = r.supplier_id
                    JOIN warehouses w ON w.id = r.warehouse_id
                    LEFT JOIN purchase_orders po ON po.id = r.po_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY r.id DESC LIMIT 200";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['status_badge'] = statusBadge($r['status']);
                $r['total'] = (float)$r['total'];
            }
            unset($r);
            jsonOk(['items' => $rows]);
            break;

        case 'save_return':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $id           = (int)post('id', 0);
            $poId         = (int)post('po_id', 0);
            $supplierId   = (int)post('supplier_id', 0);
            $warehouseId  = (int)post('warehouse_id', 0);
            $returnDate   = post('return_date', date('Y-m-d'));
            $reason       = clean((string)post('reason', ''), 400);
            $items        = post('items', []);

            $errors = [];
            if ($supplierId <= 0)  $errors[] = 'Supplier is required.';
            if ($warehouseId <= 0) $errors[] = 'Warehouse is required.';
            if (!is_array($items) || count($items) === 0) $errors[] = 'At least one line item is required.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                $pdo->beginTransaction();

                $total = 0;
                $cleanItems = [];
                foreach ($items as $row) {
                    $pid = (int)($row['product_id'] ?? 0);
                    $qty = decimal_or($row['quantity'] ?? 0, 0);
                    $cost= decimal_or($row['unit_cost'] ?? 0, 0);
                    if ($pid <= 0 || $qty <= 0) continue;
                    $total += $qty * $cost;
                    $cleanItems[] = ['product_id'=>$pid, 'variant_id'=>(int)($row['variant_id']??0), 'quantity'=>$qty, 'unit_cost'=>$cost, 'total'=>$qty*$cost];
                }

                if (empty($cleanItems)) throw new RuntimeException('No valid line items.');

                if ($id > 0) {
                    $up = $pdo->prepare('UPDATE purchase_returns SET po_id=?, supplier_id=?, warehouse_id=?, return_date=?, reason=?, total=?, updated_at=NOW()
                                         WHERE id=? AND status="pending"');
                    $up->execute([$poId ?: null, $supplierId, $warehouseId, $returnDate, $reason ?: null, $total, $id]);
                    $retNo = $pdo->query('SELECT return_no FROM purchase_returns WHERE id = ' . (int)$id)->fetchColumn();
                    $pdo->prepare('DELETE FROM purchase_return_items WHERE return_id = ?')->execute([$id]);
                } else {
                    $retNo = generateRef('PRT', 'purchase_returns', 'return_no');
                    $ins = $pdo->prepare('INSERT INTO purchase_returns (return_no, po_id, supplier_id, warehouse_id, return_date, reason, total, status, created_by, created_at)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, "pending", ?, NOW())');
                    $ins->execute([$retNo, $poId ?: null, $supplierId, $warehouseId, $returnDate, $reason ?: null, $total, currentUserId()]);
                    $id = (int)$pdo->lastInsertId();
                }

                $insItem = $pdo->prepare('INSERT INTO purchase_return_items (return_id, product_id, variant_id, quantity, unit_cost, total)
                                          VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($cleanItems as $it) {
                    $insItem->execute([$id, $it['product_id'], $it['variant_id'], $it['quantity'], $it['unit_cost'], $it['total']]);
                }

                $pdo->commit();
                logActivity('Purchase Return Saved', 'purchase', $id, "Saved $retNo");
                jsonOk(['id' => $id, 'return_no' => $retNo], 'Purchase return saved.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 500);
            }
            break;

        case 'complete_return':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            try {
                $pdo->beginTransaction();

                $ret = $pdo->prepare('SELECT * FROM purchase_returns WHERE id = ? AND status IN ("pending","approved") FOR UPDATE');
                $ret->execute([$id]);
                $row = $ret->fetch();
                if (!$row) throw new RuntimeException('Return not found or already processed.');

                $items = $pdo->prepare('SELECT * FROM purchase_return_items WHERE return_id = ?');
                $items->execute([$id]);
                $allItems = $items->fetchAll();

                foreach ($allItems as $it) {
                    applyStockMovement($pdo, [
                        'product_id'     => (int)$it['product_id'],
                        'variant_id'     => (int)$it['variant_id'],
                        'warehouse_id'   => (int)$row['warehouse_id'],
                        'movement_type'  => 'return_out',
                        'direction'      => 'out',
                        'quantity'       => (float)$it['quantity'],
                        'unit_cost'      => (float)$it['unit_cost'],
                        'reference_type' => 'purchase_return',
                        'reference_id'   => $id,
                        'reference_no'   => $row['return_no'],
                        'notes'          => 'Purchase return to supplier',
                    ]);
                }

                $pdo->prepare('UPDATE purchase_returns SET status = "completed", updated_at = NOW() WHERE id = ?')->execute([$id]);
                $pdo->commit();

                logActivity('Purchase Return Completed', 'purchase', $id, "Completed {$row['return_no']}");
                jsonOk(null, 'Return completed. Stock has been deducted.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 400);
            }
            break;

        case 'delete_return':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('purchase.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT status FROM purchase_returns WHERE id = ?');
            $chk->execute([$id]);
            $s = $chk->fetchColumn();
            if ($s === 'completed') jsonFail('Cannot delete a completed return.');
            $pdo->prepare('DELETE FROM purchase_returns WHERE id = ?')->execute([$id]);
            jsonOk(null, 'Return deleted.');
            break;

        /* ---------------- PO lookup (for GRN) ---------------- */
        case 'po_lookup':
            $q = trim((string)get('q', ''));
            $where = ['po.deleted_at IS NULL', "po.status IN ('approved','partially_received','pending')"];
            $params = [];
            if ($q !== '') { $where[] = 'po.po_number LIKE ?'; $params[] = '%' . $q . '%'; }

            $stmt = $pdo->prepare(
                'SELECT po.id, po.po_number, po.supplier_id, po.warehouse_id, s.company_name
                 FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY po.id DESC LIMIT 30'
            );
            $stmt->execute($params);
            jsonOk(['items' => $stmt->fetchAll()]);
            break;

        case 'po_items_for_grn':
            $poId = (int)get('po_id', 0);
            $stmt = $pdo->prepare(
                'SELECT i.id, i.product_id, i.variant_id, i.quantity, i.received_qty, i.unit_price,
                        (i.quantity - i.received_qty) AS remaining,
                        p.name AS product_name, p.sku, p.unit
                 FROM purchase_order_items i
                 JOIN products p ON p.id = i.product_id
                 WHERE i.po_id = ? AND (i.quantity - i.received_qty) > 0'
            );
            $stmt->execute([$poId]);
            jsonOk(['items' => $stmt->fetchAll()]);
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API PURCHASES] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}

/* =========================================================================
   Helper: Apply GRN stock movements, update PO received_qty & status
   ========================================================================= */
function applyGrnStock(PDO $pdo, int $grnId): void
{
    $grnStmt = $pdo->prepare('SELECT * FROM goods_received WHERE id = ? FOR UPDATE');
    $grnStmt->execute([$grnId]);
    $grn = $grnStmt->fetch();
    if (!$grn) throw new RuntimeException('GRN not found.');
    if ($grn['status'] === 'completed') throw new RuntimeException('GRN is already completed.');

    $items = $pdo->prepare('SELECT * FROM goods_received_items WHERE grn_id = ?');
    $items->execute([$grnId]);
    $rows = $items->fetchAll();
    if (empty($rows)) throw new RuntimeException('No items on GRN.');

    foreach ($rows as $it) {
        $acceptedQty = (float)$it['quantity'] - (float)$it['rejected_qty'];
        if ($acceptedQty > 0) {
            applyStockMovement($pdo, [
                'product_id'     => (int)$it['product_id'],
                'variant_id'     => (int)$it['variant_id'],
                'warehouse_id'   => (int)$grn['warehouse_id'],
                'movement_type'  => 'purchase_in',
                'direction'      => 'in',
                'quantity'       => $acceptedQty,
                'unit_cost'      => (float)$it['unit_cost'],
                'reference_type' => 'goods_received',
                'reference_id'   => $grnId,
                'reference_no'   => $grn['grn_number'],
                'notes'          => $grn['invoice_no'] ? 'Invoice ' . $grn['invoice_no'] : 'Goods received',
            ]);

            // Update PO received_qty
            if (!empty($it['po_item_id'])) {
                $pdo->prepare('UPDATE purchase_order_items SET received_qty = received_qty + ? WHERE id = ?')
                    ->execute([$acceptedQty, (int)$it['po_item_id']]);
            }
        }

        // If some rejected, increment rejected_qty? We don't have that on PO item - leave as-is.
    }

    // Mark GRN completed
    $pdo->prepare('UPDATE goods_received SET status = "completed", updated_at = NOW() WHERE id = ?')->execute([$grnId]);

    // Recompute PO status
    if (!empty($grn['po_id'])) {
        $poId = (int)$grn['po_id'];
        $row = $pdo->query(
            "SELECT SUM(quantity) AS ordered, SUM(received_qty) AS received
             FROM purchase_order_items WHERE po_id = $poId"
        )->fetch();

        $ordered  = (float)($row['ordered'] ?? 0);
        $received = (float)($row['received'] ?? 0);

        if ($received <= 0) {
            $status = 'approved';
        } elseif ($received >= $ordered - 0.001) {
            $status = 'received';
        } else {
            $status = 'partially_received';
        }

        $pdo->prepare('UPDATE purchase_orders SET status = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$status, $poId]);

        if ($status === 'received') {
            pushNotification('PO Fully Received',
                "All items received for PO ID $poId.",
                'success', 'purchase', BASE_URL . '/purchases/purchase-order-view.php?id=' . $poId);
        }
    }
}