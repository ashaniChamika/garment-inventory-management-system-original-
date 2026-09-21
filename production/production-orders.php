<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('production.manage');

$pageTitle    = 'Production Orders';
$pageSubtitle = 'Manufacturing orders from planning to completion';
$breadcrumbs  = ['Production' => null, 'Production Orders' => null];
$pageScripts  = [ASSETS_URL . '/js/production.js'];

$pageActions = '<a href="' . BASE_URL . '/production/production-order-create.php" class="btn btn-primary">'
             . '<i class="bi bi-plus-lg me-1"></i> New Production Order</a>';

$pdo = db();

$planned    = (int)$pdo->query("SELECT COUNT(*) FROM production_orders WHERE status='planned' AND deleted_at IS NULL")->fetchColumn();
$inProgress = (int)$pdo->query("SELECT COUNT(*) FROM production_orders WHERE status='in_progress' AND deleted_at IS NULL")->fetchColumn();
$completed  = (int)$pdo->query("SELECT COUNT(*) FROM production_orders WHERE status='completed' AND deleted_at IS NULL")->fetchColumn();
$totalOutput= (float)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM production_outputs")->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-secondary">
            <div class="gims-kpi-icon"><i class="bi bi-calendar3"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Planned</span>
                <strong class="gims-kpi-value"><?= number_format($planned) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-gear-wide-connected"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">In Progress</span>
                <strong class="gims-kpi-value"><?= number_format($inProgress) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-success">
            <div class="gims-kpi-icon"><i class="bi bi-check2-circle"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Completed</span>
                <strong class="gims-kpi-value"><?= number_format($completed) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-dark">
            <div class="gims-kpi-icon"><i class="bi bi-box-seam"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Total Output</span>
                <strong class="gims-kpi-value"><?= qty($totalOutput) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" id="prodSearch" class="form-control" placeholder="Order #, product…">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select id="prodStatus" class="form-select">
                    <option value="">All</option>
                    <option value="planned">Planned</option>
                    <option value="in_progress">In Progress</option>
                    <option value="paused">Paused</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" id="prodFrom" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" id="prodTo" class="form-control">
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary btn-sm" id="prodReset"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Production Orders</h5>
            <small class="text-muted" id="prodCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Progress</th>
                        <th>Warehouse</th>
                        <th>Start</th>
                        <th>Expected</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="prodBody">
                    <tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="prodPagerWrap" style="display:none">
            <small class="text-muted" id="prodPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="prodPager"></ul></nav>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>