<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('purchase.manage');

$pdo = db();
$id  = (int)get('id', 0);

$po = null;
$items = [];
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM purchase_orders WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $po = $stmt->fetch();
    if (!$po) { flash('danger', 'Purchase order not found.'); redirect('purchases/purchase-orders.php'); }
    if (!in_array($po['status'], ['draft', 'pending'], true)) {
        flash('warning', 'This purchase order can no longer be edited.');
        redirect('purchases/purchase-order-view.php?id=' . $id);
    }
    $i = $pdo->prepare('SELECT * FROM purchase_order_items WHERE po_id = ?');
    $i->execute([$id]);
    $items = $i->fetchAll();
}

$pageTitle    = $id ? 'Edit Purchase Order' : 'New Purchase Order';
$pageSubtitle = $po ? $po['po_number'] : 'Create a new purchase order';
$breadcrumbs  = ['Purchasing' => null,
                 'Purchase Orders' => BASE_URL . '/purchases/purchase-orders.php',
                 $id ? 'Edit' : 'New' => null];
$pageScripts  = [ASSETS_URL . '/js/purchases.js'];

$suppliers  = $pdo->query('SELECT id, company_name, payment_terms FROM suppliers WHERE deleted_at IS NULL AND status = "active" ORDER BY company_name ASC')->fetchAll();
$warehouses = $pdo->query('SELECT id, name, is_default FROM warehouses WHERE deleted_at IS NULL AND status = "active" ORDER BY is_default DESC, name ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<form id="poForm" class="row g-3">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="col-lg-8">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Order Details</h5></div>
            <div class="gims-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="poSupplier" class="form-select" required>
                            <option value="">— Select supplier —</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= $po && (int)$po['supplier_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['company_name']) ?>
                                    <?php if (!empty($s['payment_terms'])): ?> — <?= e($s['payment_terms']) ?><?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?= (int)$w['id'] ?>" <?= ($po && (int)$po['warehouse_id'] === (int)$w['id']) || (!$po && $w['is_default']) ? 'selected' : '' ?>>
                                    <?= e($w['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Order Date <span class="text-danger">*</span></label>
                        <input type="date" name="order_date" class="form-control" required value="<?= e($po ? $po['order_date'] : date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expected Date</label>
                        <input type="date" name="expected_date" class="form-control" value="<?= e($po ? $po['expected_date'] : '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="500"><?= e($po ? $po['notes'] : '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="gims-card mb-3">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Line Items</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addPoItem"><i class="bi bi-plus"></i> Add Item</button>
            </div>
            <div class="gims-card-body p-0">
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:38%">Product</th>
                                <th style="width:13%">Qty</th>
                                <th style="width:15%">Unit Price</th>
                                <th style="width:13%">Discount</th>
                                <th style="width:13%">Tax</th>
                                <th class="text-end" style="width:14%">Total</th>
                                <th style="width:5%"></th>
                            </tr>
                        </thead>
                        <tbody id="poItems"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Summary</h5></div>
            <div class="gims-card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal</span>
                    <strong id="poSubtotal">—</strong>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Discount</label>
                    <input type="number" step="0.01" min="0" name="discount" id="poDiscount" class="form-control form-control-sm" value="<?= e($po ? $po['discount'] : '0') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Tax</label>
                    <input type="number" step="0.01" min="0" name="tax" id="poTax" class="form-control form-control-sm" value="<?= e($po ? $po['tax'] : '0') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Shipping</label>
                    <input type="number" step="0.01" min="0" name="shipping" id="poShipping" class="form-control form-control-sm" value="<?= e($po ? $po['shipping'] : '0') ?>">
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <span class="fw-bold">Grand Total</span>
                    <strong class="text-primary fs-5" id="poGrandTotal">—</strong>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary gims-btn-lg">
                        <i class="bi bi-check2-circle me-1"></i> Save as Draft
                    </button>
                    <button type="button" class="btn btn-success" id="poSaveAndSubmit">
                        <i class="bi bi-send me-1"></i> Save &amp; Submit
                    </button>
                    <a href="<?= BASE_URL ?>/purchases/purchase-orders.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
window.PO_EDIT_ID = <?= (int)$id ?>;
window.PO_ITEMS   = <?= json_encode(array_map(function($it){
    return [
        'product_id' => (int)$it['product_id'],
        'variant_id' => (int)$it['variant_id'],
        'quantity'   => (float)$it['quantity'],
        'unit_price' => (float)$it['unit_price'],
        'discount'   => (float)$it['discount'],
        'tax'        => (float)$it['tax'],
    ];
}, $items)) ?>;
window.PO_PRODUCTS = <?= json_encode(array_map(fn($r)=>[
    'id'=>(int)$r['id'], 'name'=>$r['name'], 'sku'=>$r['sku'], 'unit'=>$r['unit'],
    'cost_price'=>(float)$r['cost_price']
], db()->query('SELECT id, name, sku, unit, cost_price FROM products WHERE deleted_at IS NULL AND status="active" ORDER BY name ASC')->fetchAll())) ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>