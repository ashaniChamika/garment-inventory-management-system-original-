<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('qc.manage');

$pageTitle    = 'QC Inspections';
$pageSubtitle = 'Quality control inspections for all production';
$breadcrumbs  = ['Quality Control' => null, 'Inspections' => null];
$pageScripts  = [ASSETS_URL . '/js/quality.js'];

$pdo = db();

$pending = (int)$pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE status='pending'")->fetchColumn();
$passed  = (int)$pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE status='passed'")->fetchColumn();
$failed  = (int)$pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE status='failed'")->fetchColumn();
$totalI  = (float)$pdo->query("SELECT COALESCE(SUM(inspected_qty),0) FROM qc_inspections")->fetchColumn();
$totalP  = (float)$pdo->query("SELECT COALESCE(SUM(passed_qty),0) FROM qc_inspections")->fetchColumn();
$passRate = $totalI > 0 ? round(($totalP / $totalI) * 100, 1) : 0;

$pageActions = '<a href="' . BASE_URL . '/quality-control/inspection-create.php" class="btn btn-primary">'
             . '<i class="bi bi-plus-lg me-1"></i> New Inspection</a>';

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-warning">
            <div class="gims-kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Pending</span>
                <strong class="gims-kpi-value"><?= number_format($pending) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-success">
            <div class="gims-kpi-icon"><i class="bi bi-patch-check"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Passed</span>
                <strong class="gims-kpi-value"><?= number_format($passed) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-danger">
            <div class="gims-kpi-icon"><i class="bi bi-x-octagon"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Failed</span>
                <strong class="gims-kpi-value"><?= number_format($failed) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-graph-up"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Pass Rate</span>
                <strong class="gims-kpi-value"><?= $passRate ?>%</strong>
            </div>
        </div>
    </div>
</div>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" id="qcSearch" class="form-control" placeholder="Inspection #, product…">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select id="qcStatus" class="form-select">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="passed">Passed</option>
                    <option value="failed">Failed</option>
                    <option value="partially_passed">Partially Passed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" id="qcFrom" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" id="qcTo" class="form-control">
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary btn-sm" id="qcReset"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">All Inspections</h5>
            <small class="text-muted" id="qcCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Inspection #</th>
                        <th>Product</th>
                        <th>Batch</th>
                        <th class="text-end">Inspected</th>
                        <th class="text-end">Passed</th>
                        <th class="text-end">Failed</th>
                        <th class="text-end">Pass %</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="qcBody">
                    <tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="qcPagerWrap" style="display:none">
            <small class="text-muted" id="qcPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="qcPager"></ul></nav>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>