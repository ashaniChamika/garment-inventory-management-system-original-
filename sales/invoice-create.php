<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('sales.manage');

$pdo  = db();
$id   = (int)get('id', 0);
$soId = (int)get('so_id', 0);

$inv = null;
$items = [];

if ($id > 0) {
    $s = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $s->execute([$id]);
    $inv = $s->fetch();
    if (!$inv) { flash('danger', 'Invoice not found.'); redirect('sales/invoices.php'); }
    $soId = (int)$inv['so_id'];
    $i = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = ?');
    $i->execute([$id]);
    $items = $i->fetchAll();
} elseif ($soId > 0) {
    // Prefill from SO
    $s = $pdo->prepare('SELECT * FROM sales_orders WHERE id = ?');
    $s->execute([$soId]);
    $soRow = $s->fetch();
    if ($soRow) {
        $inv = [
            'so_id' => $soRow['id'],
            'customer_id' => $soRow['customer_id'],
            'subtotal' => $soRow['subtotal'],
            'discount' => $soRow['discount'],
            'tax' => $soRow['tax'],
        ];
        $i = $pdo->prepare('SELECT * FROM sales_order_items WHERE so_id = ?');
        $i->execute([$soId]);
        $items = $i->fetchAll();
    }
}

$pageTitle    = $id ? 'Edit Invoice' : 'New Invoice';
$pageSubtitle = $inv && isset($inv['invoice_no']) ? $inv['invoice_no'] : 'Create a new customer invoice';
$breadcrumbs  = ['Sales' => null, 'Invoices' => BASE_URL . '/sales/invoices.php', $id ? 'Edit' : 'New' => null];
$pageScripts  = [ASSETS_URL . '/js/sales.js'];

$customers = $pdo->query('SELECT id, name, company FROM customers WHERE deleted_at IS NULL AND status = "active" ORDER BY name ASC')->fetchAll();
$products  = $pdo->query('SELECT id, name, sku, unit, selling_price FROM products WHERE deleted_at IS NULL AND status = "active" ORDER BY name ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<form id="invForm" class="row g-3">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="so_id" value="<?= (int)$soId ?>">

    <div class="col-lg-8">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Invoice Details</h5></div>
            <div class="gims-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $inv && (int)$inv['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?><?= $c['company'] ? ' — ' . e($c['company']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                        <input type="date" name="invoice_date" class="form-control" required
                               value="<?= e($inv && isset($inv['invoice_date']) ? $inv['invoice_date'] : date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control"
                               value="<?= e($inv && isset($inv['due_date']) ? $inv['due_date'] : date('Y-m-d', strtotime('+30 days'))) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="500"><?= e($inv && isset($inv['notes']) ? $inv['notes'] : '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Line Items</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addInvItem"><i class="bi bi-plus"></i> Add Item</button>
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
                        <tbody id="invItems"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Summary</h5></div>
            <div class="gims-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><strong id="invSubtotal">—</strong></div>
                <div class="mb-2">
                    <label class="form-label small">Discount</label>
                    <input type="number" step="0.01" min="0" name="discount" id="invDiscount" class="form-control form-control-sm" value="<?= e($inv && isset($inv['discount']) ? $inv['discount'] : '0') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Tax</label>
                    <input type="number" step="0.01" min="0" name="tax" id="invTax" class="form-control form-control-sm" value="<?= e($inv && isset($inv['tax']) ? $inv['tax'] : '0') ?>">
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <span class="fw-bold">Grand Total</span>
                    <strong class="text-primary fs-5" id="invGrandTotal">—</strong>
                </div>

                <?php if (!$id): ?>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="deduct_stock" id="deductStock" value="1" checked>
                    <label class="form-check-label small" for="deductStock"><strong>Deduct stock</strong> when saving</label>
                </div>
                <?php endif; ?>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary gims-btn-lg"><i class="bi bi-check2-circle me-1"></i> Save Invoice</button>
                    <a href="<?= BASE_URL ?>/sales/invoices.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
window.INV_EDIT_ID = <?= (int)$id ?>;
window.INV_ITEMS   = <?= json_encode(array_map(fn($it)=>[
    'product_id'=>(int)$it['product_id'],
    'variant_id'=>(int)($it['variant_id'] ?? 0),
    'quantity'=>(float)$it['quantity'],
    'unit_price'=>(float)$it['unit_price'],
    'discount'=>(float)$it['discount'],
    'tax'=>(float)$it['tax'],
], $items)) ?>;
window.INV_PRODUCTS = <?= json_encode(array_map(fn($r)=>[
    'id'=>(int)$r['id'], 'name'=>$r['name'], 'sku'=>$r['sku'],
    'unit'=>$r['unit'], 'price'=>(float)$r['selling_price']
], $products)) ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>