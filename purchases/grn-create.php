<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('purchase.manage');

$pdo = db();
$id   = (int)get('id', 0);
$poId = (int)get('po_id', 0);

$grn = null;
$items = [];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM goods_received WHERE id = ?');
    $stmt->execute([$id]);
    $grn = $stmt->fetch();
    if (!$grn) { flash('danger', 'GRN not found.'); redirect('purchases/goods-received.php'); }
    if ($grn['status'] === 'completed') {
        flash('warning', 'This GRN is completed and cannot be edited.');
        redirect('purchases/goods-received.php');
    }
    $poId = (int)$grn['po_id'];
    $i = $pdo->prepare('SELECT * FROM goods_received_items WHERE grn_id = ?');
    $i->execute([$id]);
    $items = $i->fetchAll();
} elseif ($poId > 0) {
    // Prefill from PO
    $p = $pdo->prepare('SELECT supplier_id, warehouse_id FROM purchase_orders WHERE id = ?');
    $p->execute([$poId]);
    $po = $p->fetch();
    if ($po) {
        $grn = ['supplier_id' => (int)$po['supplier_id'], 'warehouse_id' => (int)$po['warehouse_id']];
    }
}

$pageTitle    = 'Goods Received Note';
$pageSubtitle = $id ? $grn['grn_number'] : 'Create a new GRN';
$breadcrumbs  = ['Purchasing' => null,
                 'Goods Received' => BASE_URL . '/purchases/goods-received.php',
                 'New' => null];
$pageScripts  = [ASSETS_URL . '/js/purchases.js'];

$suppliers  = $pdo->query('SELECT id, company_name FROM suppliers WHERE deleted_at IS NULL ORDER BY company_name ASC')->fetchAll();
$warehouses = $pdo->query('SELECT id, name, is_default FROM warehouses WHERE deleted_at IS NULL AND status = "active" ORDER BY is_default DESC, name ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<form id="grnForm" class="row g-3">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="col-lg-8">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Receipt Details</h5></div>
            <div class="gims-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Purchase Order (optional)</label>
                        <select name="po_id" id="grnPoSelect" class="form-select">
                            <option value="">— No PO (direct receipt) —</option>
                            <?php
                            $pos = $pdo->query("SELECT id, po_number, supplier_id, warehouse_id FROM purchase_orders
                                                WHERE deleted_at IS NULL AND status IN ('approved','partially_received')
                                                ORDER BY id DESC LIMIT 100")->fetchAll();
                            foreach ($pos as $p):
                            ?>
                                <option value="<?= (int)$p['id'] ?>"
                                        data-supplier="<?= (int)$p['supplier_id'] ?>"
                                        data-warehouse="<?= (int)$p['warehouse_id'] ?>"
                                        <?= $poId === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['po_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="grnSupplier" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= $grn && (int)$grn['supplier_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?= (int)$w['id'] ?>" <?= ($grn && (int)$grn['warehouse_id'] === (int)$w['id']) || (!$grn && $w['is_default']) ? 'selected' : '' ?>>
                                    <?= e($w['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Received Date <span class="text-danger">*</span></label>
                        <input type="date" name="received_date" class="form-control" required value="<?= e($grn ? $grn['received_date'] : date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Invoice No</label>
                        <input type="text" name="invoice_no" class="form-control" maxlength="80" value="<?= e($grn ? $grn['invoice_no'] : '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="500"><?= e($grn ? $grn['notes'] : '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="gims-card mb-3">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Received Items</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addGrnItem"><i class="bi bi-plus"></i> Add Item</button>
            </div>
            <div class="gims-card-body p-0">
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:32%">Product</th>
                                <th style="width:12%">Qty</th>
                                <th style="width:12%">Rejected</th>
                                <th style="width:15%">Unit Cost</th>
                                <th style="width:14%">PO Item</th>
                                <th class="text-end" style="width:10%">Total</th>
                                <th style="width:5%"></th>
                            </tr>
                        </thead>
                        <tbody id="grnItems"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Summary</h5></div>
            <div class="gims-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Items</span><strong id="grnItemsCount">0</strong></div>
                <div class="d-flex justify-content-between mb-3"><span class="text-muted">Total Value</span><strong class="text-primary fs-5" id="grnTotal">—</strong></div>

                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Completing this GRN will <strong>add stock</strong> to the selected warehouse and update the PO.
                </div>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary" id="grnSaveDraft">
                        <i class="bi bi-save me-1"></i> Save as Draft
                    </button>
                    <button type="button" class="btn btn-success gims-btn-lg" id="grnSaveComplete">
                        <i class="bi bi-check2-circle me-1"></i> Save &amp; Complete
                    </button>
                    <a href="<?= BASE_URL ?>/purchases/goods-received.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
window.GRN_EDIT_ID = <?= (int)$id ?>;
window.GRN_PO_ID   = <?= (int)$poId ?>;
window.GRN_ITEMS   = <?= json_encode(array_map(function($it){
    return [
        'product_id'  => (int)$it['product_id'],
        'variant_id'  => (int)$it['variant_id'],
        'quantity'    => (float)$it['quantity'],
        'rejected_qty'=> (float)$it['rejected_qty'],
        'unit_cost'   => (float)$it['unit_cost'],
        'po_item_id'  => $it['po_item_id'] ? (int)$it['po_item_id'] : null,
    ];
}, $items)) ?>;
window.GRN_PRODUCTS = <?= json_encode(array_map(fn($r)=>[
    'id'=>(int)$r['id'], 'name'=>$r['name'], 'sku'=>$r['sku'], 'unit'=>$r['unit'], 'cost_price'=>(float)$r['cost_price']
], db()->query('SELECT id, name, sku, unit, cost_price FROM products WHERE deleted_at IS NULL AND status="active" ORDER BY name ASC')->fetchAll())) ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>