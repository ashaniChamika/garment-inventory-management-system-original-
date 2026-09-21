<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('reports.view');

$pageTitle    = 'Stock Movement Report';
$pageSubtitle = 'Every in/out movement across warehouses';
$breadcrumbs  = ['Reports' => null, 'Stock Movement' => null];
$pageScripts  = [ASSETS_URL . '/js/reports.js'];

$pdo = db();
$warehouses = $pdo->query('SELECT id, name FROM warehouses WHERE deleted_at IS NULL ORDER BY name')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" id="mvFrom" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" id="mvTo" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Warehouse</label>
                <select id="mvWarehouse" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select id="mvType" class="form-select">
                    <option value="">All</option>
                    <option value="purchase_in">Purchase In</option>
                    <option value="sale_out">Sale Out</option>
                    <option value="production_in">Production In</option>
                    <option value="production_out">Production Out</option>
                    <option value="adjustment_in">Adj +</option>
                    <option value="adjustment_out">Adj −</option>
                    <option value="transfer_in">Transfer In</option>
                    <option value="transfer_out">Transfer Out</option>
                    <option value="return_in">Return In</option>
                    <option value="return_out">Return Out</option>
                    <option value="damage_out">Damage</option>
                </select>
            </div>
            <div class="col-md-3 text-end">
                <button class="btn btn-primary" id="runMovement"><i class="bi bi-play-fill me-1"></i> Run</button>
                <button class="btn btn-success" id="exportCsvMovement"><i class="bi bi-filetype-csv me-1"></i> CSV</button>
                <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="gims-kpi gims-kpi-success"><div class="gims-kpi-icon"><i class="bi bi-arrow-down-circle"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Total In</span><strong class="gims-kpi-value" id="mvKpiIn">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-danger"><div class="gims-kpi-icon"><i class="bi bi-arrow-up-circle"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Total Out</span><strong class="gims-kpi-value" id="mvKpiOut">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-primary"><div class="gims-kpi-icon"><i class="bi bi-activity"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Net</span><strong class="gims-kpi-value" id="mvKpiNet">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-dark"><div class="gims-kpi-icon"><i class="bi bi-list-ol"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Movements</span><strong class="gims-kpi-value" id="mvKpiCount">—</strong></div></div></div>
</div>

<div class="gims-card">
    <div class="gims-card-head"><h5 class="gims-card-title">Movement History</h5></div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr><th>Date</th><th>Product</th><th>Warehouse</th><th>Type</th><th>Reference</th><th class="text-end">Qty</th><th class="text-end">Balance</th><th>By</th></tr>
                </thead>
                <tbody id="mvBody">
                    <tr><td colspan="8"><div class="gims-empty"><i class="bi bi-arrow-left-right"></i><p>Click "Run" to load the report.</p></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>