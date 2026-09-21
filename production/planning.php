<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('production.manage');

$pageTitle    = 'Production Planning';
$pageSubtitle = 'Pipeline view of upcoming and active production runs';
$breadcrumbs  = ['Production' => null, 'Planning' => null];

$pdo = db();

$upcoming = $pdo->query(
    "SELECT po.*, p.name AS product_name, p.sku, w.name AS warehouse_name
     FROM production_orders po
     JOIN products p ON p.id = po.product_id
     JOIN warehouses w ON w.id = po.warehouse_id
     WHERE po.deleted_at IS NULL AND po.status IN ('planned','in_progress','paused')
     ORDER BY po.start_date ASC LIMIT 50"
)->fetchAll();

$materialsNeeded = $pdo->query(
    "SELECT p.id, p.name, p.sku, p.unit,
            SUM(pm.required_qty - pm.issued_qty) AS needed,
            COALESCE((SELECT SUM(quantity) FROM stock WHERE product_id = p.id), 0) AS in_stock
     FROM production_materials pm
     JOIN products p ON p.id = pm.material_id
     JOIN production_orders po ON po.id = pm.production_order_id
     WHERE po.status IN ('planned','in_progress') AND po.deleted_at IS NULL
       AND pm.required_qty > pm.issued_qty
     GROUP BY p.id
     ORDER BY (SUM(pm.required_qty - pm.issued_qty) - COALESCE((SELECT SUM(quantity) FROM stock WHERE product_id = p.id), 0)) DESC
     LIMIT 20"
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Active &amp; Upcoming Production</h5>
                <span class="badge badge-soft-primary"><?= count($upcoming) ?> orders</span>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($upcoming)): ?>
                    <div class="gims-empty"><i class="bi bi-calendar3"></i><p>No active or planned production orders.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr><th>Order #</th><th>Product</th><th>Qty</th><th>Start</th><th>Expected</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($upcoming as $po): ?>
                            <tr>
                                <td><a href="<?= BASE_URL ?>/production/production-order-view.php?id=<?= (int)$po['id'] ?>" class="fw-semibold text-decoration-none"><?= e($po['order_no']) ?></a></td>
                                <td>
                                    <div class="gims-cell-title"><?= e($po['product_name']) ?></div>
                                    <small class="text-muted"><?= e($po['sku']) ?></small>
                                </td>
                                <td class="text-end"><?= qty($po['quantity']) ?></td>
                                <td><?= fdate($po['start_date']) ?></td>
                                <td><?= fdate($po['expected_date']) ?></td>
                                <td><?= statusBadge($po['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title"><i class="bi bi-exclamation-triangle text-warning me-1"></i> Material Shortages</h5>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($materialsNeeded)): ?>
                    <div class="gims-empty"><i class="bi bi-check2-circle"></i><p>All materials sufficiently stocked.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead><tr><th>Material</th><th class="text-end">Needed</th><th class="text-end">In Stock</th><th class="text-end">Shortage</th></tr></thead>
                        <tbody>
                        <?php foreach ($materialsNeeded as $m):
                            $short = max(0, (float)$m['needed'] - (float)$m['in_stock']); ?>
                            <tr>
                                <td>
                                    <div class="gims-cell-title"><?= e($m['name']) ?></div>
                                    <small class="text-muted"><?= e($m['sku']) ?></small>
                                </td>
                                <td class="text-end"><?= qty($m['needed']) ?> <?= e($m['unit']) ?></td>
                                <td class="text-end"><?= qty($m['in_stock']) ?></td>
                                <td class="text-end fw-semibold <?= $short > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= $short > 0 ? qty($short) : 'OK' ?>
                                </td>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>