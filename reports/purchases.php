<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('reports.view');

$pageTitle    = 'Purchase Report';
$pageSubtitle = 'Purchase orders and supplier spend analysis';
$breadcrumbs  = ['Reports' => null, 'Purchases' => null];
$pageScripts  = [ASSETS_URL . '/js/reports.js'];

$pdo = db();
$suppliers = $pdo->query('SELECT id, company_name FROM suppliers WHERE deleted_at IS NULL ORDER BY company_name')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" id="puFrom" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" id="puTo" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Supplier</label>
                <select id="puSupplier" class="form-select">
                    <option value="">All suppliers</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= e($s['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 text-end">
                <button class="btn btn-primary" id="runPurchases"><i class="bi bi-play-fill me-1"></i> Run</button>
                <button class="btn btn-success" id="exportCsvPurchases"><i class="bi bi-filetype-csv me-1"></i> CSV</button>
                <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="gims-kpi gims-kpi-primary"><div class="gims-kpi-icon"><i class="bi bi-cart"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Orders</span><strong class="gims-kpi-value" id="puKpiCount">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-info"><div class="gims-kpi-icon"><i class="bi bi-currency-exchange"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Total</span><strong class="gims-kpi-value" id="puKpiTotal">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-success"><div class="gims-kpi-icon"><i class="bi bi-check2-circle"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Paid</span><strong class="gims-kpi-value" id="puKpiPaid">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-danger"><div class="gims-kpi-icon"><i class="bi bi-exclamation-triangle"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Payable</span><strong class="gims-kpi-value" id="puKpiBal">—</strong></div></div></div>
</div>

<div class="gims-card">
    <div class="gims-card-head"><h5 class="gims-card-title">Purchase Orders</h5></div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr><th>PO #</th><th>Date</th><th>Supplier</th><th>Warehouse</th><th class="text-end">Items</th><th class="text-end">Total</th><th class="text-end">Balance</th><th>Status</th></tr>
                </thead>
                <tbody id="puBody">
                    <tr><td colspan="8"><div class="gims-empty"><i class="bi bi-bar-chart"></i><p>Click "Run" to load the report.</p></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>