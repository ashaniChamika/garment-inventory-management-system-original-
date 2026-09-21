<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('purchase.manage');

$pageTitle    = 'Goods Received';
$pageSubtitle = 'GRNs that update stock automatically';
$breadcrumbs  = ['Purchasing' => null, 'Goods Received' => null];
$pageScripts  = [ASSETS_URL . '/js/purchases.js'];

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <a href="<?= BASE_URL ?>/purchases/grn-create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New GRN
    </a>
</div>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="text" id="grnSearch" class="form-control" placeholder="GRN number, supplier…">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select id="grnStatus" class="form-select">
                    <option value="">All</option>
                    <option value="draft">Draft</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm" id="grnReset"><i class="bi bi-arrow-clockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">GRN Records</h5>
            <small class="text-muted" id="grnCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>GRN #</th>
                        <th>PO #</th>
                        <th>Supplier</th>
                        <th>Warehouse</th>
                        <th>Date</th>
                        <th>Invoice</th>
                        <th class="text-end">Items</th>
                        <th class="text-end">Value</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="grnBody">
                    <tr><td colspan="10" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="grnPagerWrap" style="display:none">
            <small class="text-muted" id="grnPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="grnPager"></ul></nav>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>