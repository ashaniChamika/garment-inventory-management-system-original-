<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('inventory.view');

$pageTitle    = 'Stock Levels';
$pageSubtitle = 'Current stock across all warehouses';
$breadcrumbs  = ['Inventory' => null, 'Stock' => null];
$pageScripts  = [ASSETS_URL . '/js/inventory.js'];

$pdo = db();
$warehouses = $pdo->query('SELECT id, name FROM warehouses WHERE deleted_at IS NULL ORDER BY is_default DESC, name ASC')->fetchAll();
$categories = $pdo->query('SELECT id, name FROM categories WHERE deleted_at IS NULL AND parent_id IS NULL ORDER BY name ASC')->fetchAll();

$pageActions = '';
if (hasPermission('inventory.manage')) {
    $pageActions .= '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjustModal"><i class="bi bi-sliders me-1"></i> Quick Adjust</button> ';
    $pageActions .= '<a href="' . BASE_URL . '/inventory/adjustments.php" class="btn btn-outline-secondary"><i class="bi bi-list-check me-1"></i> Adjustments</a>';
}

include __DIR__ . '/../includes/header.php';
?>

<!-- KPI row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-stack"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Total Stock Qty</span>
                <strong class="gims-kpi-value" id="kpiQty">—</strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-dark">
            <div class="gims-kpi-icon"><i class="bi bi-currency-exchange"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Inventory Value</span>
                <strong class="gims-kpi-value" id="kpiValue">—</strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-warning">
            <div class="gims-kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Low Stock</span>
                <strong class="gims-kpi-value" id="kpiLow">—</strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-danger">
            <div class="gims-kpi-icon"><i class="bi bi-x-octagon"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Out of Stock</span>
                <strong class="gims-kpi-value" id="kpiOut">—</strong>
            </div>
        </div>
    </div>
</div>

<!-- Filter panel -->
<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" id="sSearch" class="form-control" placeholder="Product, SKU…">
            </div>
            <div class="col-md-2">
                <label class="form-label">Warehouse</label>
                <select id="sWarehouse" class="form-select">
                    <option value="">All warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select id="sCategory" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Stock Filter</label>
                <select id="sFilter" class="form-select">
                    <option value="">All stock</option>
                    <option value="low">Low stock</option>
                    <option value="out">Out of stock</option>
                    <option value="ok">In stock</option>
                </select>
            </div>
            <div class="col-md-3 text-end">
                <button class="btn btn-outline-secondary btn-sm" id="sReset"><i class="bi bi-arrow-clockwise"></i> Reset</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Stock Ledger</h5>
            <small class="text-muted" id="sCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-end">On Hand</th>
                        <th class="text-end">Reserved</th>
                        <th class="text-end">Damaged</th>
                        <th class="text-end">Available</th>
                        <th class="text-end">Value</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="sBody">
                    <tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="sPagerWrap" style="display:none">
            <small class="text-muted" id="sPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="sPager"></ul></nav>
        </div>
    </div>
</div>

<!-- Quick adjust modal -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="adjustForm">
            <div class="modal-header">
                <h5 class="modal-title">Quick Stock Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Product <span class="text-danger">*</span></label>
                    <select name="product_id" id="adjProduct" class="form-select" required>
                        <option value="">Loading…</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                    <select name="warehouse_id" class="form-select" required>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Type</label>
                        <select name="adjustment_type" class="form-select">
                            <option value="increase">Increase (+)</option>
                            <option value="decrease">Decrease (−)</option>
                            <option value="damage">Damage (−)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" required>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label">Reason</label>
                    <input type="text" name="reason" class="form-control" maxlength="255">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
window.INVENTORY_CAN_MANAGE = <?= hasPermission('inventory.manage') ? 'true' : 'false' ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>