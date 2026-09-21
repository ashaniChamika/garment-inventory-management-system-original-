<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('reports.view');

$pageTitle    = 'Sales Report';
$pageSubtitle = 'Revenue, payments and outstanding balances';
$breadcrumbs  = ['Reports' => null, 'Sales' => null];
$pageScripts  = [ASSETS_URL . '/js/reports.js'];

$pdo = db();
$customers = $pdo->query('SELECT id, name FROM customers WHERE deleted_at IS NULL ORDER BY name')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" id="slFrom" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" id="slTo" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Customer</label>
                <select id="slCustomer" class="form-select">
                    <option value="">All customers</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 text-end">
                <button class="btn btn-primary" id="runSales"><i class="bi bi-play-fill me-1"></i> Run</button>
                <button class="btn btn-success" id="exportCsvSales"><i class="bi bi-filetype-csv me-1"></i> CSV</button>
                <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-receipt"></i></div>
            <div class="gims-kpi-body"><span class="gims-kpi-label">Invoices</span><strong class="gims-kpi-value" id="slKpiCount">—</strong></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-info">
            <div class="gims-kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="gims-kpi-body"><span class="gims-kpi-label">Total Sales</span><strong class="gims-kpi-value" id="slKpiTotal">—</strong></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-success">
            <div class="gims-kpi-icon"><i class="bi bi-check2-circle"></i></div>
            <div class="gims-kpi-body"><span class="gims-kpi-label">Paid</span><strong class="gims-kpi-value" id="slKpiPaid">—</strong></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-danger">
            <div class="gims-kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="gims-kpi-body"><span class="gims-kpi-label">Outstanding</span><strong class="gims-kpi-value" id="slKpiBal">—</strong></div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head"><h5 class="gims-card-title">Invoices</h5></div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr><th>Invoice</th><th>Date</th><th>Customer</th><th>SO</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th></tr>
                </thead>
                <tbody id="slBody">
                    <tr><td colspan="8"><div class="gims-empty"><i class="bi bi-bar-chart"></i><p>Click "Run" to load the report.</p></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>