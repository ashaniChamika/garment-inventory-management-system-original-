<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('inventory.view');

$pageTitle    = 'Stock Movements';
$pageSubtitle = 'Complete audit trail of all stock in/out transactions';
$breadcrumbs  = ['Inventory' => null, 'Stock Movements' => null];
$pageScripts  = [ASSETS_URL . '/js/inventory.js'];

$pdo = db();
$warehouses = $pdo->query('SELECT id, name FROM warehouses WHERE deleted_at IS NULL ORDER BY name ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Product</label>
                <select id="mProduct" class="form-select"><option value="">All products</option></select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Warehouse</label>
                <select id="mWarehouse" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select id="mType" class="form-select">
                    <option value="">All types</option>
                    <option value="purchase_in">Purchase In</option>
                    <option value="sale_out">Sale Out</option>
                    <option value="production_in">Production In</option>
                    <option value="production_out">Production Out</option>
                    <option value="adjustment_in">Adjustment +</option>
                    <option value="adjustment_out">Adjustment −</option>
                    <option value="transfer_in">Transfer In</option>
                    <option value="transfer_out">Transfer Out</option>
                    <option value="return_in">Return In</option>
                    <option value="damage_out">Damage</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" id="mFrom" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" id="mTo" class="form-control">
            </div>
            <div class="col-md-1 text-end">
                <button class="btn btn-outline-secondary btn-sm" id="mReset"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Movement History</h5>
            <small class="text-muted" id="mCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Warehouse</th>
                        <th>Type</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Balance</th>
                        <th>Reference</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody id="mBody">
                    <tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="mPagerWrap" style="display:none">
            <small class="text-muted" id="mPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="mPager"></ul></nav>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>