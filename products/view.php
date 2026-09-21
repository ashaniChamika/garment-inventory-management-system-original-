<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('product.view');

$pdo = db();
$id  = (int)get('id', 0);
if ($id <= 0) { flash('danger', 'Invalid product.'); redirect('products/index.php'); }

$stmt = $pdo->prepare(
    'SELECT p.*, c.name AS category_name, sc.name AS sub_category_name,
            s.company_name AS supplier_name, u.name AS created_by_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN categories sc ON sc.id = p.sub_category_id
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     LEFT JOIN users u ON u.id = p.created_by
     WHERE p.id = ? AND p.deleted_at IS NULL'
);
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { flash('danger', 'Product not found.'); redirect('products/index.php'); }

$pageTitle    = $p['name'];
$pageSubtitle = $p['sku'] . ' · ' . $p['product_code'];
$breadcrumbs  = ['Inventory' => null, 'Products' => BASE_URL . '/products/index.php', 'View' => null];

$pageActions  = '';
if (hasPermission('product.edit')) {
    $pageActions .= '<a href="' . BASE_URL . '/products/edit.php?id=' . $id . '" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i> Edit</a> ';
}
if (hasPermission('barcode.manage')) {
    $pageActions .= '<a href="' . BASE_URL . '/barcode/generate-barcode.php?id=' . $id . '" class="btn btn-outline-secondary"><i class="bi bi-upc-scan me-1"></i> Barcode</a> ';
}
if (!empty($p['has_variants'])) {
    $pageActions .= '<a href="' . BASE_URL . '/products/variants.php?id=' . $id . '" class="btn btn-outline-secondary"><i class="bi bi-palette me-1"></i> Variants</a>';
}

/* ---- Data ---- */
$stockRows = $pdo->prepare(
    'SELECT s.*, w.name AS warehouse_name, w.code AS warehouse_code
     FROM stock s
     JOIN warehouses w ON w.id = s.warehouse_id
     WHERE s.product_id = ?
     ORDER BY w.name ASC'
);
$stockRows->execute([$id]);
$stockRows = $stockRows->fetchAll();

$totalStock = array_sum(array_map(fn($r) => (float)$r['quantity'], $stockRows));
$stockValue = $totalStock * (float)$p['cost_price'];

$variants = [];
if ($p['has_variants']) {
    $v = $pdo->prepare('SELECT * FROM product_variants WHERE product_id = ? AND deleted_at IS NULL ORDER BY color, size');
    $v->execute([$id]);
    $variants = $v->fetchAll();
}

$movements = $pdo->prepare(
    'SELECT m.*, w.name AS warehouse_name
     FROM stock_movements m
     LEFT JOIN warehouses w ON w.id = m.warehouse_id
     WHERE m.product_id = ?
     ORDER BY m.id DESC LIMIT 15'
);
$movements->execute([$id]);
$movements = $movements->fetchAll();

$bomItems = $pdo->prepare(
    'SELECT b.name AS bom_name, bi.quantity, bi.unit, bi.wastage_pct, bi.notes,
            mp.name AS material_name, mp.sku AS material_sku
     FROM bom_items bi
     JOIN bom b ON b.id = bi.bom_id
     JOIN products mp ON mp.id = bi.material_id
     WHERE b.product_id = ? AND b.status = "active"
     ORDER BY b.id DESC, bi.id ASC'
);
$bomItems->execute([$id]);
$bomItems = $bomItems->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Summary KPI row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-stack"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Total Stock</span>
                <strong class="gims-kpi-value"><?= qty($totalStock) ?> <small class="text-muted fw-normal"><?= e($p['unit']) ?></small></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-info">
            <div class="gims-kpi-icon"><i class="bi bi-currency-exchange"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Stock Value</span>
                <strong class="gims-kpi-value"><?= money($stockValue) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi <?= $totalStock <= $p['reorder_level'] ? 'gims-kpi-warning' : 'gims-kpi-success' ?>">
            <div class="gims-kpi-icon"><i class="bi bi-bell"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Reorder Level</span>
                <strong class="gims-kpi-value"><?= qty($p['reorder_level']) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="gims-kpi gims-kpi-dark">
            <div class="gims-kpi-icon"><i class="bi bi-info-circle"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Status</span>
                <strong class="gims-kpi-value"><?= statusBadge($p['status']) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="gims-card mb-3">
            <div class="gims-card-body text-center">
                <?php if (!empty($p['image'])): ?>
                    <img src="<?= UPLOADS_URL . '/' . e($p['image']) ?>"
                         class="img-fluid rounded mb-3" style="max-height:240px;object-fit:contain">
                <?php else: ?>
                    <div class="gims-upload-preview mb-3"><i class="bi bi-image"></i></div>
                <?php endif; ?>
                <h5 class="mb-1"><?= e($p['name']) ?></h5>
                <p class="text-muted small mb-2"><?= e($p['sku']) ?> · <?= e($p['product_code']) ?></p>
                <?= statusBadge($p['status']) ?>
                <?php if ($p['has_variants']): ?>
                    <span class="badge badge-soft-info ms-1">Has Variants</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Details</h5></div>
            <div class="gims-card-body p-0">
                <table class="table gims-table mb-0">
                    <tbody>
                        <tr><td class="text-muted small">Category</td><td class="text-end fw-semibold"><?= e($p['category_name'] ?: '—') ?></td></tr>
                        <tr><td class="text-muted small">Sub Category</td><td class="text-end fw-semibold"><?= e($p['sub_category_name'] ?: '—') ?></td></tr>
                        <tr><td class="text-muted small">Type</td><td class="text-end fw-semibold"><?= e(ucwords(str_replace('_',' ',$p['product_type']))) ?></td></tr>
                        <tr><td class="text-muted small">Brand</td><td class="text-end fw-semibold"><?= e($p['brand'] ?: '—') ?></td></tr>
                        <tr><td class="text-muted small">Unit</td><td class="text-end fw-semibold"><?= e($p['unit']) ?></td></tr>
                        <tr><td class="text-muted small">Supplier</td><td class="text-end fw-semibold"><?= e($p['supplier_name'] ?: '—') ?></td></tr>
                        <tr><td class="text-muted small">Cost Price</td><td class="text-end fw-semibold"><?= money($p['cost_price']) ?></td></tr>
                        <tr><td class="text-muted small">Selling Price</td><td class="text-end fw-semibold"><?= money($p['selling_price']) ?></td></tr>
                        <tr><td class="text-muted small">Min / Max</td><td class="text-end fw-semibold"><?= qty($p['min_stock']) ?> / <?= qty($p['max_stock']) ?></td></tr>
                        <tr><td class="text-muted small">Barcode</td><td class="text-end fw-semibold"><code><?= e($p['barcode'] ?: '—') ?></code></td></tr>
                        <tr><td class="text-muted small">Created</td><td class="text-end fw-semibold"><?= fdate($p['created_at']) ?></td></tr>
                        <tr><td class="text-muted small">Created By</td><td class="text-end fw-semibold"><?= e($p['created_by_name'] ?: '—') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($p['description'])): ?>
            <div class="gims-card">
                <div class="gims-card-head"><h5 class="gims-card-title">Description</h5></div>
                <div class="gims-card-body small"><?= nl2br(e($p['description'])) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">

        <!-- Stock by warehouse -->
        <div class="gims-card mb-3">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Stock by Warehouse</h5>
                <span class="badge badge-soft-primary"><?= count($stockRows) ?> warehouse(s)</span>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($stockRows)): ?>
                    <div class="gims-empty"><i class="bi bi-inbox"></i><p>No stock records yet.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr>
                                <th>Warehouse</th>
                                <th class="text-end">On Hand</th>
                                <th class="text-end">Reserved</th>
                                <th class="text-end">Damaged</th>
                                <th class="text-end">Rejected</th>
                                <th class="text-end">Available</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stockRows as $sr):
                            $avail = (float)$sr['quantity'] - (float)$sr['reserved_qty'] - (float)$sr['damaged_qty']; ?>
                            <tr>
                                <td>
                                    <div class="gims-cell-title"><?= e($sr['warehouse_name']) ?></div>
                                    <small class="text-muted"><?= e($sr['warehouse_code']) ?></small>
                                </td>
                                <td class="text-end fw-semibold"><?= qty($sr['quantity']) ?></td>
                                <td class="text-end text-muted"><?= qty($sr['reserved_qty']) ?></td>
                                <td class="text-end text-danger"><?= qty($sr['damaged_qty']) ?></td>
                                <td class="text-end text-danger"><?= qty($sr['rejected_qty']) ?></td>
                                <td class="text-end fw-bold text-success"><?= qty($avail) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Variants -->
        <?php if ($p['has_variants']): ?>
        <div class="gims-card mb-3">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Variants</h5>
                <a href="<?= BASE_URL ?>/products/variants.php?id=<?= $id ?>" class="small">Manage</a>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($variants)): ?>
                    <div class="gims-empty"><i class="bi bi-palette"></i><p>No variants defined yet.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr><th>Size</th><th>Color</th><th>SKU</th><th class="text-end">Cost</th><th class="text-end">Selling</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($variants as $v): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($v['size']) ?></td>
                                <td><?= e($v['color']) ?></td>
                                <td><code><?= e($v['sku']) ?></code></td>
                                <td class="text-end"><?= money($v['cost_price']) ?></td>
                                <td class="text-end"><?= money($v['selling_price']) ?></td>
                                <td><?= statusBadge($v['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- BOM -->
        <?php if (!empty($bomItems)): ?>
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Bill of Materials</h5></div>
            <div class="gims-card-body p-0">
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr><th>Material</th><th class="text-end">Qty</th><th>Unit</th><th class="text-end">Wastage %</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bomItems as $bi): ?>
                            <tr>
                                <td>
                                    <div class="gims-cell-title"><?= e($bi['material_name']) ?></div>
                                    <small class="text-muted"><?= e($bi['material_sku']) ?></small>
                                </td>
                                <td class="text-end fw-semibold"><?= qty($bi['quantity'], 4) ?></td>
                                <td><?= e($bi['unit']) ?></td>
                                <td class="text-end text-muted"><?= qty($bi['wastage_pct'], 2) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Movements -->
        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Recent Stock Movements</h5>
                <a href="<?= BASE_URL ?>/reports/stock-movement.php?product_id=<?= $id ?>" class="small">View all</a>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($movements)): ?>
                    <div class="gims-empty"><i class="bi bi-arrow-left-right"></i><p>No movements recorded.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr><th>Date</th><th>Type</th><th>Warehouse</th><th class="text-end">Qty</th><th class="text-end">Balance</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($movements as $mv): ?>
                            <tr>
                                <td class="small text-muted"><?= fdatetime($mv['created_at']) ?></td>
                                <td><span class="badge badge-soft-secondary"><?= e(ucwords(str_replace('_',' ',$mv['movement_type']))) ?></span></td>
                                <td><?= e($mv['warehouse_name'] ?: '—') ?></td>
                                <td class="text-end fw-semibold <?= $mv['direction'] === 'in' ? 'text-success' : 'text-danger' ?>">
                                    <?= $mv['direction'] === 'in' ? '+' : '−' ?><?= qty($mv['quantity']) ?>
                                </td>
                                <td class="text-end"><?= qty($mv['balance_after']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.gims-upload-preview {
    width: 100%; height: 200px;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    display: grid; place-items: center;
    background: #f8fafc;
    color: #94a3b8;
    font-size: 48px;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>