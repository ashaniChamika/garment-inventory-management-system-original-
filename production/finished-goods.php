<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('production.manage');

$pageTitle    = 'Finished Goods';
$pageSubtitle = 'Ready-to-sell garments currently in stock';
$breadcrumbs  = ['Production' => null, 'Finished Goods' => null];

$pdo = db();
$rows = $pdo->query(
    "SELECT p.id, p.sku, p.name, p.unit, p.selling_price, p.cost_price,
            c.name AS category_name,
            COALESCE(SUM(s.quantity), 0) AS stock_qty
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN stock s ON s.product_id = p.id
     WHERE p.deleted_at IS NULL AND p.product_type = 'finished_garment' AND p.status = 'active'
     GROUP BY p.id
     ORDER BY p.name ASC"
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Finished Garments</h5>
            <small class="text-muted"><?= count($rows) ?> product(s)</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-end">On Hand</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Selling</th>
                        <th class="text-end">Margin</th>
                        <th class="text-end">Stock Value</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $qty = (float)$r['stock_qty'];
                    $cost = (float)$r['cost_price'];
                    $sell = (float)$r['selling_price'];
                    $margin = $sell > 0 ? round((($sell - $cost) / $sell) * 100, 1) : 0;
                ?>
                    <tr>
                        <td>
                            <div class="gims-cell-title"><?= e($r['name']) ?></div>
                            <small class="text-muted"><?= e($r['sku']) ?></small>
                        </td>
                        <td><?= e($r['category_name'] ?: '—') ?></td>
                        <td class="text-end fw-semibold"><?= qty($qty) ?> <small class="text-muted"><?= e($r['unit']) ?></small></td>
                        <td class="text-end"><?= money($cost) ?></td>
                        <td class="text-end"><?= money($sell) ?></td>
                        <td class="text-end <?= $margin >= 30 ? 'text-success' : ($margin >= 15 ? 'text-warning' : 'text-danger') ?>">
                            <?= number_format($margin, 1) ?>%
                        </td>
                        <td class="text-end fw-semibold"><?= money($qty * $cost) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>