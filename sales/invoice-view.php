<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('sales.manage');

$pdo = db();
$id  = (int)get('id', 0);
if ($id <= 0) { flash('danger', 'Invalid invoice.'); redirect('sales/invoices.php'); }

$stmt = $pdo->prepare(
    'SELECT i.*, c.name AS customer_name, c.company, c.phone AS customer_phone,
            c.email AS customer_email, c.address AS customer_address, c.tax_number AS customer_tax,
            so.order_no, u.name AS created_by_name
     FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     LEFT JOIN sales_orders so ON so.id = i.so_id
     LEFT JOIN users u ON u.id = i.created_by
     WHERE i.id = ?'
);
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { flash('danger', 'Invoice not found.'); redirect('sales/invoices.php'); }

$items = $pdo->prepare('SELECT i.*, p.name AS product_name, p.sku, p.unit FROM invoice_items i JOIN products p ON p.id = i.product_id WHERE i.invoice_id = ?');
$items->execute([$id]);
$items = $items->fetchAll();

$pageTitle    = 'Invoice ' . $inv['invoice_no'];
$pageSubtitle = $inv['customer_name'];
$breadcrumbs  = ['Sales' => null, 'Invoices' => BASE_URL . '/sales/invoices.php', 'View' => null];

$pageActions = '';
if ($inv['status'] !== 'paid') {
    $pageActions .= '<button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#payModal"><i class="bi bi-cash-coin me-1"></i> Record Payment</button> ';
}
$pageActions .= '<button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>';

$pageScripts = [ASSETS_URL . '/js/sales.js'];

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Invoice #<?= e($inv['invoice_no']) ?></h5>
                <div><?= statusBadge($inv['status']) ?></div>
            </div>
            <div class="gims-card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table gims-table mb-0">
                            <tr><td class="text-muted small" style="width:40%">Invoice Date</td><td><?= fdate($inv['invoice_date']) ?></td></tr>
                            <tr><td class="text-muted small">Due Date</td><td><?= fdate($inv['due_date']) ?></td></tr>
                            <tr><td class="text-muted small">SO #</td><td><?= e($inv['order_no'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Created By</td><td><?= e($inv['created_by_name'] ?: '—') ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table gims-table mb-0">
                            <tr><td class="text-muted small" style="width:40%">Customer</td><td class="fw-semibold"><?= e($inv['customer_name']) ?></td></tr>
                            <tr><td class="text-muted small">Company</td><td><?= e($inv['company'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Phone</td><td><?= e($inv['customer_phone'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted small">Tax #</td><td><?= e($inv['customer_tax'] ?: '—') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Summary</h5></div>
            <div class="gims-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><strong><?= money($inv['subtotal']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Discount</span><strong class="text-danger">− <?= money($inv['discount']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Tax</span><strong><?= money($inv['tax']) ?></strong></div>
                <hr>
                <div class="d-flex justify-content-between mb-2"><span class="fw-bold">Total</span><strong class="text-primary fs-5"><?= money($inv['total']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Paid</span><strong class="text-success"><?= money($inv['paid_amount']) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Balance</span><strong class="text-danger"><?= money($inv['total'] - $inv['paid_amount']) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head"><h5 class="gims-card-title">Items</h5></div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Discount</th><th class="text-end">Tax</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <div class="gims-cell-title"><?= e($it['product_name']) ?></div>
                            <small class="text-muted"><?= e($it['sku']) ?></small>
                        </td>
                        <td class="text-end"><?= qty($it['quantity']) ?> <small class="text-muted"><?= e($it['unit']) ?></small></td>
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

<?php if ($inv['status'] !== 'paid'): ?>
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form class="modal-content" id="payForm">
            <div class="modal-header"><h5 class="modal-title">Record Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <div class="mb-3"><label class="form-label">Outstanding</label><input type="text" class="form-control" value="<?= money($inv['total'] - $inv['paid_amount']) ?>" readonly></div>
                <div class="mb-0"><label class="form-label">Amount <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" max="<?= $inv['total'] - $inv['paid_amount'] ?>" name="amount" class="form-control" required></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i> Record</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>