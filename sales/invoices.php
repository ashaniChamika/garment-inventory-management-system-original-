<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('sales.manage');

$pageTitle    = 'Invoices';
$pageSubtitle = 'All customer invoices and payments';
$breadcrumbs  = ['Sales' => null, 'Invoices' => null];
$pageScripts  = [ASSETS_URL . '/js/sales.js'];

$pageActions = '<a href="' . BASE_URL . '/sales/invoice-create.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Invoice</a>';

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" id="invSearch" class="form-control" placeholder="Invoice #, customer…">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select id="invStatus" class="form-select">
                    <option value="">All</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                    <option value="overdue">Overdue</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" id="invFrom" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" id="invTo" class="form-control">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm" id="invReset"><i class="bi bi-arrow-clockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Invoice Register</h5>
            <small class="text-muted" id="invCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>SO #</th>
                        <th>Date</th>
                        <th>Due</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="invBody">
                    <tr><td colspan="10" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="invPagerWrap" style="display:none">
            <small class="text-muted" id="invPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="invPager"></ul></nav>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form class="modal-content" id="payForm">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="payInvId">
                <div class="mb-3">
                    <label class="form-label">Invoice</label>
                    <input type="text" id="payInvNo" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Outstanding</label>
                    <input type="text" id="payBalance" class="form-control" readonly>
                </div>
                <div class="mb-0">
                    <label class="form-label">Payment Amount <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="payAmount" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i> Record</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>