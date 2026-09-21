<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('production.manage');

$pageTitle    = 'Raw Materials';
$pageSubtitle = 'All raw materials currently used in production';
$breadcrumbs  = ['Production' => null, 'Raw Materials' => null];

$pdo = db();
$rows = $pdo->query(
    "SELECT p.id, p.sku, p.name, p.unit, p.cost_price, p.reorder_level,
            c.name AS category_name,
            COALESCE(SUM(s.quantity), 0) AS stock_qty,
            COALESCE(SUM(s.quantity), 0) * p.cost_price AS stock_value
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN stock s ON s.product_id = p.id
     WHERE p.deleted_at IS NULL AND p.product_type <> 'finished_garment' AND p.status = 'active'
     GROUP BY p.id
     ORDER BY p.name ASC"
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Raw Materials Inventory</h5>
            <small class="text-muted"><?= count($rows) ?> material(s)</small>
        </div>
        <input type="text" id="rmSearch" class="form-control form-control-sm" placeholder="Search…" style="width:220px">
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0" id="rmTable">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Category</th>
                        <th class="text-end">On Hand</th>
                        <th class="text-end">Reorder</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $qty = (float)$r['stock_qty'];
                    $reorder = (float)$r['reorder_level'];
                ?>
                    <tr>
                        <td>
                            <div class="gims-cell-title"><?= e($r['name']) ?></div>
                            <small class="text-muted"><?= e($r['sku']) ?></small>
                        </td>
                        <td><?= e($r['category_name'] ?: '—') ?></td>
                        <td class="text-end fw-semibold"><?= qty($qty) ?> <small class="text-muted"><?= e($r['unit']) ?></small></td>
                        <td class="text-end text-muted"><?= qty($reorder) ?></td>
                        <td class="text-end"><?= money($r['cost_price']) ?></td>
                        <td class="text-end fw-semibold"><?= money($r['stock_value']) ?></td>
                        <td>
                            <?php if ($qty <= 0): ?>
                                <span class="badge badge-soft-danger">Out of Stock</span>
                            <?php elseif ($qty <= $reorder): ?>
                                <span class="badge badge-soft-warning">Low Stock</span>
                            <?php else: ?>
                                <span class="badge badge-soft-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('rmSearch')?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#rmTable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>