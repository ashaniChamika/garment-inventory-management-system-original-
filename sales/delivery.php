<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('sales.manage');

$pageTitle    = 'Deliveries';
$pageSubtitle = 'Dispatch and track customer deliveries';
$breadcrumbs  = ['Sales' => null, 'Deliveries' => null];
$pageScripts  = [ASSETS_URL . '/js/sales.js'];

$pdo = db();
$customers  = $pdo->query('SELECT id, name, address FROM customers WHERE deleted_at IS NULL AND status = "active" ORDER BY name ASC')->fetchAll();
$warehouses = $pdo->query('SELECT id, name, is_default FROM warehouses WHERE deleted_at IS NULL ORDER BY is_default DESC, name ASC')->fetchAll();
$orders = $pdo->query("SELECT id, order_no, customer_id, warehouse_id FROM sales_orders WHERE status IN ('confirmed','processing','shipped') AND deleted_at IS NULL ORDER BY id DESC LIMIT 100")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#delModal" onclick="openDelivery()">
        <i class="bi bi-plus-lg me-1"></i> New Delivery
    </button>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Deliveries</h5>
            <small class="text-muted" id="delCount">Loading…</small>
        </div>
        <select id="delStatus" class="form-select form-select-sm" style="width:160px">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="dispatched">Dispatched</option>
            <option value="delivered">Delivered</option>
            <option value="failed">Failed</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Delivery #</th>
                        <th>SO #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Driver</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="delBody">
                    <tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="delModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="delForm">
            <div class="modal-header"><h5 class="modal-title">New Delivery</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" id="delId" value="">
                <div class="mb-3">
                    <label class="form-label">Sales Order</label>
                    <select name="so_id" id="delSo" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($orders as $o): ?>
                            <option value="<?= (int)$o['id'] ?>" data-cust="<?= (int)$o['customer_id'] ?>" data-wh="<?= (int)$o['warehouse_id'] ?>">
                                <?= e($o['order_no']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Customer <span class="text-danger">*</span></label>
                    <select name="customer_id" id="delCustomer" class="form-select" required>
                        <option value="">— Select —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                    <select name="warehouse_id" class="form-select" required>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= (int)$w['id'] ?>" <?= $w['is_default'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Delivery Date <span class="text-danger">*</span></label>
                    <input type="date" name="delivery_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" maxlength="400">
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Driver</label>
                        <input type="text" name="driver" class="form-control" maxlength="120">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Vehicle No</label>
                        <input type="text" name="vehicle_no" class="form-control" maxlength="60">
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" maxlength="400">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>