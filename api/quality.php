<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();
require_once __DIR__ . '/../includes/stock.php';

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        case 'list':
            if (!hasPermission('qc.manage')) jsonFail('Forbidden', 403);

            $q        = trim((string)get('q', ''));
            $status   = trim((string)get('status', ''));
            $productId= (int)get('product_id', 0);
            $dateFrom = trim((string)get('date_from', ''));
            $dateTo   = trim((string)get('date_to', ''));
            $page     = max(1, (int)get('page', 1));
            $perPage  = 15;

            $where  = ['1=1'];
            $params = [];
            if ($q !== '') { $where[] = '(q.inspection_no LIKE ? OR q.batch_no LIKE ? OR p.name LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like,$like); }
            if ($status !== '') { $where[] = 'q.status = ?'; $params[] = $status; }
            if ($productId > 0) { $where[] = 'q.product_id = ?'; $params[] = $productId; }
            if ($dateFrom !== '') { $where[] = 'q.inspection_date >= ?'; $params[] = $dateFrom; }
            if ($dateTo !== '')   { $where[] = 'q.inspection_date <= ?'; $params[] = $dateTo; }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $c = $pdo->prepare("SELECT COUNT(*) FROM qc_inspections q JOIN products p ON p.id=q.product_id $whereSql");
            $c->execute($params);
            $total = (int)$c->fetchColumn();
            $pg = paginate($total, $perPage, $page);

            $sql = "SELECT q.*, p.name AS product_name, p.sku, p.unit,
                           po.order_no, e.full_name AS inspector_name,
                           (SELECT COUNT(*) FROM qc_defects d WHERE d.inspection_id = q.id) AS defect_count
                    FROM qc_inspections q
                    JOIN products p ON p.id = q.product_id
                    LEFT JOIN production_orders po ON po.id = q.production_order_id
                    LEFT JOIN employees e ON e.id = q.inspector_id
                    $whereSql
                    ORDER BY q.id DESC
                    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                $r['status_badge'] = statusBadge($r['status']);
                $r['pass_rate'] = $r['inspected_qty'] > 0 ? round(($r['passed_qty'] / $r['inspected_qty']) * 100, 1) : 0;
            }
            unset($r);

            jsonOk(['items' => $rows, 'pagination' => $pg]);
            break;

        case 'get':
            if (!hasPermission('qc.manage')) jsonFail('Forbidden', 403);
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare(
                'SELECT q.*, p.name AS product_name, p.sku, p.unit, po.order_no, e.full_name AS inspector_name
                 FROM qc_inspections q
                 JOIN products p ON p.id = q.product_id
                 LEFT JOIN production_orders po ON po.id = q.production_order_id
                 LEFT JOIN employees e ON e.id = q.inspector_id
                 WHERE q.id = ?'
            );
            $stmt->execute([$id]);
            $q = $stmt->fetch();
            if (!$q) jsonFail('Inspection not found.', 404);

            $d = $pdo->prepare('SELECT * FROM qc_defects WHERE inspection_id = ? ORDER BY id');
            $d->execute([$id]);
            $q['defects'] = $d->fetchAll();
            jsonOk($q);
            break;

        case 'save':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('qc.manage')) jsonFail('Forbidden', 403);

            $id             = (int)post('id', 0);
            $poId           = (int)post('production_order_id', 0);
            $productId      = (int)post('product_id', 0);
            $variantId      = (int)post('variant_id', 0);
            $batchNo        = clean((string)post('batch_no', ''), 60);
            $inspectorId    = (int)post('inspector_id', 0);
            $inspectionDate = post('inspection_date', date('Y-m-d'));
            $inspected      = decimal_or(post('inspected_qty', 0), 0);
            $passed         = decimal_or(post('passed_qty', 0), 0);
            $failed         = decimal_or(post('failed_qty', 0), 0);
            $remarks        = clean((string)post('remarks', ''), 500);
            $defects        = post('defects', []);
            $applyStock     = post('apply_stock') ? true : false;

            $errors = [];
            if ($productId <= 0) $errors[] = 'Product is required.';
            if ($inspected <= 0) $errors[] = 'Inspected quantity must be > 0.';
            if ($passed < 0 || $failed < 0) $errors[] = 'Quantities cannot be negative.';
            if (abs(($passed + $failed) - $inspected) > 0.01) $errors[] = 'Passed + Failed must equal Inspected quantity.';

            if ($errors) jsonFail('Validation failed.', 422, $errors);

            $status = $failed <= 0 ? 'passed' : ($passed <= 0 ? 'failed' : 'partially_passed');

            try {
                $pdo->beginTransaction();

                if ($id > 0) {
                    $up = $pdo->prepare(
                        'UPDATE qc_inspections SET production_order_id=?, product_id=?, variant_id=?, batch_no=?,
                            inspector_id=?, inspection_date=?, inspected_qty=?, passed_qty=?, failed_qty=?,
                            status=?, remarks=?, updated_at=NOW()
                         WHERE id=?'
                    );
                    $up->execute([$poId ?: null, $productId, $variantId, $batchNo ?: null, $inspectorId ?: null,
                                  $inspectionDate, $inspected, $passed, $failed, $status, $remarks ?: null, $id]);
                    $inspNo = $pdo->query('SELECT inspection_no FROM qc_inspections WHERE id = ' . (int)$id)->fetchColumn();
                    $pdo->prepare('DELETE FROM qc_defects WHERE inspection_id = ?')->execute([$id]);
                } else {
                    $inspNo = generateRef('QC', 'qc_inspections', 'inspection_no');
                    $ins = $pdo->prepare(
                        'INSERT INTO qc_inspections (inspection_no, production_order_id, product_id, variant_id, batch_no,
                            inspector_id, inspection_date, inspected_qty, passed_qty, failed_qty, status, remarks, created_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $ins->execute([$inspNo, $poId ?: null, $productId, $variantId, $batchNo ?: null, $inspectorId ?: null,
                                   $inspectionDate, $inspected, $passed, $failed, $status, $remarks ?: null, currentUserId()]);
                    $id = (int)$pdo->lastInsertId();
                }

                // Save defects
                if (is_array($defects) && count($defects) > 0) {
                    $insDef = $pdo->prepare(
                        'INSERT INTO qc_defects (inspection_id, defect_type, description, quantity, severity, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())'
                    );
                    foreach ($defects as $d) {
                        $type = $d['defect_type'] ?? 'other';
                        $qty  = decimal_or($d['quantity'] ?? 0, 0);
                        if ($qty <= 0) continue;
                        $insDef->execute([$id, $type, $d['description'] ?? null, $qty, $d['severity'] ?? 'minor']);
                    }
                }

                // If new inspection and passed_qty > 0 and apply_stock requested, add to stock
                if ($applyStock && $passed > 0) {
                    // Get warehouse from production order (or default)
                    $whId = 1;
                    if ($poId > 0) {
                        $whId = (int)$pdo->query("SELECT warehouse_id FROM production_orders WHERE id = $poId")->fetchColumn();
                    }
                    if ($whId <= 0) {
                        $whId = (int)$pdo->query("SELECT id FROM warehouses WHERE is_default = 1 LIMIT 1")->fetchColumn();
                    }

                    $cost = (float)$pdo->query("SELECT cost_price FROM products WHERE id = $productId")->fetchColumn();

                    applyStockMovement($pdo, [
                        'product_id'     => $productId,
                        'variant_id'     => $variantId,
                        'warehouse_id'   => $whId,
                        'movement_type'  => 'production_in',
                        'direction'      => 'in',
                        'quantity'       => $passed,
                        'unit_cost'      => $cost,
                        'reference_type' => 'qc_inspection',
                        'reference_id'   => $id,
                        'reference_no'   => $inspNo,
                        'notes'          => 'Passed QC inspection',
                    ]);

                    // Update any production output for this order/variant
                    if ($poId > 0) {
                        $pdo->prepare('UPDATE production_outputs SET passed_qty = passed_qty + ?, rejected_qty = rejected_qty + ?, status = "completed"
                                       WHERE production_order_id = ? AND variant_id = ? AND status = "qc_pending" LIMIT 1')
                            ->execute([$passed, $failed, $poId, $variantId]);
                    }
                }

                $pdo->commit();

                logActivity($id ? 'QC Inspection Updated' : 'QC Inspection Created', 'quality', $id, "QC: $inspNo");
                if ($status === 'failed') {
                    pushNotification('QC Failed', "QC inspection $inspNo failed. Review defects.", 'danger', 'quality',
                        BASE_URL . '/quality-control/inspections.php');
                }
                jsonOk(['id' => $id, 'inspection_no' => $inspNo], 'QC inspection saved.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 500);
            }
            break;

        case 'delete':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('qc.manage')) jsonFail('Forbidden', 403);
            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT inspection_no FROM qc_inspections WHERE id = ?');
            $chk->execute([$id]);
            $no = $chk->fetchColumn();
            if (!$no) jsonFail('Inspection not found.', 404);
            $pdo->prepare('DELETE FROM qc_inspections WHERE id = ?')->execute([$id]);
            logActivity('QC Inspection Deleted', 'quality', $id, 'Deleted ' . $no);
            jsonOk(null, 'Inspection deleted.');
            break;

        case 'defects_list':
            if (!hasPermission('qc.manage')) jsonFail('Forbidden', 403);
            $status = trim((string)get('severity', ''));
            $where  = ['1=1'];
            $params = [];
            if ($status !== '') { $where[] = 'd.severity = ?'; $params[] = $status; }

            $sql = "SELECT d.*, q.inspection_no, q.inspection_date, p.name AS product_name, p.sku
                    FROM qc_defects d
                    JOIN qc_inspections q ON q.id = d.inspection_id
                    JOIN products p ON p.id = q.product_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY d.id DESC LIMIT 200";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            jsonOk(['items' => $stmt->fetchAll()]);
            break;

        case 'summary':
            if (!hasPermission('qc.manage')) jsonFail('Forbidden', 403);

            $pending = (int)$pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE status='pending'")->fetchColumn();
            $passed  = (int)$pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE status='passed'")->fetchColumn();
            $failed  = (int)$pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE status='failed'")->fetchColumn();
            $partial = (int)$pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE status='partially_passed'")->fetchColumn();
            $totalInspected = (float)$pdo->query("SELECT COALESCE(SUM(inspected_qty),0) FROM qc_inspections")->fetchColumn();
            $totalPassed    = (float)$pdo->query("SELECT COALESCE(SUM(passed_qty),0) FROM qc_inspections")->fetchColumn();
            $passRate = $totalInspected > 0 ? round(($totalPassed / $totalInspected) * 100, 1) : 0;

            jsonOk([
                'pending' => $pending, 'passed' => $passed, 'failed' => $failed, 'partial' => $partial,
                'inspected_qty' => $totalInspected, 'passed_qty' => $totalPassed, 'pass_rate' => $passRate,
            ]);
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API QUALITY] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}