<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('bom.manage');

$pdo = db();
$id  = (int)get('id', 0);
if ($id <= 0) { flash('danger', 'Invalid BOM.'); redirect('bom/index.php'); }

$stmt = $pdo->prepare('SELECT b.*, p.name AS product_name, p.sku, u.name AS created_by_name FROM bom b JOIN products p ON p.id = b.product_id LEFT JOIN users u ON u.id = b.created_by WHERE b.id = ?');
$stmt->execute([$id]);
$bom = $stmt->fetch();
if (!$bom) { flash('danger', 'BOM not found.'); redirect('bom/index.php'); }

$items = $pdo->prepare(
    'SELECT bi.*, m.name AS material_name, m.sku AS material_sku, m.cost_price,
            COALESCE((SELECT SUM(quantity) FROM stock WHERE product_id = bi.material_id), 0) AS stock_qty
     FROM bom_items bi
     JOIN products m ON m.id = bi.material_id
     WHERE bi.bom_id = ?
     ORDER BY bi.id ASC'
);
$items->execute([$id]);
$items = $items->fetchAll();

$pageTitle    = 'BOM: ' . $bom['name'];
$pageSubtitle = $bom['product_name'] . ' · v' . $bom['version'];
$breadcrumbs  = ['Production' => null, 'Bill of Materials' => BASE_URL . '/bom/index.php', 'View' => null];

$pageActions = '<button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>';

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="gims-card mb-3">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Material Requirements</h5>
                <span class="badge badge-soft-primary"><?= count($items) ?> material(s)</span>
            </div>
            <div class="gims-card-body p-0">
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th class="text-end">Per Unit</th>
                                <th class="text-end">Wastage %</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Effective Cost</th>
                                <th class="text-end">In Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $perUnitCost = 0;
                        foreach ($items as $it):
                            $effCost = (float)$it['quantity'] * (1 + (float)$it['wastage_pct'] / 100) * (float)$it['cost_price'];
                            $perUnitCost += $effCost;
                        ?>
                            <tr>
                                <td>
                                    <div class="gims-cell-title"><?= e($it['material_name']) ?></div>
                                    <small class="text-muted"><?= e($it['material_sku']) ?></small>
                                </td>
                                <td class="text-end fw-semibold"><?= qty($it['quantity'], 4) ?> <small class="text-muted"><?= e($it['unit']) ?></small></td>
                                <td class="text-end text-muted"><?= qty($it['wastage_pct'], 2) ?>%</td>
                                <td class="text-end"><?= money($it['cost_price']) ?></td>
                                <td class="text-end fw-semibold"><?= money($effCost) ?></td>
                                <td class="text-end"><?= qty($it['stock_qty']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="4" class="text-end fw-bold">Total Material Cost / Unit</td>
                                <td class="text-end fw-bold text-primary"><?= money($perUnitCost) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">BOM Details</h5></div>
            <div class="gims-card-body">
                <table class="table gims-table mb-0">
                    <tr><td class="text-muted small">Product</td><td class="text-end fw-semibold"><?= e($bom['product_name']) ?></td></tr>
                    <tr><td class="text-muted small">SKU</td><td class="text-end"><?= e($bom['sku']) ?></td></tr>
                    <tr><td class="text-muted small">Version</td><td class="text-end"><?= e($bom['version']) ?></td></tr>
                    <tr><td class="text-muted small">Status</td><td class="text-end"><?= statusBadge($bom['status']) ?></td></tr>
                    <tr><td class="text-muted small">Created By</td><td class="text-end"><?= e($bom['created_by_name'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted small">Created</td><td class="text-end"><?= fdate($bom['created_at']) ?></td></tr>
                </table>
                <?php if (!empty($bom['notes'])): ?>
                    <div class="mt-3 p-3 bg-light rounded small"><?= nl2br(e($bom['notes'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Quick Calculator</h5></div>
            <div class="gims-card-body">
                <label class="form-label">Production Quantity</label>
                <div class="input-group mb-3">
                    <input type="number" id="calcQty" class="form-control" value="100" min="1">
                    <button class="btn btn-primary" id="calcBtn"><i class="bi bi-calculator"></i> Calculate</button>
                </div>
                <div id="calcResult" class="d-none">
                    <div class="table-responsive">
                        <table class="table gims-table mb-0">
                            <thead><tr><th>Material</th><th class="text-end">Required</th></tr></thead>
                            <tbody id="calcBody"></tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td class="fw-bold">Estimated Cost</td>
                                    <td class="text-end fw-bold text-primary" id="calcCost">—</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.BOM_ID = <?= (int)$id ?>;
</script>
<?php $pageScripts = [ASSETS_URL . '/js/production.js']; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>