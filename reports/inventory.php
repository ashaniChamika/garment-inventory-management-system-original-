<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('reports.view');

$pageTitle    = 'Inventory Report';
$pageSubtitle = 'Full stock valuation and availability';
$breadcrumbs  = ['Reports' => null, 'Inventory' => null];
$pageScripts  = [ASSETS_URL . '/js/reports.js'];

$pdo = db();
$categories = $pdo->query('SELECT id, name FROM categories WHERE deleted_at IS NULL AND parent_id IS NULL ORDER BY name')->fetchAll();
$warehouses = $pdo->query('SELECT id, name FROM warehouses WHERE deleted_at IS NULL ORDER BY name')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select id="rptCategory" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Warehouse</label>
                <select id="rptWarehouse" class="form-select">
                    <option value="">All warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Stock</label>
                <select id="rptFilter" class="form-select">
                    <option value="">All</option>
                    <option value="low">Low only</option>
                    <option value="out">Out of stock</option>
                    <option value="ok">In stock</option>
                </select>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-primary" id="runInventory"><i class="bi bi-play-fill me-1"></i> Run</button>
                <button class="btn btn-success" id="exportCsvInv"><i class="bi bi-filetype-csv me-1"></i> CSV</button>
                <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4" id="invKpiRow">
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-boxes"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Items</span>
                <strong class="gims-kpi-value" id="invKpiCount">—</strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-info">
            <div class="gims-kpi-icon"><i class="bi bi-stack"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Total Qty</span>
                <strong class="gims-kpi-value" id="invKpiQty">—</strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-dark">
            <div class="gims-kpi-icon"><i class="bi bi-currency-exchange"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Cost Value</span>
                <strong class="gims-kpi-value" id="invKpiCost">—</strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-success">
            <div class="gims-kpi-icon"><i class="bi bi-tag"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Retail Value</span>
                <strong class="gims-kpi-value" id="invKpiRetail">—</strong>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div><h5 class="gims-card-title">Inventory Detail</h5></div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-end">On Hand</th>
                        <th class="text-end">Reorder</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="invBody">
                    <tr><td colspan="8"><div class="gims-empty"><i class="bi bi-bar-chart"></i><p>Click "Run" to load the report.</p></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>