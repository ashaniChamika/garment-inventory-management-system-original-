<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo    = db();
$action = get('action', 'inventory');

try {
    switch ($action) {

        /* ---------------- INVENTORY REPORT ---------------- */
        case 'inventory':
            if (!hasPermission('reports.view')) jsonFail('Forbidden', 403);

            $categoryId  = (int)get('category_id', 0);
            $warehouseId = (int)get('warehouse_id', 0);
            $filter      = trim((string)get('filter', ''));

            $where  = ['p.deleted_at IS NULL'];
            $params = [];
            if ($categoryId > 0) { $where[] = 'p.category_id = ?'; $params[] = $categoryId; }

            $whCond = $warehouseId > 0 ? ' AND s.warehouse_id = ' . $warehouseId : '';
            $having = '';
            if ($filter === 'low') $having = 'HAVING total_qty <= p.reorder_level AND total_qty > 0';
            if ($filter === 'out') $having = 'HAVING total_qty <= 0';
            if ($filter === 'ok')  $having = 'HAVING total_qty > p.reorder_level';

            $sql = 'SELECT p.id, p.sku, p.product_code, p.name, p.unit, p.cost_price, p.selling_price,
                           p.reorder_level, c.name AS category_name,
                           COALESCE(SUM(s.quantity),0) AS total_qty,
                           COALESCE(SUM(s.quantity * p.cost_price),0) AS stock_value,
                           COALESCE(SUM(s.quantity * p.selling_price),0) AS retail_value
                    FROM products p
                    LEFT JOIN categories c ON c.id = p.category_id
                    LEFT JOIN stock s ON s.product_id = p.id' . $whCond . '
                    WHERE ' . implode(' AND ', $where) . '
                    GROUP BY p.id ' . $having . '
                    ORDER BY p.name ASC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $totals = [
                'qty' => array_sum(array_map(fn($r)=>(float)$r['total_qty'], $rows)),
                'cost' => array_sum(array_map(fn($r)=>(float)$r['stock_value'], $rows)),
                'retail' => array_sum(array_map(fn($r)=>(float)$r['retail_value'], $rows)),
            ];

            jsonOk(['items'=>$rows, 'totals'=>$totals]);
            break;

        /* ---------------- PURCHASE REPORT ---------------- */
        case 'purchases':
            if (!hasPermission('reports.view')) jsonFail('Forbidden', 403);

            $from = trim((string)get('date_from', date('Y-m-01')));
            $to   = trim((string)get('date_to', date('Y-m-d')));
            $supplierId = (int)get('supplier_id', 0);

            $where = ['po.deleted_at IS NULL', 'po.order_date BETWEEN ? AND ?'];
            $params = [$from, $to];
            if ($supplierId > 0) { $where[] = 'po.supplier_id = ?'; $params[] = $supplierId; }

            $sql = 'SELECT po.id, po.po_number, po.order_date, po.total, po.paid_amount, po.status,
                           s.company_name, w.name AS warehouse_name,
                           (SELECT COUNT(*) FROM purchase_order_items i WHERE i.po_id = po.id) AS item_count
                    FROM purchase_orders po
                    JOIN suppliers s ON s.id = po.supplier_id
                    JOIN warehouses w ON w.id = po.warehouse_id
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY po.order_date DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $totals = [
                'orders' => count($rows),
                'total'  => array_sum(array_map(fn($r)=>(float)$r['total'], $rows)),
                'paid'   => array_sum(array_map(fn($r)=>(float)$r['paid_amount'], $rows)),
            ];
            $totals['balance'] = $totals['total'] - $totals['paid'];

            jsonOk(['items'=>$rows, 'totals'=>$totals, 'from'=>$from, 'to'=>$to]);
            break;

        /* ---------------- SALES REPORT ---------------- */
        case 'sales':
            if (!hasPermission('reports.view')) jsonFail('Forbidden', 403);

            $from = trim((string)get('date_from', date('Y-m-01')));
            $to   = trim((string)get('date_to', date('Y-m-d')));
            $customerId = (int)get('customer_id', 0);

            $where = ['i.invoice_date BETWEEN ? AND ?', "i.status <> 'cancelled'"];
            $params = [$from, $to];
            if ($customerId > 0) { $where[] = 'i.customer_id = ?'; $params[] = $customerId; }

            $sql = 'SELECT i.id, i.invoice_no, i.invoice_date, i.total, i.paid_amount, i.status,
                           c.name AS customer_name, c.company,
                           so.order_no
                    FROM invoices i
                    JOIN customers c ON c.id = i.customer_id
                    LEFT JOIN sales_orders so ON so.id = i.so_id
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY i.invoice_date DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $totals = [
                'invoices' => count($rows),
                'total'    => array_sum(array_map(fn($r)=>(float)$r['total'], $rows)),
                'paid'     => array_sum(array_map(fn($r)=>(float)$r['paid_amount'], $rows)),
            ];
            $totals['balance'] = $totals['total'] - $totals['paid'];

            jsonOk(['items'=>$rows, 'totals'=>$totals, 'from'=>$from, 'to'=>$to]);
            break;

        /* ---------------- PRODUCTION REPORT ---------------- */
        case 'production':
            if (!hasPermission('reports.view')) jsonFail('Forbidden', 403);

            $from = trim((string)get('date_from', date('Y-m-01')));
            $to   = trim((string)get('date_to', date('Y-m-d')));

            $sql = 'SELECT po.id, po.order_no, po.quantity, po.produced_qty, po.start_date, po.expected_date,
                           po.actual_date, po.status, p.name AS product_name, p.sku, w.name AS warehouse_name,
                           (SELECT COALESCE(SUM(passed_qty),0) FROM qc_inspections WHERE production_order_id = po.id) AS passed,
                           (SELECT COALESCE(SUM(failed_qty),0) FROM qc_inspections WHERE production_order_id = po.id) AS failed
                    FROM production_orders po
                    JOIN products p ON p.id = po.product_id
                    JOIN warehouses w ON w.id = po.warehouse_id
                    WHERE po.deleted_at IS NULL AND (po.start_date BETWEEN ? AND ? OR po.start_date IS NULL)
                    ORDER BY po.start_date DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$from, $to]);
            $rows = $stmt->fetchAll();

            $totals = [
                'orders'    => count($rows),
                'ordered'   => array_sum(array_map(fn($r)=>(float)$r['quantity'], $rows)),
                'produced'  => array_sum(array_map(fn($r)=>(float)$r['produced_qty'], $rows)),
                'passed'    => array_sum(array_map(fn($r)=>(float)$r['passed'], $rows)),
                'failed'    => array_sum(array_map(fn($r)=>(float)$r['failed'], $rows)),
            ];

            jsonOk(['items'=>$rows, 'totals'=>$totals, 'from'=>$from, 'to'=>$to]);
            break;

        /* ---------------- STOCK MOVEMENT REPORT ---------------- */
        case 'stock_movement':
            if (!hasPermission('reports.view')) jsonFail('Forbidden', 403);

            $from = trim((string)get('date_from', date('Y-m-01')));
            $to   = trim((string)get('date_to', date('Y-m-d')));
            $productId = (int)get('product_id', 0);
            $warehouseId = (int)get('warehouse_id', 0);
            $type = trim((string)get('type', ''));

            $where = ['DATE(m.created_at) BETWEEN ? AND ?'];
            $params = [$from, $to];
            if ($productId > 0) { $where[] = 'm.product_id = ?'; $params[] = $productId; }
            if ($warehouseId > 0) { $where[] = 'm.warehouse_id = ?'; $params[] = $warehouseId; }
            if ($type !== '') { $where[] = 'm.movement_type = ?'; $params[] = $type; }

            $sql = 'SELECT m.*, p.name AS product_name, p.sku, p.unit, w.name AS warehouse_name,
                           u.name AS user_name
                    FROM stock_movements m
                    JOIN products p ON p.id = m.product_id
                    JOIN warehouses w ON w.id = m.warehouse_id
                    LEFT JOIN users u ON u.id = m.created_by
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY m.id DESC LIMIT 1000';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $in = 0; $out = 0;
            foreach ($rows as $r) {
                if ($r['direction'] === 'in') $in += (float)$r['quantity'];
                else $out += (float)$r['quantity'];
            }

            jsonOk(['items'=>$rows, 'totals'=>['in'=>$in, 'out'=>$out, 'net'=>$in-$out], 'from'=>$from, 'to'=>$to]);
            break;

        /* ---------------- PROFIT REPORT ---------------- */
        case 'profit':
            if (!hasPermission('reports.view')) jsonFail('Forbidden', 403);

            $from = trim((string)get('date_from', date('Y-m-01')));
            $to   = trim((string)get('date_to', date('Y-m-d')));

            // Revenue and cost by aggregating invoice items
            $sql = 'SELECT ii.product_id, p.name AS product_name, p.sku, p.unit,
                           SUM(ii.quantity) AS qty_sold,
                           SUM(ii.total) AS revenue,
                           SUM(ii.quantity * p.cost_price) AS cost,
                           SUM(ii.total - (ii.quantity * p.cost_price)) AS profit
                    FROM invoice_items ii
                    JOIN invoices i ON i.id = ii.invoice_id
                    JOIN products p ON p.id = ii.product_id
                    WHERE i.invoice_date BETWEEN ? AND ? AND i.status <> "cancelled"
                    GROUP BY ii.product_id
                    ORDER BY profit DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$from, $to]);
            $rows = $stmt->fetchAll();

            $totals = [
                'revenue' => array_sum(array_map(fn($r)=>(float)$r['revenue'], $rows)),
                'cost'    => array_sum(array_map(fn($r)=>(float)$r['cost'], $rows)),
                'profit'  => array_sum(array_map(fn($r)=>(float)$r['profit'], $rows)),
                'qty'     => array_sum(array_map(fn($r)=>(float)$r['qty_sold'], $rows)),
            ];
            $totals['margin'] = $totals['revenue'] > 0 ? round(($totals['profit'] / $totals['revenue']) * 100, 2) : 0;

            jsonOk(['items'=>$rows, 'totals'=>$totals, 'from'=>$from, 'to'=>$to]);
            break;

        /* ---------------- LOW STOCK REPORT ---------------- */
        case 'low_stock':
            if (!hasPermission('reports.view')) jsonFail('Forbidden', 403);

            $sql = 'SELECT p.id, p.sku, p.name, p.unit, p.reorder_level, p.cost_price,
                           c.name AS category_name,
                           COALESCE(SUM(s.quantity),0) AS total_qty
                    FROM products p
                    LEFT JOIN categories c ON c.id = p.category_id
                    LEFT JOIN stock s ON s.product_id = p.id
                    WHERE p.deleted_at IS NULL AND p.status = "active"
                    GROUP BY p.id
                    HAVING total_qty <= p.reorder_level
                    ORDER BY total_qty ASC';
            $rows = $pdo->query($sql)->fetchAll();
            jsonOk(['items'=>$rows]);
            break;

        default:
            jsonFail('Unknown report.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API REPORTS] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}