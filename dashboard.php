<?php
require_once __DIR__ . '/config/config.php';
requirePermission('dashboard.view');

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Real-time overview of your garment operations';
$breadcrumbs  = ['Dashboard' => null];
$pageScripts  = [ASSETS_URL . '/js/dashboard.js'];

/* ---------------- Date range ---------------- */
$range = get('range', 'month');
$today = new DateTime();

switch ($range) {
    case 'today':
        $from = $today->format('Y-m-d');
        $to   = $today->format('Y-m-d');
        break;
    case 'week':
        $from = (clone $today)->modify('monday this week')->format('Y-m-d');
        $to   = $today->format('Y-m-d');
        break;
    case 'year':
        $from = (clone $today)->setDate((int)$today->format('Y'), 1, 1)->format('Y-m-d');
        $to   = $today->format('Y-m-d');
        break;
    case 'custom':
        $from = get('from', $today->format('Y-m-01'));
        $to   = get('to',   $today->format('Y-m-d'));
        break;
    case 'month':
    default:
        $from = $today->format('Y-m-01');
        $to   = $today->format('Y-m-d');
}

$from = date('Y-m-d', strtotime($from) ?: time());
$to   = date('Y-m-d', strtotime($to)   ?: time());

$pdo = db();

/* ---------------- Safe fetch helper ---------------- */
$fetchOne = function (string $sql, array $params = []) use ($pdo) {
    try { $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
};
$fetchAll = function (string $sql, array $params = []) use ($pdo) {
    try { $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
    catch (Throwable $e) { return []; }
};

/* ---------------- KPI cards ---------------- */
$kpi = [
    'total_products'    => (int)$fetchOne("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL"),
    'total_stock_qty'   => (float)$fetchOne("SELECT COALESCE(SUM(quantity),0) FROM stock"),
    'low_stock_items'   => (int)$fetchOne("SELECT COUNT(*) FROM (
                                SELECT p.id FROM products p
                                LEFT JOIN stock s ON s.product_id = p.id
                                WHERE p.deleted_at IS NULL AND p.status = 'active'
                                GROUP BY p.id, p.reorder_level
                                HAVING COALESCE(SUM(s.quantity),0) <= p.reorder_level AND COALESCE(SUM(s.quantity),0) > 0
                            ) AS t"),
    'out_of_stock'      => (int)$fetchOne("SELECT COUNT(*) FROM (
                                SELECT p.id FROM products p
                                LEFT JOIN stock s ON s.product_id = p.id
                                WHERE p.deleted_at IS NULL AND p.status = 'active'
                                GROUP BY p.id
                                HAVING COALESCE(SUM(s.quantity),0) <= 0
                            ) AS t"),
    'total_suppliers'   => (int)$fetchOne("SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL"),
    'total_customers'   => (int)$fetchOne("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL"),
    'pending_po'        => (int)$fetchOne("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('pending','approved','partially_received') AND deleted_at IS NULL"),
    'production_orders' => (int)$fetchOne("SELECT COUNT(*) FROM production_orders WHERE status IN ('planned','in_progress','paused') AND deleted_at IS NULL"),
    'completed_prod'    => (int)$fetchOne("SELECT COUNT(*) FROM production_orders WHERE status = 'completed' AND deleted_at IS NULL"),
    'pending_qc'        => (int)$fetchOne("SELECT COUNT(*) FROM qc_inspections WHERE status = 'pending'"),
    'rejected_items'    => (float)$fetchOne("SELECT COALESCE(SUM(rejected_qty),0) FROM production_outputs WHERE DATE(output_date) BETWEEN ? AND ?", [$from, $to]),
    'today_sales'       => (float)$fetchOne("SELECT COALESCE(SUM(total),0) FROM invoices WHERE DATE(invoice_date) = CURDATE() AND status <> 'cancelled'"),
    'month_sales'       => (float)$fetchOne("SELECT COALESCE(SUM(total),0) FROM invoices WHERE MONTH(invoice_date)=MONTH(CURDATE()) AND YEAR(invoice_date)=YEAR(CURDATE()) AND status <> 'cancelled'"),
    'purchase_value'    => (float)$fetchOne("SELECT COALESCE(SUM(total),0) FROM purchase_orders WHERE DATE(order_date) BETWEEN ? AND ? AND status <> 'cancelled'", [$from, $to]),
    'inventory_value'   => (float)$fetchOne("SELECT COALESCE(SUM(s.quantity * p.cost_price),0)
                                             FROM stock s JOIN products p ON p.id = s.product_id"),
];

/* ---------------- Charts ---------------- */

// Monthly sales last 12 months
$monthlySales = $fetchAll(
    "SELECT DATE_FORMAT(invoice_date,'%Y-%m') ym,
            DATE_FORMAT(invoice_date,'%b %Y') lbl,
            SUM(total) total
     FROM invoices
     WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
       AND status <> 'cancelled'
     GROUP BY ym, lbl
     ORDER BY ym ASC"
);

// Monthly purchases last 12 months
$monthlyPurchases = $fetchAll(
    "SELECT DATE_FORMAT(order_date,'%Y-%m') ym,
            DATE_FORMAT(order_date,'%b %Y') lbl,
            SUM(total) total
     FROM purchase_orders
     WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
       AND status <> 'cancelled'
     GROUP BY ym, lbl
     ORDER BY ym ASC"
);

// Stock movement last 14 days
$stockMovement = $fetchAll(
    "SELECT DATE(created_at) d,
            SUM(CASE WHEN direction='in' THEN quantity ELSE 0 END) qty_in,
            SUM(CASE WHEN direction='out' THEN quantity ELSE 0 END) qty_out
     FROM stock_movements
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     GROUP BY DATE(created_at)
     ORDER BY d ASC"
);

// Production performance
$productionStats = $fetchAll(
    "SELECT status, COUNT(*) c FROM production_orders WHERE deleted_at IS NULL GROUP BY status"
);

// Category distribution (stock value)
$categoryDist = $fetchAll(
    "SELECT c.name, COALESCE(SUM(s.quantity * p.cost_price),0) value
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.deleted_at IS NULL
     LEFT JOIN stock s    ON s.product_id = p.id
     WHERE c.parent_id IS NULL AND c.deleted_at IS NULL
     GROUP BY c.id, c.name
     ORDER BY value DESC
     LIMIT 8"
);

// Low stock top items
$lowStock = $fetchAll(
    "SELECT p.id, p.name, p.sku, p.reorder_level, p.unit, COALESCE(SUM(s.quantity),0) qty
     FROM products p
     LEFT JOIN stock s ON s.product_id = p.id
     WHERE p.deleted_at IS NULL AND p.status='active'
     GROUP BY p.id, p.name, p.sku, p.reorder_level, p.unit
     HAVING qty <= p.reorder_level
     ORDER BY qty ASC
     LIMIT 8"
);

// Recent purchases
$recentPurchases = $fetchAll(
    "SELECT po.id, po.po_number, po.total, po.status, po.order_date, s.company_name
     FROM purchase_orders po
     JOIN suppliers s ON s.id = po.supplier_id
     WHERE po.deleted_at IS NULL
     ORDER BY po.id DESC LIMIT 6"
);

// Recent sales
$recentSales = $fetchAll(
    "SELECT i.id, i.invoice_no, i.total, i.status, i.invoice_date, c.name customer
     FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     ORDER BY i.id DESC LIMIT 6"
);

// Recent stock movements
$recentMovements = $fetchAll(
    "SELECT m.id, m.movement_type, m.direction, m.quantity, m.created_at,
            p.name product, p.unit, w.name warehouse
     FROM stock_movements m
     JOIN products p ON p.id = m.product_id
     JOIN warehouses w ON w.id = m.warehouse_id
     ORDER BY m.id DESC LIMIT 6"
);

// Recent production orders
$recentProduction = $fetchAll(
    "SELECT po.id, po.order_no, po.quantity, po.status, po.start_date, po.expected_date,
            p.name product
     FROM production_orders po
     JOIN products p ON p.id = po.product_id
     WHERE po.deleted_at IS NULL
     ORDER BY po.id DESC LIMIT 6"
);

// Pending QC
$pendingQc = $fetchAll(
    "SELECT q.id, q.inspection_no, q.inspected_qty, q.inspection_date, q.status, p.name product
     FROM qc_inspections q
     JOIN products p ON p.id = q.product_id
     WHERE q.status = 'pending'
     ORDER BY q.id DESC LIMIT 6"
);

/* ---------------- JSON payload for charts ---------------- */
$charts = [
    'monthlySales' => [
        'labels' => array_column($monthlySales, 'lbl'),
        'data'   => array_map('floatval', array_column($monthlySales, 'total')),
    ],
    'monthlyPurchases' => [
        'labels' => array_column($monthlyPurchases, 'lbl'),
        'data'   => array_map('floatval', array_column($monthlyPurchases, 'total')),
    ],
    'stockMovement' => [
        'labels' => array_map(fn($r) => date('d M', strtotime($r['d'])), $stockMovement),
        'in'     => array_map('floatval', array_column($stockMovement, 'qty_in')),
        'out'    => array_map('floatval', array_column($stockMovement, 'qty_out')),
    ],
    'production' => [
        'labels' => array_map(fn($r) => ucwords(str_replace('_', ' ', $r['status'])), $productionStats),
        'data'   => array_map('intval', array_column($productionStats, 'c')),
    ],
    'categories' => [
        'labels' => array_column($categoryDist, 'name'),
        'data'   => array_map('floatval', array_column($categoryDist, 'value')),
    ],
    'lowStock' => [
        'labels' => array_map(fn($r) => mb_strimwidth($r['name'], 0, 24, '…'), $lowStock),
        'data'   => array_map('floatval', array_column($lowStock, 'qty')),
    ],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============= FILTER BAR ============= -->
<div class="gims-card mb-4">
    <div class="gims-card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex flex-wrap gap-1 gims-range-tabs">
            <?php
            $ranges = [
                'today'  => 'Today',
                'week'   => 'This Week',
                'month'  => 'This Month',
                'year'   => 'This Year',
            ];
            foreach ($ranges as $k => $lbl):
                $active = $range === $k ? 'active' : '';
            ?>
                <a class="gims-range-tab <?= $active ?>"
                   href="?range=<?= $k ?>"><?= e($lbl) ?></a>
            <?php endforeach; ?>
        </div>

        <form class="d-flex gap-2 align-items-end flex-wrap" method="get">
            <input type="hidden" name="range" value="custom">
            <div>
                <label class="form-label small text-muted mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
            </div>
            <div>
                <label class="form-label small text-muted mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
            </div>
            <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Apply</button>
        </form>
    </div>
</div>

<!-- ============= KPI GRID ============= -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Products',    number_format($kpi['total_products']),   'bi-box-seam',        'primary',  null],
        ['Total Stock Qty',   number_format($kpi['total_stock_qty'],2), 'bi-stack',           'info',     null],
        ['Low Stock Items',   number_format($kpi['low_stock_items']),   'bi-exclamation-triangle','warning', BASE_URL.'/inventory/stock.php?filter=low'],
        ['Out of Stock',      number_format($kpi['out_of_stock']),      'bi-x-octagon',       'danger',   BASE_URL.'/inventory/stock.php?filter=out'],
        ['Total Suppliers',   number_format($kpi['total_suppliers']),   'bi-people',          'secondary',BASE_URL.'/suppliers/index.php'],
        ['Total Customers',   number_format($kpi['total_customers']),   'bi-person-hearts',   'success',  BASE_URL.'/customers/index.php'],
        ['Pending POs',       number_format($kpi['pending_po']),        'bi-cart-check',      'warning',  BASE_URL.'/purchases/purchase-orders.php'],
        ['Production Orders', number_format($kpi['production_orders']), 'bi-gear-wide-connected','primary',BASE_URL.'/production/production-orders.php'],
        ['Completed Prod.',   number_format($kpi['completed_prod']),    'bi-check2-circle',   'success',  null],
        ['Pending QC',        number_format($kpi['pending_qc']),        'bi-patch-question',  'warning',  BASE_URL.'/quality-control/inspections.php'],
        ['Rejected Items',    number_format($kpi['rejected_items'],2),  'bi-bug',             'danger',   null],
        ["Today's Sales",     money($kpi['today_sales']),               'bi-cash-coin',       'success',  BASE_URL.'/sales/invoices.php'],
        ['Monthly Sales',     money($kpi['month_sales']),               'bi-graph-up-arrow',  'primary',  BASE_URL.'/reports/sales.php'],
        ['Purchase Value',    money($kpi['purchase_value']),            'bi-cart-plus',       'info',     BASE_URL.'/reports/purchases.php'],
        ['Inventory Value',   money($kpi['inventory_value']),           'bi-currency-exchange','dark',    BASE_URL.'/reports/inventory.php'],
    ];
    foreach ($cards as [$label, $value, $icon, $color, $link]):
        $tag = $link ? 'a' : 'div';
        $href = $link ? 'href="'.e($link).'"' : '';
    ?>
        <<?= $tag ?> <?= $href ?> class="col-6 col-md-4 col-xl-3 text-decoration-none">
            <div class="gims-kpi gims-kpi-<?= $color ?>">
                <div class="gims-kpi-icon"><i class="bi <?= $icon ?>"></i></div>
                <div class="gims-kpi-body">
                    <span class="gims-kpi-label"><?= e($label) ?></span>
                    <strong class="gims-kpi-value"><?= e($value) ?></strong>
                </div>
            </div>
        </<?= $tag ?>>
    <?php endforeach; ?>
</div>

<!-- ============= CHARTS ROW 1 ============= -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="gims-card h-100">
            <div class="gims-card-head">
                <div>
                    <h5 class="gims-card-title">Monthly Sales &amp; Purchases</h5>
                    <small class="text-muted">Last 12 months performance</small>
                </div>
                <span class="badge badge-soft-primary">Financial</span>
            </div>
            <div class="gims-card-body">
                <canvas id="salesChart" height="110"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="gims-card h-100">
            <div class="gims-card-head">
                <div>
                    <h5 class="gims-card-title">Production Status</h5>
                    <small class="text-muted">Order distribution</small>
                </div>
            </div>
            <div class="gims-card-body">
                <canvas id="productionChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ============= CHARTS ROW 2 ============= -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="gims-card h-100">
            <div class="gims-card-head">
                <div>
                    <h5 class="gims-card-title">Stock Movement</h5>
                    <small class="text-muted">Daily in / out over the last 14 days</small>
                </div>
            </div>
            <div class="gims-card-body">
                <canvas id="movementChart" height="110"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="gims-card h-100">
            <div class="gims-card-head">
                <div>
                    <h5 class="gims-card-title">Category Value</h5>
                    <small class="text-muted">Stock value by category</small>
                </div>
            </div>
            <div class="gims-card-body">
                <canvas id="categoryChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ============= LOW STOCK + PENDING QC ============= -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="gims-card h-100">
            <div class="gims-card-head">
                <div>
                    <h5 class="gims-card-title"><i class="bi bi-exclamation-triangle text-warning me-1"></i>Low Stock Alerts</h5>
                    <small class="text-muted">Items at or below reorder level</small>
                </div>
                <a href="<?= BASE_URL ?>/inventory/stock.php?filter=low" class="small">View all</a>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($lowStock)): ?>
                    <div class="gims-empty">
                        <i class="bi bi-check2-circle"></i>
                        <p>All products are sufficiently stocked.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">On Hand</th>
                                <th class="text-end">Reorder</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lowStock as $r): ?>
                            <tr>
                                <td>
                                    <div class="gims-cell-title"><?= e($r['name']) ?></div>
                                    <small class="text-muted"><?= e($r['sku']) ?></small>
                                </td>
                                <td class="text-end">
                                    <span class="fw-semibold text-danger"><?= qty($r['qty']) ?></span>
                                    <small class="text-muted"> <?= e($r['unit']) ?></small>
                                </td>
                                <td class="text-end text-muted"><?= qty($r['reorder_level']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="gims-card h-100">
            <div class="gims-card-head">
                <div>
                    <h5 class="gims-card-title"><i class="bi bi-patch-question text-warning me-1"></i>Pending QC Inspections</h5>
                    <small class="text-muted">Awaiting quality review</small>
                </div>
                <a href="<?= BASE_URL ?>/quality-control/inspections.php" class="small">View all</a>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($pendingQc)): ?>
                    <div class="gims-empty"><i class="bi bi-check2-circle"></i><p>No pending inspections.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr>
                                <th>Inspection</th>
                                <th>Product</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pendingQc as $r): ?>
                            <tr>
                                <td><span class="fw-semibold"><?= e($r['inspection_no']) ?></span></td>
                                <td><?= e($r['product']) ?></td>
                                <td class="text-end"><?= qty($r['inspected_qty']) ?></td>
                                <td class="text-end text-muted small"><?= fdate($r['inspection_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============= RECENT ACTIVITY ============= -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="gims-card h-100">
            <div class="gims-card-head">
                <div><h5 class="gims-card-title">Recent Purchases</h5></div>
                <a href="<?= BASE_URL ?>/purchases/purchase-orders.php" class="small">View all</a>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($recentPurchases)): ?>
                    <div class="gims-empty"><i class="bi bi-inbox"></i><p>No purchase orders yet.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead><tr><th>PO #</th><th>Supplier</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentPurchases as $r): ?>
                            <tr>
                                <td><a class="fw-semibold text-decoration-none" href="<?= BASE_URL ?>/purchases/purchase-orders.php?id=<?= (int)$r['id'] ?>"><?= e($r['po_number']) ?></a></td>
                                <td><?= e(mb_strimwidth($r['company_name'], 0, 24, '…')) ?></td>
                                <td class="text-end"><?= money($r['total']) ?></td>
                                <td><?= statusBadge($r['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="gims-card h-100">
            <div class="gims-card-head">
                <div><h5 class="gims-card-title">Recent Sales</h5></div>
                <a href="<?= BASE_URL ?>/sales/invoices.php" class="small">View all</a>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($recentSales)): ?>
                    <div class="gims-empty"><i class="bi bi-inbox"></i><p>No sales yet.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead><tr><th>Invoice</th><th>Customer</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentSales as $r): ?>
                            <tr>
                                <td><span class="fw-semibold"><?= e($r['invoice_no']) ?></span></td>
                                <td><?= e(mb_strimwidth($r['customer'], 0, 22, '…')) ?></td>
                                <td class="text-end"><?= money($r['total']) ?></td>
                                <td><?= statusBadge($r['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="gims-card h-100">
            <div class="gims-card-head">
                <div><h5 class="gims-card-title">Recent Stock Movements</h5></div>
                <a href="<?= BASE_URL ?>/reports/stock-movement.php" class="small">View all</a>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($recentMovements)): ?>
                    <div class="gims-empty"><i class="bi bi-arrow-left-right"></i><p>No movements yet.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead><tr><th>Product</th><th>Warehouse</th><th class="text-end">Qty</th><th>When</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentMovements as $r): ?>
                            <tr>
                                <td>
                                    <div class="gims-cell-title"><?= e(mb_strimwidth($r['product'],0,26,'…')) ?></div>
                                    <small class="text-muted"><?= e(ucwords(str_replace('_',' ',$r['movement_type']))) ?></small>
                                </td>
                                <td class="small text-muted"><?= e(mb_strimwidth($r['warehouse'],0,18,'…')) ?></td>
                                <td class="text-end">
                                    <span class="fw-semibold <?= $r['direction']==='in' ? 'text-success' : 'text-danger' ?>">
                                        <?= $r['direction']==='in' ? '+' : '−' ?><?= qty($r['quantity']) ?>
                                    </span>
                                    <small class="text-muted"> <?= e($r['unit']) ?></small>
                                </td>
                                <td class="small text-muted"><?= e(timeAgo($r['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="gims-card h-100">
            <div class="gims-card-head">
                <div><h5 class="gims-card-title">Recent Production Orders</h5></div>
                <a href="<?= BASE_URL ?>/production/production-orders.php" class="small">View all</a>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($recentProduction)): ?>
                    <div class="gims-empty"><i class="bi bi-gear"></i><p>No production orders yet.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead><tr><th>Order</th><th>Product</th><th class="text-end">Qty</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentProduction as $r): ?>
                            <tr>
                                <td><span class="fw-semibold"><?= e($r['order_no']) ?></span></td>
                                <td><?= e(mb_strimwidth($r['product'],0,22,'…')) ?></td>
                                <td class="text-end"><?= qty($r['quantity']) ?></td>
                                <td><?= statusBadge($r['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
window.GIMS_CHARTS = <?= json_encode($charts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.GIMS_CURRENCY = <?= json_encode(DEFAULT_CURRENCY) ?>;
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>