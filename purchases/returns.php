<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('purchase.manage');

$pageTitle    = 'Purchase Returns';
$pageSubtitle = 'Return defective or excess goods to suppliers';
$breadcrumbs  = ['Purchasing' => null, 'Purchase Returns' => null];
$pageScripts  = [ASSETS_URL . '/js/purchases.js'];

$pdo = db();
$suppliers  = $pdo->query('SELECT id, company_name FROM suppliers WHERE deleted_at IS NULL ORDER BY company_name ASC')->fetchAll();
$warehouses = $pdo->query('SELECT id, name, is_default FROM warehouses WHERE deleted_at IS NULL ORDER BY is_default DESC, name ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#retModal" onclick="openReturn()">
        <i class="bi bi-plus-lg me-1"></i> New Return
    </button>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Return Records</h5>
            <small class="text-muted" id="retCount">Loading…</small>
        </div>
        <select id="retStatus" class="form-select form-select-sm" style="width:160px">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="completed">Completed</option>
        </select>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Return #</th>
                        <th>Supplier</th>
                        <th>Warehouse</th>
                        <th>PO #</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="retBody">
                    <tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Return Modal -->
<div class="modal fade" id="retModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="retForm">
            <div class="modal-header">
                <h5 class="modal-title">New Purchase Return</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"><?= e($s['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?= (int)$w['id'] ?>" <?= $w['is_default'] ? 'selected' : '' ?>>
                                    <?= e($w['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Return Date <span class="text-danger">*</span></label>
                        <input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Related PO (optional)</label>
                        <select name="po_id" class="form-select">
                            <option value="">— None —</option>
                            <?php
                            $pos = $pdo->query("SELECT id, po_number FROM purchase_orders WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 100")->fetchAll();
                            foreach ($pos as $p):
                            ?>
                                <option value="<?= (int)$p['id'] ?>"><?= e($p['po_number']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" maxlength="400">
                    </div>
                </div>

                <label class="form-label">Items <span class="text-danger">*</span></label>
                <div class="table-responsive">
                    <table class="table gims-table">
                        <thead>
                            <tr>
                                <th style="width:50%">Product</th>
                                <th class="text-end" style="width:15%">Quantity</th>
                                <th class="text-end" style="width:20%">Unit Cost</th>
                                <th class="text-end" style="width:10%">Total</th>
                                <th style="width:5%"></th>
                            </tr>
                        </thead>
                        <tbody id="retItems"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addRetItem"><i class="bi bi-plus"></i> Add Item</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save Return</button>
            </div>
        </form>
    </div>
</div>

<script>
window.RET_PRODUCTS = <?= json_encode(array_map(fn($r)=>[
    'id'=>(int)$r['id'], 'name'=>$r['name'], 'sku'=>$r['sku'], 'cost_price'=>(float)$r['cost_price']
], db()->query('SELECT id, name, sku, cost_price FROM products WHERE deleted_at IS NULL AND status="active" ORDER BY name ASC')->fetchAll())) ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>