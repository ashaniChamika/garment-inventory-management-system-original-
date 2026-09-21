<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('production.manage');

$pdo = db();
$id  = (int)get('id', 0);
$po  = null;

if ($id > 0) {
    $s = $pdo->prepare('SELECT * FROM production_orders WHERE id = ? AND deleted_at IS NULL');
    $s->execute([$id]);
    $po = $s->fetch();
    if (!$po) { flash('danger', 'Production order not found.'); redirect('production/production-orders.php'); }
    if (!in_array($po['status'], ['planned', 'paused'], true)) {
        flash('warning', 'This order can no longer be edited.');
        redirect('production/production-order-view.php?id=' . $id);
    }
}

$pageTitle    = $id ? 'Edit Production Order' : 'New Production Order';
$pageSubtitle = $po ? $po['order_no'] : 'Plan a new production run';
$breadcrumbs  = ['Production' => null,
                 'Production Orders' => BASE_URL . '/production/production-orders.php',
                 $id ? 'Edit' : 'New' => null];

$products   = $pdo->query('SELECT id, name, sku, unit FROM products WHERE deleted_at IS NULL AND product_type = "finished_garment" AND status = "active" ORDER BY name ASC')->fetchAll();
$warehouses = $pdo->query('SELECT id, name, is_default FROM warehouses WHERE deleted_at IS NULL AND status = "active" ORDER BY is_default DESC, name ASC')->fetchAll();
$employees  = $pdo->query('SELECT id, full_name FROM employees WHERE deleted_at IS NULL AND status = "active" ORDER BY full_name ASC')->fetchAll();
$boms       = $pdo->query('SELECT id, name, product_id FROM bom WHERE deleted_at IS NULL AND status = "active" ORDER BY name ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<form id="prodOrderForm" class="row g-3">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="col-lg-8">
        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Order Details</h5></div>
            <div class="gims-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Finished Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="prodProduct" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= $po && (int)$po['product_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">BOM</label>
                        <select name="bom_id" id="prodBom" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($boms as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" data-product="<?= (int)$b['product_id'] ?>"
                                    <?= $po && (int)$po['bom_id'] === (int)$b['id'] ? 'selected' : '' ?>>
                                    <?= e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" step="1" min="1" name="quantity" class="form-control" required
                               value="<?= e($po ? $po['quantity'] : '100') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Size</label>
                        <input type="text" name="size" class="form-control" maxlength="30" value="<?= e($po ? $po['size'] : '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Color</label>
                        <input type="text" name="color" class="form-control" maxlength="50" value="<?= e($po ? $po['color'] : '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Output Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-select" required>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?= (int)$w['id'] ?>" <?= ($po && (int)$po['warehouse_id'] === (int)$w['id']) || (!$po && $w['is_default']) ? 'selected' : '' ?>>
                                    <?= e($w['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= e($po ? $po['start_date'] : date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expected Completion</label>
                        <input type="date" name="expected_date" class="form-control" value="<?= e($po ? $po['expected_date'] : '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Supervisor</label>
                        <select name="supervisor_id" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?= (int)$e['id'] ?>" <?= $po && (int)$po['supervisor_id'] === (int)$e['id'] ? 'selected' : '' ?>>
                                    <?= e($e['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="500"><?= e($po ? $po['notes'] : '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Quick Actions</h5></div>
            <div class="gims-card-body d-grid gap-2">
                <button type="submit" class="btn btn-primary gims-btn-lg">
                    <i class="bi bi-check2-circle me-1"></i> Save Order
                </button>
                <a href="<?= BASE_URL ?>/production/production-orders.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>

        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Material Preview</h5></div>
            <div class="gims-card-body" id="materialPreview">
                <p class="text-muted small mb-0">Select a product and BOM, then enter quantity to see required materials.</p>
            </div>
        </div>
    </div>
</form>

<script>
window.BOM_LIST = <?= json_encode(array_map(fn($b)=>['id'=>(int)$b['id'],'name'=>$b['name'],'product_id'=>(int)$b['product_id']], $boms)) ?>;
</script>
<?php $pageScripts = [ASSETS_URL . '/js/production.js']; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>