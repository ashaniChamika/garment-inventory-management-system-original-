<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('supplier.manage');

$pageTitle    = 'Suppliers';
$pageSubtitle = 'Manage your raw material and service suppliers';
$breadcrumbs  = ['Purchasing' => null, 'Suppliers' => null];
$pageScripts  = [ASSETS_URL . '/js/purchases.js'];

$pdo = db();
$total     = (int)$pdo->query('SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL')->fetchColumn();
$active    = (int)$pdo->query('SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL AND status = "active"')->fetchColumn();
$outstanding = (float)$pdo->query(
    "SELECT COALESCE(SUM(total - paid_amount),0) FROM purchase_orders
     WHERE status IN ('received','partially_received') AND deleted_at IS NULL"
)->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-people"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Total Suppliers</span>
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
                <span class="gims-kpi-label">Outstanding Payable</span>
                <strong class="gims-kpi-value"><?= money($outstanding) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <?php if (hasPermission('supplier.manage')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supModal" onclick="openSupplier()">
            <i class="bi bi-plus-lg me-1"></i> Add Supplier
        </button>
    <?php endif; ?>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Supplier Directory</h5>
            <small class="text-muted" id="supCount">Loading…</small>
        </div>
        <div class="d-flex gap-2">
            <input type="text" id="supSearch" class="form-control form-control-sm" placeholder="Search…" style="width:220px">
            <select id="supStatus" class="form-select form-select-sm" style="width:150px">
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
                        <th>Company</th>
                        <th>Contact</th>
                        <th>City</th>
                        <th>Payment Terms</th>
                        <th class="text-end">POs</th>
                        <th class="text-end">Purchased</th>
                        <th class="text-end">Outstanding</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="supBody">
                    <tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="supPagerWrap" style="display:none">
            <small class="text-muted" id="supPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="supPager"></ul></nav>
        </div>
    </div>
</div>

<!-- Supplier Modal -->
<div class="modal fade" id="supModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="supForm">
            <div class="modal-header">
                <h5 class="modal-title" id="supModalTitle">Add Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="supId" value="">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" id="supCompany" class="form-control" required maxlength="190">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" id="supContact" class="form-control" maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="supPhone" class="form-control" maxlength="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="supEmail" class="form-control" maxlength="190">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tax Number</label>
                        <input type="text" name="tax_number" id="supTax" class="form-control" maxlength="60">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" id="supAddress" class="form-control" maxlength="400">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">City</label>
                        <input type="text" name="city" id="supCity" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" id="supCountry" class="form-control" value="Sri Lanka" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Terms</label>
                        <input type="text" name="payment_terms" id="supTerms" class="form-control" maxlength="120" placeholder="e.g. 30 Days Credit">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Opening Balance</label>
                        <input type="number" step="0.01" name="opening_balance" id="supOpening" class="form-control" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="supStatusInput" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Bank Details</label>
                        <textarea name="bank_details" id="supBank" class="form-control" rows="2" maxlength="400"></textarea>
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
window.SUPPLIER_CAN_MANAGE = <?= hasPermission('supplier.manage') ? 'true' : 'false' ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>