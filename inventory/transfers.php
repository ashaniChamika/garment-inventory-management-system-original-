<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('inventory.manage');

$pageTitle    = 'Stock Transfers';
$pageSubtitle = 'Move stock between warehouses';
$breadcrumbs  = ['Inventory' => null, 'Stock Transfers' => null];
$pageScripts  = [ASSETS_URL . '/js/inventory.js'];

$pdo = db();
$warehouses = $pdo->query('SELECT id, name FROM warehouses WHERE deleted_at IS NULL ORDER BY name ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#trfModal" onclick="openTransfer()">
        <i class="bi bi-plus-lg me-1"></i> New Transfer
    </button>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Transfer Records</h5>
            <small class="text-muted" id="tCount">Loading…</small>
        </div>
        <select id="tStatus" class="form-select form-select-sm" style="width:160px">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Date</th>
                        <th class="text-end">Items</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="tBody">
                    <tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Transfer Modal -->
<div class="modal fade" id="trfModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="trfForm">
            <div class="modal-header">
                <h5 class="modal-title">New Stock Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">From Warehouse <span class="text-danger">*</span></label>
                        <select name="from_warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">To Warehouse <span class="text-danger">*</span></label>
                        <select name="to_warehouse_id" class="form-select" required>
                            <?php $first = true; foreach ($warehouses as $w): ?>
                                <option value="<?= (int)$w['id'] ?>" <?= $first && count($warehouses) > 1 ? 'selected' : '' ?>>
                                    <?= e($w['name']) ?>
                                </option>
                            <?php $first = false; endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" maxlength="400">
                    </div>
                </div>

                <label class="form-label">Items <span class="text-danger">*</span></label>
                <div class="table-responsive">
                    <table class="table gims-table">
                        <thead>
                            <tr>
                                <th style="width:70%">Product</th>
                                <th class="text-end" style="width:20%">Quantity</th>
                                <th style="width:10%"></th>
                            </tr>
                        </thead>
                        <tbody id="trfItems"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addTrfItem"><i class="bi bi-plus"></i> Add Item</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save Transfer</button>
            </div>
        </form>
    </div>
</div>

<script>
window.TRANSFER_PRODUCTS = <?= json_encode(array_map(fn($r) => ['id'=>(int)$r['id'],'name'=>$r['name'],'unit'=>$r['unit']], db()->query('SELECT id, name, unit FROM products WHERE deleted_at IS NULL AND status="active" ORDER BY name ASC')->fetchAll())) ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>