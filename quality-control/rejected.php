<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('qc.manage');

$pageTitle    = 'Rejected Items';
$pageSubtitle = 'Production outputs that failed quality control';
$breadcrumbs  = ['Quality Control' => null, 'Rejected Items' => null];

$pdo = db();
$rows = $pdo->query(
    "SELECT po.id, po.order_no, p.name AS product_name, p.sku,
            po.quantity AS output_qty, po.rejected_qty, po.output_date, po.status,
            w.name AS warehouse_name
     FROM production_outputs po
     JOIN products p ON p.id = po.product_id
     JOIN warehouses w ON w.id = po.warehouse_id
     WHERE po.rejected_qty > 0
     ORDER BY po.output_date DESC LIMIT 100"
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Rejected Outputs</h5>
            <small class="text-muted"><?= count($rows) ?> record(s)</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="gims-empty"><i class="bi bi-check2-circle"></i><p>No rejected items recorded.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Production Order</th>
                        <th>Product</th>
                        <th>Warehouse</th>
                        <th class="text-end">Output Qty</th>
                        <th class="text-end">Rejected</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><span class="fw-semibold"><?= e($r['order_no']) ?></span></td>
                        <td>
                            <div class="gims-cell-title"><?= e($r['product_name']) ?></div>
                            <small class="text-muted"><?= e($r['sku']) ?></small>
                        </td>
                        <td><?= e($r['warehouse_name']) ?></td>
                        <td class="text-end"><?= qty($r['output_qty']) ?></td>
                        <td class="text-end fw-semibold text-danger"><?= qty($r['rejected_qty']) ?></td>
                        <td><?= fdate($r['output_date']) ?></td>
                        <td><?= statusBadge($r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>