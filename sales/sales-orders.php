<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('sales.manage');

$pageTitle    = 'Sales Orders';
$pageSubtitle = 'Manage customer orders from draft to delivery';
$breadcrumbs  = ['Sales' => null, 'Sales Orders' => null];
$pageScripts  = [ASSETS_URL . '/js/sales.js'];

$pdo = db();
$today = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE DATE(invoice_date)=CURDATE() AND status<>'cancelled'")->fetchColumn();
$month = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE MONTH(invoice_date)=MONTH(CURDATE()) AND YEAR(invoice_date)=YEAR(CURDATE()) AND status<>'cancelled'")->fetchColumn();
$pending = (int)$pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status IN ('draft','pending','confirmed','processing') AND deleted_at IS NULL")->fetchColumn();
$unpaid = (float)$pdo->query("SELECT COALESCE(SUM(total - paid_amount),0) FROM invoices WHERE status IN ('unpaid','partial','overdue')")->fetchColumn();

$pageActions = '<a href="' . BASE_URL . '/sales/sales-order-create.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Order</a>';

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-success">
            <div class="gims-kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Today's Sales</span>
                <strong class="gims-kpi-value"><?= money($today) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Monthly Sales</span>
                <strong class="gims-kpi-value"><?= money($month) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-warning">
            <div class="gims-kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Pending Orders</span>
                <strong class="gims-kpi-value"><?= number_format($pending) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-danger">
            <div class="gims-kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Receivables</span>
                <strong class="gims-kpi-value"><?= money($unpaid) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" id="soSearch" class="form-control" placeholder="Order #, customer…">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select id="soStatus" class="form-select">
                    <option value="">All</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" id="soFrom" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" id="soTo" class="form-control">
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary btn-sm" id="soReset"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Sales Orders</h5>
            <small class="text-muted" id="soCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Warehouse</th>
                        <th>Order Date</th>
                        <th>Delivery Date</th>
                        <th class="text-end">Items</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="soBody">
                    <tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="soPagerWrap" style="display:none">
            <small class="text-muted" id="soPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="soPager"></ul></nav>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>