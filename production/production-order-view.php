<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('production.manage');

$pdo = db();
$id  = (int)get('id', 0);
if ($id <= 0) { flash('danger', 'Invalid order.'); redirect('production/production-orders.php'); }

$stmt = $pdo->prepare(
    'SELECT po.*, p.name AS product_name, p.sku, p.unit,
            w.name AS warehouse_name, e.full_name AS supervisor_name, b.name AS bom_name
     FROM production_orders po
     JOIN products p ON p.id = po.product_id
     JOIN warehouses w ON w.id = po.warehouse_id
     LEFT JOIN employees e ON e.id = po.supervisor_id
     LEFT JOIN bom b ON b.id = po.bom_id
     WHERE po.id = ?'
);
$stmt->execute([$id]);
$po = $stmt->fetch();
if (!$po) { flash('danger', 'Not found.'); redirect('production/production-orders.php'); }

$materials = $pdo->prepare(
    'SELECT pm.*, p.name AS material_name, p.sku,
            COALESCE((SELECT SUM(quantity) FROM stock WHERE product_id = pm.material_id), 0) AS stock_qty
     FROM production_materials pm
     JOIN products p ON p.id = pm.material_id
     WHERE pm.production_order_id = ?
     ORDER BY pm.id'
);
$materials->execute([$id]);
$materials = $materials->fetchAll();

$outputs = $pdo->prepare('SELECT * FROM production_outputs WHERE production_order_id = ? ORDER BY id');
$outputs->execute([$id]);
$outputs = $outputs->fetchAll();

$qc = $pdo->prepare('SELECT id, inspection_no, inspected_qty, passed_qty, failed_qty, status, inspection_date FROM qc_inspections WHERE production_order_id = ? ORDER BY id DESC');
$qc->execute([$id]);
$qc = $qc->fetchAll();

$pageTitle    = 'Production Order ' . $po['order_no'];
$pageSubtitle = $po['product_name'];
$breadcrumbs  = ['Production' => null,
                 'Production Orders' => BASE_URL . '/production/production-orders.php',
                 'View' => null];

$pageActions = '';
if (in_array($po['status'], ['planned', 'paused'], true)) {
    $pageActions .= '<a href="' . BASE_URL . '/production/production-order-create.php?id=' . $id . '" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Edit</a> ';
}
if ($po['status'] === 'planned') {
    $pageActions .= '<button class="btn btn-success status-btn" data-id="' . $id . '" data-status="in_progress"><i class="bi bi-play-fill me-1"></i> Start</button> ';
}
if ($po['status'] === 'in_progress') {
    $pageActions .= '<button class="btn btn-warning status-btn" data-id="' . $id . '" data-status="paused"><i class="bi bi-pause-fill me-1"></i> Pause</button> ';
    $pageActions .= '<button class="btn btn-success status-btn" data-id="' . $id . '" data-status="completed"><i class="bi bi-check2 me-1"></i> Complete</button> ';
}
$pageActions .= '<button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>';

$pageScripts = [ASSETS_URL . '/js/production.js'];

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-box"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Order Qty</span>
                <strong class="gims-kpi-value"><?= qty($po['quantity']) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-info">
            <div class="gims-kpi-icon"><i class="bi bi-gear"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Produced</span>
                <strong class="gims-kpi-value"><?= qty($po['produced_qty']) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-success">
            <div class="gims-kpi-icon"><i class="bi bi-patch-check"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">QC Passed</span>
                <strong class="gims-kpi-value"><?= qty(array_sum(array_map(fn($r)=>(float)$r['passed_qty'], $qc))) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi <?= $po['status'] === 'completed' ? 'gims-kpi-success' : 'gims-kpi-warning' ?>">
            <div class="gims-kpi-icon"><i class="bi bi-info-circle"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Status</span>
                <strong class="gims-kpi-value"><?= statusBadge($po['status']) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Order Information</h5></div>
            <div class="gims-card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table gims-table mb-0">
                            <tr><td class="text-muted small" style="width:40%">Order #</td><td class="fw-semibold"><?= e($po['order_no']) ?></td></tr>
                            <tr><td class="text-muted small">Product</td><td><?= e($po['product_name']) ?></td></tr>
                            <tr><td class="text-muted small">SKU</td><td><?= e($po['sku']) ?></td></tr>
                            <tr><td class="text-muted small">Size</td><td><?= e($po['size'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Color</td><td><?= e($po['color'] ?: '—') ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table gims-table mb-0">
                            <tr><td class="text-muted small" style="width:40%">Warehouse</td><td><?= e($po['warehouse_name']) ?></td></tr>
                            <tr><td class="text-muted small">BOM</td><td><?= e($po['bom_name'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Supervisor</td><td><?= e($po['supervisor_name'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Start Date</td><td><?= fdate($po['start_date']) ?></td></tr>
                            <tr><td class="text-muted small">Expected</td><td><?= fdate($po['expected_date']) ?></td></tr>
                        </table>
                    </div>
                </div>
                <?php if ($po['notes']): ?>
                    <div class="mt-3 p-3 bg-light rounded small"><?= nl2br(e($po['notes'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="gims-card mb-3">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Materials</h5>
                <?php if ($po['status'] === 'in_progress' && hasPermission('production.manage')): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#issueModal"><i class="bi bi-arrow-up-circle me-1"></i> Issue Material</button>
                <?php endif; ?>
            </div>
            <div class="gims-card-body p-0">
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th class="text-end">Required</th>
                                <th class="text-end">Issued</th>
                                <th class="text-end">Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($materials)): ?>
                            <tr><td colspan="5"><div class="gims-empty"><i class="bi bi-box"></i><p>No materials — attach a BOM or issue materials manually.</p></div></td></tr>
                        <?php else: foreach ($materials as $m): ?>
                            <tr>
                                <td>
                                    <div class="gims-cell-title"><?= e($m['material_name']) ?></div>
                                    <small class="text-muted"><?= e($m['sku']) ?></small>
                                </td>
                                <td class="text-end fw-semibold"><?= qty($m['required_qty'], 2) ?> <small class="text-muted"><?= e($m['unit']) ?></small></td>
                                <td class="text-end"><?= qty($m['issued_qty'], 2) ?></td>
                                <td class="text-end <?= $m['stock_qty'] < $m['required_qty'] ? 'text-danger' : '' ?>"><?= qty($m['stock_qty']) ?></td>
                                <td><?= statusBadge($m['status']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="gims-card mb-3">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Production Output</h5>
                <?php if ($po['status'] === 'in_progress'): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#outputModal"><i class="bi bi-plus me-1"></i> Log Output</button>
                <?php endif; ?>
            </div>
            <div class="gims-card-body p-0">
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr><th>Date</th><th class="text-end">Quantity</th><th class="text-end">Passed</th><th class="text-end">Rejected</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($outputs)): ?>
                            <tr><td colspan="5"><div class="gims-empty"><i class="bi bi-box-seam"></i><p>No output logged yet.</p></div></td></tr>
                        <?php else: foreach ($outputs as $o): ?>
                            <tr>
                                <td><?= fdate($o['output_date']) ?></td>
                                <td class="text-end"><?= qty($o['quantity']) ?></td>
                                <td class="text-end text-success"><?= qty($o['passed_qty']) ?></td>
                                <td class="text-end text-danger"><?= qty($o['rejected_qty']) ?></td>
                                <td><?= statusBadge($o['status']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (!empty($qc)): ?>
        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">QC Inspections</h5></div>
            <div class="gims-card-body p-0">
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead><tr><th>Inspection #</th><th>Date</th><th class="text-end">Inspected</th><th class="text-end">Passed</th><th class="text-end">Failed</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($qc as $q): ?>
                            <tr>
                                <td><a href="<?= BASE_URL ?>/quality-control/inspection-view.php?id=<?= (int)$q['id'] ?>" class="fw-semibold text-decoration-none"><?= e($q['inspection_no']) ?></a></td>
                                <td><?= fdate($q['inspection_date']) ?></td>
                                <td class="text-end"><?= qty($q['inspected_qty']) ?></td>
                                <td class="text-end text-success"><?= qty($q['passed_qty']) ?></td>
                                <td class="text-end text-danger"><?= qty($q['failed_qty']) ?></td>
                                <td><?= statusBadge($q['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Progress</h5></div>
            <div class="gims-card-body">
                <?php $pct = $po['quantity'] > 0 ? min(100, round(($po['produced_qty'] / $po['quantity']) * 100)) : 0; ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Completion</span>
                    <strong><?= $pct ?>%</strong>
                </div>
                <div class="progress mb-3" style="height:10px">
                    <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
                </div>
                <table class="table gims-table mb-0">
                    <tr><td class="text-muted small">Ordered</td><td class="text-end fw-semibold"><?= qty($po['quantity']) ?></td></tr>
                    <tr><td class="text-muted small">Produced</td><td class="text-end"><?= qty($po['produced_qty']) ?></td></tr>
                    <tr><td class="text-muted small">Remaining</td><td class="text-end text-warning"><?= qty($po['quantity'] - $po['produced_qty']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Issue Material Modal -->
<div class="modal fade" id="issueModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="issueForm">
            <div class="modal-header">
                <h5 class="modal-title">Issue Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="production_order_id" value="<?= (int)$id ?>">
                <div class="mb-3">
                    <label class="form-label">Material <span class="text-danger">*</span></label>
                    <select name="material_id" class="form-select" required>
                        <option value="">— Select —</option>
                        <?php foreach ($materials as $m): ?>
                            <option value="<?= (int)$m['material_id'] ?>">
                                <?= e($m['material_name']) ?> — need <?= qty((float)$m['required_qty'] - (float)$m['issued_qty']) ?> <?= e($m['unit']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quantity <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" required>
                </div>
                <div class="mb-0">
                    <label class="form-label">From Warehouse <span class="text-danger">*</span></label>
                    <select name="warehouse_id" class="form-select" required>
                        <?php
                        $whs = $pdo->query('SELECT id, name FROM warehouses WHERE deleted_at IS NULL ORDER BY is_default DESC, name ASC')->fetchAll();
                        foreach ($whs as $w):
                        ?>
                            <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Issue</button>
            </div>
        </form>
    </div>
</div>

<!-- Log Output Modal -->
<div class="modal fade" id="outputModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="outputForm">
            <div class="modal-header">
                <h5 class="modal-title">Log Production Output</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="production_order_id" value="<?= (int)$id ?>">
                <input type="hidden" name="product_id" value="<?= (int)$po['product_id'] ?>">
                <input type="hidden" name="variant_id" value="0">
                <div class="mb-3">
                    <label class="form-label">Quantity Produced <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Output Date <span class="text-danger">*</span></label>
                    <input type="date" name="output_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-0">
                    <label class="form-label">Output Warehouse <span class="text-danger">*</span></label>
                    <select name="warehouse_id" class="form-select" required>
                        <?php foreach ($whs as $w): ?>
                            <option value="<?= (int)$w['id'] ?>" <?= (int)$po['warehouse_id'] === (int)$w['id'] ? 'selected' : '' ?>>
                                <?= e($w['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Log Output</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>