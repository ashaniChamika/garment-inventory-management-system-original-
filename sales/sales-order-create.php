<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('sales.manage');

$pdo = db();
$id  = (int)get('id', 0);
$so  = null;
$items = [];

if ($id > 0) {
    $s = $pdo->prepare('SELECT * FROM sales_orders WHERE id = ? AND deleted_at IS NULL');
    $s->execute([$id]);
    $so = $s->fetch();
    if (!$so) { flash('danger', 'Order not found.'); redirect('sales/sales-orders.php'); }
    if (!in_array($so['status'], ['draft', 'pending'], true)) {
        flash('warning', 'This order cannot be edited.');
        redirect('sales/sales-order-view.php?id=' . $id);
    }
    $i = $pdo->prepare('SELECT * FROM sales_order_items WHERE so_id = ?');
    $i->execute([$id]);
    $items = $i->fetchAll();
}

$pageTitle    = $id ? 'Edit Sales Order' : 'New Sales Order';
$pageSubtitle = $so ? $so['order_no'] : 'Create a new customer order';
$breadcrumbs  = ['Sales' => null, 'Sales Orders' => BASE_URL . '/sales/sales-orders.php', $id ? 'Edit' : 'New' => null];
$pageScripts  = [ASSETS_URL . '/js/sales.js'];

$customers  = $pdo->query('SELECT id, name, company FROM customers WHERE deleted_at IS NULL AND status = "active" ORDER BY name ASC')->fetchAll();
$warehouses = $pdo->query('SELECT id, name, is_default FROM warehouses WHERE deleted_at IS NULL AND status = "active" ORDER BY is_default DESC, name ASC')->fetchAll();
$products   = $pdo->query('SELECT id, name, sku, unit, selling_price FROM products WHERE deleted_at IS NULL AND status = "active" ORDER BY name ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<form id="soForm" class="row g-3">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="col-lg-8">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Order Details</h5></div>
            <div class="gims-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $so && (int)$so['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?><?= $c['company'] ? ' — ' . e($c['company']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?= (int)$w['id'] ?>" <?= ($so && (int)$so['warehouse_id'] === (int)$w['id']) || (!$so && $w['is_default']) ? 'selected' : '' ?>>
                                    <?= e($w['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Order Date <span class="text-danger">*</span></label>
                        <input type="date" name="order_date" class="form-control" required value="<?= e($so ? $so['order_date'] : date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Delivery Date</label>
                        <input type="date" name="delivery_date" class="form-control" value="<?= e($so ? $so['delivery_date'] : '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="500"><?= e($so ? $so['notes'] : '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Line Items</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addSoItem"><i class="bi bi-plus"></i> Add Item</button>
            </div>
            <div class="gims-card-body p-0">
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:36%">Product</th>
                                <th style="width:12%">Qty</th>
                                <th style="width:15%">Unit Price</th>
                                <th style="width:12%">Discount</th>
                                <th style="width:12%">Tax</th>
                                <th class="text-end" style="width:13%">Total</th>
                                <th style="width:5%"></th>
                            </tr>
                        </thead>
                        <tbody id="soItems"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Summary</h5></div>
            <div class="gims-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><strong id="soSubtotal">—</strong></div>
                <div class="mb-2">
                    <label class="form-label small">Discount</label>
                    <input type="number" step="0.01" min="0" name="discount" id="soDiscount" class="form-control form-control-sm" value="<?= e($so ? $so['discount'] : '0') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Tax</label>
                    <input type="number" step="0.01" min="0" name="tax" id="soTax" class="form-control form-control-sm" value="<?= e($so ? $so['tax'] : '0') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Shipping</label>
                    <input type="number" step="0.01" min="0" name="shipping" id="soShipping" class="form-control form-control-sm" value="<?= e($so ? $so['shipping'] : '0') ?>">
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <span class="fw-bold">Grand Total</span>
                    <strong class="text-primary fs-5" id="soGrandTotal">—</strong>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary gims-btn-lg"><i class="bi bi-check2-circle me-1"></i> Save as Draft</button>
                    <button type="button" class="btn btn-success" id="soSaveConfirm"><i class="bi bi-send me-1"></i> Save &amp; Confirm</button>
                    <a href="<?= BASE_URL ?>/sales/sales-orders.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
window.SO_EDIT_ID = <?= (int)$id ?>;
window.SO_ITEMS   = <?= json_encode(array_map(fn($it)=>[
    'product_id'=>(int)$it['product_id'],
    'variant_id'=>(int)$it['variant_id'],
    'quantity'=>(float)$it['quantity'],
    'unit_price'=>(float)$it['unit_price'],
    'discount'=>(float)$it['discount'],
    'tax'=>(float)$it['tax'],
], $items)) ?>;
window.SO_PRODUCTS = <?= json_encode(array_map(fn($r)=>[
    'id'=>(int)$r['id'], 'name'=>$r['name'], 'sku'=>$r['sku'],
    'unit'=>$r['unit'], 'price'=>(float)$r['selling_price']
], $products)) ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>