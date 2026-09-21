<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('reports.view');

$pageTitle    = 'Profit Report';
$pageSubtitle = 'Revenue, cost and margin by product';
$breadcrumbs  = ['Reports' => null, 'Profit' => null];
$pageScripts  = [ASSETS_URL . '/js/reports.js'];

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" id="pfFrom" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" id="pfTo" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-primary" id="runProfit"><i class="bi bi-play-fill me-1"></i> Run</button>
                <button class="btn btn-success" id="exportCsvProfit"><i class="bi bi-filetype-csv me-1"></i> CSV</button>
                <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="gims-kpi gims-kpi-primary"><div class="gims-kpi-icon"><i class="bi bi-cash-coin"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Revenue</span><strong class="gims-kpi-value" id="pfKpiRev">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-info"><div class="gims-kpi-icon"><i class="bi bi-box"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Cost</span><strong class="gims-kpi-value" id="pfKpiCost">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-success"><div class="gims-kpi-icon"><i class="bi bi-graph-up-arrow"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Profit</span><strong class="gims-kpi-value" id="pfKpiProfit">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-dark"><div class="gims-kpi-icon"><i class="bi bi-percent"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Margin</span><strong class="gims-kpi-value" id="pfKpiMargin">—</strong></div></div></div>
</div>

<div class="gims-card">
    <div class="gims-card-head"><h5 class="gims-card-title">Profit by Product</h5></div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr><th>Product</th><th>SKU</th><th class="text-end">Qty Sold</th><th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Profit</th><th class="text-end">Margin</th></tr>
                </thead>
                <tbody id="pfBody">
                    <tr><td colspan="7"><div class="gims-empty"><i class="bi bi-graph-up"></i><p>Click "Run" to load the report.</p></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>