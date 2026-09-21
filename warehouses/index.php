<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('inventory.view');

$pageTitle    = 'Warehouses';
$pageSubtitle = 'Manage storage facilities and locations';
$breadcrumbs  = ['Inventory' => null, 'Warehouses' => null];
$pageScripts  = [ASSETS_URL . '/js/inventory.js'];

$pdo = db();
$employees = $pdo->query('SELECT id, full_name FROM employees WHERE deleted_at IS NULL AND status = "active" ORDER BY full_name ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="gims-range-tabs">
        <a href="<?= BASE_URL ?>/warehouses/index.php" class="gims-range-tab active">All Warehouses</a>
        <a href="<?= BASE_URL ?>/warehouses/locations.php" class="gims-range-tab">Locations</a>
    </div>
    <?php if (hasPermission('warehouse.manage')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#whModal" onclick="openWarehouse()">
            <i class="bi bi-plus-lg me-1"></i> Add Warehouse
        </button>
    <?php endif; ?>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">All Warehouses</h5>
            <small class="text-muted" id="wCount">Loading…</small>
        </div>
        <input type="text" id="wSearch" class="form-control form-control-sm" placeholder="Search…" style="width:220px">
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Warehouse</th>
                        <th>Code</th>
                        <th>Manager</th>
                        <th>Contact</th>
                        <th class="text-end">Products</th>
                        <th class="text-end">Stock Value</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="wBody">
                    <tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Warehouse Modal -->
<div class="modal fade" id="whModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="whForm">
            <div class="modal-header">
                <h5 class="modal-title" id="whModalTitle">Add Warehouse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="whId" value="">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="whName" class="form-control" required maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="whCode" class="form-control" required maxlength="40" placeholder="WH-XX-01">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" id="whAddress" class="form-control" maxlength="400">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">City</label>
                        <input type="text" name="city" id="whCity" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" id="whCountry" class="form-control" value="Sri Lanka" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Manager</label>
                        <select name="manager_id" id="whManager" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= (int)$emp['id'] ?>"><?= e($emp['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="whPhone" class="form-control" maxlength="30">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="whEmail" class="form-control" maxlength="190">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" id="whStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_default" id="whDefault" value="1">
                            <label class="form-check-label" for="whDefault">Set as default warehouse</label>
                        </div>
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
window.WAREHOUSE_CAN_MANAGE = <?= hasPermission('warehouse.manage') ? 'true' : 'false' ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>