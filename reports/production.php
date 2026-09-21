<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('reports.view');

$pageTitle    = 'Production Report';
$pageSubtitle = 'Production output, efficiency and QC performance';
$breadcrumbs  = ['Reports' => null, 'Production' => null];
$pageScripts  = [ASSETS_URL . '/js/reports.js'];

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" id="prFrom" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" id="prTo" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-primary" id="runProduction"><i class="bi bi-play-fill me-1"></i> Run</button>
                <button class="btn btn-success" id="exportCsvProduction"><i class="bi bi-filetype-csv me-1"></i> CSV</button>
                <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="gims-kpi gims-kpi-primary"><div class="gims-kpi-icon"><i class="bi bi-gear"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Orders</span><strong class="gims-kpi-value" id="prKpiOrders">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-info"><div class="gims-kpi-icon"><i class="bi bi-box"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Ordered</span><strong class="gims-kpi-value" id="prKpiOrdered">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-success"><div class="gims-kpi-icon"><i class="bi bi-check2-circle"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Produced</span><strong class="gims-kpi-value" id="prKpiProduced">—</strong></div></div></div>
    <div class="col-md-3"><div class="gims-kpi gims-kpi-danger"><div class="gims-kpi-icon"><i class="bi bi-x-octagon"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Rejected</span><strong class="gims-kpi-value" id="prKpiFailed">—</strong></div></div></div>
</div>

<div class="gims-card">
    <div class="gims-card-head"><h5 class="gims-card-title">Production Orders</h5></div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr><th>Order #</th><th>Product</th><th>Start</th><th>Expected</th><th class="text-end">Ordered</th><th class="text-end">Produced</th><th class="text-end">Passed</th><th class="text-end">Failed</th><th>Status</th></tr>
                </thead>
                <tbody id="prBody">
                    <tr><td colspan="9"><div class="gims-empty"><i class="bi bi-bar-chart"></i><p>Click "Run" to load the report.</p></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>