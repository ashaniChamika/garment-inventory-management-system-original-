<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('inventory.manage');

$pageTitle    = 'Stock Adjustments';
$pageSubtitle = 'Record physical count variance and manual corrections';
$breadcrumbs  = ['Inventory' => null, 'Stock Adjustments' => null];
$pageScripts  = [ASSETS_URL . '/js/inventory.js'];

$pdo = db();
$warehouses = $pdo->query('SELECT id, name FROM warehouses WHERE deleted_at IS NULL ORDER BY is_default DESC, name ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjModal" onclick="openAdjustment()">
        <i class="bi bi-plus-lg me-1"></i> New Adjustment
    </button>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Adjustment History</h5>
            <small class="text-muted" id="aCount">Loading…</small>
        </div>
        <select id="aStatus" class="form-select form-select-sm" style="width:160px">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Warehouse</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-end">Items</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="aBody">
                    <tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Adjustment Form Modal -->
<div class="modal fade" id="adjModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="adjForm">
            <div class="modal-header">
                <h5 class="modal-title">New Stock Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="adjId" value="">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" id="adjWarehouse" class="form-select" required>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="adjustment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <select name="adjustment_type" class="form-select">
                            <option value="recount">Physical Recount</option>
                            <option value="increase">Increase</option>
                            <option value="decrease">Decrease</option>
                            <option value="damage">Damage</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" maxlength="255">
                    </div>
                </div>

                <label class="form-label">Items <span class="text-danger">*</span></label>
                <div class="table-responsive">
                    <table class="table gims-table">
                        <thead>
                            <tr>
                                <th style="width:55%">Product</th>
                                <th class="text-end" style="width:20%">Counted Qty</th>
                                <th class="text-end" style="width:15%">System Qty</th>
                                <th style="width:10%"></th>
                            </tr>
                        </thead>
                        <tbody id="adjItems"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addAdjItem"><i class="bi bi-plus"></i> Add Item</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
window.ADJUSTMENT_PRODUCTS = <?= json_encode(array_map(fn($r) => ['id'=>(int)$r['id'],'name'=>$r['name'],'unit'=>$r['unit']], db()->query('SELECT id, name, unit FROM products WHERE deleted_at IS NULL AND status="active" ORDER BY name ASC')->fetchAll())) ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>