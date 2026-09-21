<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('qc.manage');

$pdo = db();
$id  = (int)get('id', 0);
$po_id = (int)get('production_order_id', 0);
$insp = null;
$defects = [];

if ($id > 0) {
    $s = $pdo->prepare('SELECT * FROM qc_inspections WHERE id = ?');
    $s->execute([$id]);
    $insp = $s->fetch();
    if (!$insp) { flash('danger', 'Inspection not found.'); redirect('quality-control/inspections.php'); }
    $d = $pdo->prepare('SELECT * FROM qc_defects WHERE inspection_id = ?');
    $d->execute([$id]);
    $defects = $d->fetchAll();
}

$pageTitle    = $id ? 'Edit QC Inspection' : 'New QC Inspection';
$pageSubtitle = $insp ? $insp['inspection_no'] : 'Record a quality control inspection';
$breadcrumbs  = ['Quality Control' => null,
                 'Inspections' => BASE_URL . '/quality-control/inspections.php',
                 $id ? 'Edit' : 'New' => null];

$products   = $pdo->query('SELECT id, name, sku FROM products WHERE deleted_at IS NULL ORDER BY name ASC')->fetchAll();
$employees  = $pdo->query('SELECT id, full_name FROM employees WHERE deleted_at IS NULL AND status = "active" ORDER BY full_name ASC')->fetchAll();
$pos = $pdo->query("SELECT id, order_no, product_id FROM production_orders WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 100")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<form id="qcForm" method="post" class="row g-3">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="col-lg-8">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Inspection Details</h5></div>
            <div class="gims-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Production Order</label>
                        <select name="production_order_id" id="qcPo" class="form-select">
                            <option value="">— None (standalone) —</option>
                            <?php foreach ($pos as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" data-product="<?= (int)$p['product_id'] ?>"
                                    <?= ($insp && (int)$insp['production_order_id'] === (int)$p['id']) || (!$insp && $po_id === (int)$p['id']) ? 'selected' : '' ?>>
                                    <?= e($p['order_no']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="qcProduct" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= $insp && (int)$insp['product_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Batch No</label>
                        <input type="text" name="batch_no" class="form-control" maxlength="60" value="<?= e($insp ? $insp['batch_no'] : '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Inspector</label>
                        <select name="inspector_id" class="form-select">
                            <option value="">— Select —</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?= (int)$e['id'] ?>" <?= $insp && (int)$insp['inspector_id'] === (int)$e['id'] ? 'selected' : '' ?>>
                                    <?= e($e['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Inspection Date <span class="text-danger">*</span></label>
                        <input type="date" name="inspection_date" class="form-control" required
                               value="<?= e($insp ? $insp['inspection_date'] : date('Y-m-d')) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Inspected Qty <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="inspected_qty" id="qcInspected" class="form-control" required
                               value="<?= e($insp ? $insp['inspected_qty'] : '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Passed Qty <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="passed_qty" id="qcPassed" class="form-control" required
                               value="<?= e($insp ? $insp['passed_qty'] : '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Failed Qty <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="failed_qty" id="qcFailed" class="form-control" required
                               value="<?= e($insp ? $insp['failed_qty'] : '') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" maxlength="500"><?= e($insp ? $insp['remarks'] : '') ?></textarea>
                    </div>

                    <?php if (!$insp): ?>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="apply_stock" id="applyStock" value="1" checked>
                                <label class="form-check-label" for="applyStock">
                                    <strong>Add passed quantity to stock</strong> (recommended for production inspections)
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Defects</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addDefect"><i class="bi bi-plus"></i> Add Defect</button>
            </div>
            <div class="gims-card-body p-0">
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:22%">Defect Type</th>
                                <th style="width:13%">Quantity</th>
                                <th style="width:15%">Severity</th>
                                <th style="width:45%">Description</th>
                                <th style="width:5%"></th>
                            </tr>
                        </thead>
                        <tbody id="defectItems"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Actions</h5></div>
            <div class="gims-card-body d-grid gap-2">
                <button type="submit" class="btn btn-primary gims-btn-lg">
                    <i class="bi bi-check2-circle me-1"></i> Save Inspection
                </button>
                <a href="<?= BASE_URL ?>/quality-control/inspections.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php
$defectsJson = json_encode(array_map(fn($d)=>[
    'defect_type'=>$d['defect_type'], 'quantity'=>(float)$d['quantity'],
    'severity'=>$d['severity'], 'description'=>$d['description']
], $defects));
?>
<script>
window.QC_DEFECTS = <?= $defectsJson ?>;
</script>
<?php $pageScripts = [ASSETS_URL . '/js/quality.js']; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>