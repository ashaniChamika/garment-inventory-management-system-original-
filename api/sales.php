<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('sales.manage');

$pageTitle    = 'Customers';
$pageSubtitle = 'Manage customer accounts and credit';
$breadcrumbs  = ['Sales' => null, 'Customers' => null];
$pageScripts  = [ASSETS_URL . '/js/sales.js'];

$pdo = db();
$total = (int)$pdo->query('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL')->fetchColumn();
$active= (int)$pdo->query('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL AND status = "active"')->fetchColumn();
$outstanding = (float)$pdo->query("SELECT COALESCE(SUM(total - paid_amount),0) FROM invoices WHERE status IN ('unpaid','partial','overdue')")->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-people"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Total Customers</span>
                <strong class="gims-kpi-value"><?= number_format($total) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="gims-kpi gims-kpi-success">
            <div class="gims-kpi-icon"><i class="bi bi-check2-circle"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Active</span>
                <strong class="gims-kpi-value"><?= number_format($active) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="gims-kpi gims-kpi-warning">
            <div class="gims-kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Outstanding Receivable</span>
                <strong class="gims-kpi-value"><?= money($outstanding) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <?php if (hasPermission('customer.manage')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#custModal" onclick="openCustomer()">
            <i class="bi bi-plus-lg me-1"></i> Add Customer
        </button>
    <?php endif; ?>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Customer Directory</h5>
            <small class="text-muted" id="custCount">Loading…</small>
        </div>
        <div class="d-flex gap-2">
            <input type="text" id="custSearch" class="form-control form-control-sm" placeholder="Search…" style="width:220px">
            <select id="custStatus" class="form-select form-select-sm" style="width:150px">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>City</th>
                        <th class="text-end">Credit Limit</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Total Sales</th>
                        <th class="text-end">Outstanding</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="custBody">
                    <tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="custPagerWrap" style="display:none">
            <small class="text-muted" id="custPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="custPager"></ul></nav>
        </div>
    </div>
</div>

<div class="modal fade" id="custModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="custForm">
            <div class="modal-header">
                <h5 class="modal-title" id="custModalTitle">Add Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="custId" value="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="custName" class="form-control" required maxlength="190">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Company</label>
                        <input type="text" name="company" id="custCompany" class="form-control" maxlength="190">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="custPhone" class="form-control" maxlength="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="custEmail" class="form-control" maxlength="190">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tax Number</label>
                        <input type="text" name="tax_number" id="custTax" class="form-control" maxlength="60">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" id="custAddress" class="form-control" maxlength="400">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">City</label>
                        <input type="text" name="city" id="custCity" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" id="custCountry" class="form-control" value="Sri Lanka">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" id="custStatusInput" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Credit Limit</label>
                        <input type="number" step="0.01" min="0" name="credit_limit" id="custCredit" class="form-control" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
window.CUSTOMER_CAN_MANAGE = <?= hasPermission('customer.manage') ? 'true' : 'false' ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>