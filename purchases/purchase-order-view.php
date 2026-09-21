<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('purchase.manage');

$pdo = db();
$id  = (int)get('id', 0);
if ($id <= 0) { flash('danger', 'Invalid purchase order.'); redirect('purchases/purchase-orders.php'); }

$stmt = $pdo->prepare(
    'SELECT po.*, s.company_name, s.contact_person, s.phone AS supplier_phone, s.email AS supplier_email,
            s.address AS supplier_address, s.tax_number AS supplier_tax,
            w.name AS warehouse_name,
            u1.name AS created_by_name, u2.name AS approved_by_name
     FROM purchase_orders po
     JOIN suppliers s ON s.id = po.supplier_id
     JOIN warehouses w ON w.id = po.warehouse_id
     LEFT JOIN users u1 ON u1.id = po.created_by
     LEFT JOIN users u2 ON u2.id = po.approved_by
     WHERE po.id = ? AND po.deleted_at IS NULL'
);
$stmt->execute([$id]);
$po = $stmt->fetch();
if (!$po) { flash('danger', 'Purchase order not found.'); redirect('purchases/purchase-orders.php'); }

$items = $pdo->prepare(
    'SELECT i.*, p.name AS product_name, p.sku, p.unit
     FROM purchase_order_items i
     JOIN products p ON p.id = i.product_id
     WHERE i.po_id = ?'
);
$items->execute([$id]);
$items = $items->fetchAll();

$grns = $pdo->prepare(
    'SELECT g.id, g.grn_number, g.received_date, g.status, g.invoice_no
     FROM goods_received g WHERE g.po_id = ? ORDER BY g.id ASC'
);
$grns->execute([$id]);
$grns = $grns->fetchAll();

$pageTitle    = 'Purchase Order ' . $po['po_number'];
$pageSubtitle = $po['company_name'];
$breadcrumbs  = ['Purchasing' => null,
                 'Purchase Orders' => BASE_URL . '/purchases/purchase-orders.php',
                 'View' => null];

$pageActions = '';
if (in_array($po['status'], ['draft','pending'], true)) {
    $pageActions .= '<a href="' . BASE_URL . '/purchases/purchase-order-create.php?id=' . $id . '" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Edit</a> ';
}
if ($po['status'] === 'draft') {
    $pageActions .= '<button class="btn btn-success" id="btnSubmitPo" data-id="' . $id . '"><i class="bi bi-send me-1"></i> Submit</button> ';
}
if ($po['status'] === 'pending' && hasPermission('purchase.manage')) {
    $pageActions .= '<button class="btn btn-success" id="btnApprovePo" data-id="' . $id . '"><i class="bi bi-check2 me-1"></i> Approve</button> ';
}
if (in_array($po['status'], ['approved','partially_received'], true)) {
    $pageActions .= '<a href="' . BASE_URL . '/purchases/grn-create.php?po_id=' . $id . '" class="btn btn-success"><i class="bi bi-box-arrow-in-down me-1"></i> Receive Goods</a> ';
}
$pageActions .= '<button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>';

$pageScripts = [ASSETS_URL . '/js/purchases.js'];

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="gims-card">
            <div class="gims-card-head">
                <div>
                    <h5 class="gims-card-title">PO Information</h5>
                </div>
                <div><?= statusBadge($po['status']) ?></div>
            </div>
            <div class="gims-card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table gims-table mb-0">
                            <tr><td class="text-muted small" style="width:40%">PO Number</td><td class="fw-semibold"><?= e($po['po_number']) ?></td></tr>
                            <tr><td class="text-muted small">Order Date</td><td><?= fdate($po['order_date']) ?></td></tr>
                            <tr><td class="text-muted small">Expected Date</td><td><?= fdate($po['expected_date']) ?></td></tr>
                            <tr><td class="text-muted small">Warehouse</td><td><?= e($po['warehouse_name']) ?></td></tr>
                            <tr><td class="text-muted small">Created By</td><td><?= e($po['created_by_name'] ?: '—') ?></td></tr>
                            <?php if ($po['approved_by_name']): ?>
                            <tr><td class="text-muted small">Approved By</td><td><?= e($po['approved_by_name']) ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table gims-table mb-0">
                            <tr><td class="text-muted small" style="width:40%">Supplier</td><td class="fw-semibold"><?= e($po['company_name']) ?></td></tr>
                            <tr><td class="text-muted small">Contact</td><td><?= e($po['contact_person'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Phone</td><td><?= e($po['supplier_phone'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Email</td><td><?= e($po['supplier_email'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Tax Number</td><td><?= e($po['supplier_tax'] ?: '—') ?></td></tr>
                        </table>
                    </div>
                </div>
                <?php if (!empty($po['notes'])): ?>
                    <div class="mt-3 p-3 bg-light rounded">
                        <small class="text-muted d-block mb-1">Notes</small>
                        <?= nl2br(e($po['notes'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Financial Summary</h5></div>
            <div class="gims-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><strong><?= money($po['subtotal']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Discount</span><strong class="text-danger">− <?= money($po['discount']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Tax</span><strong><?= money($po['tax']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Shipping</span><strong><?= money($po['shipping']) ?></strong></div>
                <hr>
                <div class="d-flex justify-content-between mb-2"><span class="fw-bold">Total</span><strong class="text-primary fs-5"><?= money($po['total']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Paid</span><strong class="text-success"><?= money($po['paid_amount']) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Balance</span><strong class="text-danger"><?= money($po['total'] - $po['paid_amount']) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="gims-card mb-3">
    <div class="gims-card-head"><h5 class="gims-card-title">Order Items</h5></div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Ordered</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it):
                    $remaining = (float)$it['quantity'] - (float)$it['received_qty']; ?>
                    <tr>
                        <td>
                            <div class="gims-cell-title"><?= e($it['product_name']) ?></div>
                            <small class="text-muted"><?= e($it['sku']) ?></small>
                        </td>
                        <td class="text-end fw-semibold"><?= qty($it['quantity']) ?> <small class="text-muted"><?= e($it['unit']) ?></small></td>
                        <td class="text-end <?= $remaining > 0 ? 'text-warning' : 'text-success' ?>">
                            <?= qty($it['received_qty']) ?>
                            <?php if ($remaining > 0): ?><small class="d-block">(<?= qty($remaining) ?> left)</small><?php endif; ?>
                        </td>
                        <td class="text-end"><?= money($it['unit_price']) ?></td>
                        <td class="text-end text-danger"><?= money($it['discount']) ?></td>
                        <td class="text-end"><?= money($it['tax']) ?></td>
                        <td class="text-end fw-semibold"><?= money($it['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($grns)): ?>
<div class="gims-card">
    <div class="gims-card-head"><h5 class="gims-card-title">Goods Received</h5></div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr><th>GRN #</th><th>Invoice #</th><th>Received On</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($grns as $g): ?>
                    <tr>
                        <td><span class="fw-semibold"><?= e($g['grn_number']) ?></span></td>
                        <td><?= e($g['invoice_no'] ?: '—') ?></td>
                        <td><?= fdate($g['received_date']) ?></td>
                        <td><?= statusBadge($g['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>