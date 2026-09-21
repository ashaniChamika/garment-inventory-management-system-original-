<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('sales.manage');

$pdo = db();
$id  = (int)get('id', 0);
if ($id <= 0) { flash('danger', 'Invalid order.'); redirect('sales/sales-orders.php'); }

$stmt = $pdo->prepare(
    'SELECT so.*, c.name AS customer_name, c.company, c.phone AS customer_phone,
            c.email AS customer_email, c.address AS customer_address, c.tax_number AS customer_tax,
            w.name AS warehouse_name, u.name AS created_by_name
     FROM sales_orders so
     JOIN customers c ON c.id = so.customer_id
     JOIN warehouses w ON w.id = so.warehouse_id
     LEFT JOIN users u ON u.id = so.created_by
     WHERE so.id = ? AND so.deleted_at IS NULL'
);
$stmt->execute([$id]);
$so = $stmt->fetch();
if (!$so) { flash('danger', 'Order not found.'); redirect('sales/sales-orders.php'); }

$items = $pdo->prepare(
    'SELECT i.*, p.name AS product_name, p.sku, p.unit
     FROM sales_order_items i JOIN products p ON p.id = i.product_id
     WHERE i.so_id = ?'
);
$items->execute([$id]);
$items = $items->fetchAll();

$invoices = $pdo->prepare('SELECT id, invoice_no, invoice_date, total, status FROM invoices WHERE so_id = ?');
$invoices->execute([$id]);
$invoices = $invoices->fetchAll();

$pageTitle    = 'Order ' . $so['order_no'];
$pageSubtitle = $so['customer_name'];
$breadcrumbs  = ['Sales' => null, 'Sales Orders' => BASE_URL . '/sales/sales-orders.php', 'View' => null];

$pageActions = '';
if (in_array($so['status'], ['draft','pending'], true)) {
    $pageActions .= '<a href="' . BASE_URL . '/sales/sales-order-create.php?id=' . $id . '" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Edit</a> ';
}
if ($so['status'] === 'draft') {
    $pageActions .= '<button class="btn btn-success confirm-so" data-id="' . $id . '"><i class="bi bi-check2-circle me-1"></i> Confirm Order</button> ';
}
if (in_array($so['status'], ['confirmed','processing'], true)) {
    $pageActions .= '<a href="' . BASE_URL . '/sales/invoice-create.php?so_id=' . $id . '" class="btn btn-success"><i class="bi bi-receipt me-1"></i> Create Invoice</a> ';
    $pageActions .= '<a href="' . BASE_URL . '/sales/delivery.php?so_id=' . $id . '" class="btn btn-primary"><i class="bi bi-truck me-1"></i> Create Delivery</a> ';
}
$pageActions .= '<button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>';

$pageScripts = [ASSETS_URL . '/js/sales.js'];

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Order Information</h5>
                <div><?= statusBadge($so['status']) ?></div>
            </div>
            <div class="gims-card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table gims-table mb-0">
                            <tr><td class="text-muted small" style="width:40%">Order #</td><td class="fw-semibold"><?= e($so['order_no']) ?></td></tr>
                            <tr><td class="text-muted small">Order Date</td><td><?= fdate($so['order_date']) ?></td></tr>
                            <tr><td class="text-muted small">Delivery Date</td><td><?= fdate($so['delivery_date']) ?></td></tr>
                            <tr><td class="text-muted small">Warehouse</td><td><?= e($so['warehouse_name']) ?></td></tr>
                            <tr><td class="text-muted small">Created By</td><td><?= e($so['created_by_name'] ?: '—') ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table gims-table mb-0">
                            <tr><td class="text-muted small" style="width:40%">Customer</td><td class="fw-semibold"><?= e($so['customer_name']) ?></td></tr>
                            <tr><td class="text-muted small">Company</td><td><?= e($so['company'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Phone</td><td><?= e($so['customer_phone'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Email</td><td><?= e($so['customer_email'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Tax Number</td><td><?= e($so['customer_tax'] ?: '—') ?></td></tr>
                        </table>
                    </div>
                </div>
                <?php if ($so['notes']): ?>
                    <div class="mt-3 p-3 bg-light rounded small"><?= nl2br(e($so['notes'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Financial Summary</h5></div>
            <div class="gims-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><strong><?= money($so['subtotal']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Discount</span><strong class="text-danger">− <?= money($so['discount']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Tax</span><strong><?= money($so['tax']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Shipping</span><strong><?= money($so['shipping']) ?></strong></div>
                <hr>
                <div class="d-flex justify-content-between mb-2"><span class="fw-bold">Total</span><strong class="text-primary fs-5"><?= money($so['total']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Paid</span><strong class="text-success"><?= money($so['paid_amount']) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Balance</span><strong class="text-danger"><?= money($so['total'] - $so['paid_amount']) ?></strong></div>
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
                        <th class="text-end">Delivered</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it):
                    $remaining = (float)$it['quantity'] - (float)$it['delivered_qty']; ?>
                    <tr>
                        <td>
                            <div class="gims-cell-title"><?= e($it['product_name']) ?></div>
                            <small class="text-muted"><?= e($it['sku']) ?></small>
                        </td>
                        <td class="text-end fw-semibold"><?= qty($it['quantity']) ?> <small class="text-muted"><?= e($it['unit']) ?></small></td>
                        <td class="text-end <?= $remaining > 0 ? 'text-warning' : 'text-success' ?>"><?= qty($it['delivered_qty']) ?></td>
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

<?php if (!empty($invoices)): ?>
<div class="gims-card">
    <div class="gims-card-head"><h5 class="gims-card-title">Linked Invoices</h5></div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead><tr><th>Invoice #</th><th>Date</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/sales/invoice-view.php?id=<?= (int)$inv['id'] ?>" class="fw-semibold text-decoration-none"><?= e($inv['invoice_no']) ?></a></td>
                        <td><?= fdate($inv['invoice_date']) ?></td>
                        <td class="text-end"><?= money($inv['total']) ?></td>
                        <td><?= statusBadge($inv['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>