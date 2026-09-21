<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('inventory.view');

$pageTitle    = 'Warehouse Locations';
$pageSubtitle = 'Racks, bins and storage locations';
$breadcrumbs  = ['Inventory' => null, 'Warehouses' => BASE_URL . '/warehouses/index.php', 'Locations' => null];
$pageScripts  = [ASSETS_URL . '/js/inventory.js'];

$pdo = db();
$warehouses = $pdo->query('SELECT id, name, code FROM warehouses WHERE deleted_at IS NULL ORDER BY name ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="gims-range-tabs">
        <a href="<?= BASE_URL ?>/warehouses/index.php" class="gims-range-tab">All Warehouses</a>
        <a href="<?= BASE_URL ?>/warehouses/locations.php" class="gims-range-tab active">Locations</a>
    </div>
    <?php if (hasPermission('warehouse.manage')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#locModal" onclick="openLocation()">
            <i class="bi bi-plus-lg me-1"></i> Add Location
        </button>
    <?php endif; ?>
</div>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Warehouse</label>
                <select id="locWhFilter" class="form-select">
                    <option value="">All warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" id="locSearch" class="form-control" placeholder="Code, rack, bin…">
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">All Locations</h5>
            <small class="text-muted" id="locCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Warehouse</th>
                        <th>Rack</th>
                        <th>Bin</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="locBody">
                    <tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Location Modal -->
<div class="modal fade" id="locModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="locForm">
            <div class="modal-header">
                <h5 class="modal-title">Add Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="locId" value="">
                <div class="mb-3">
                    <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                    <select name="warehouse_id" id="locWarehouse" class="form-select" required>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Rack</label>
                        <input type="text" name="rack" id="locRack" class="form-control" maxlength="50">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Bin</label>
                        <input type="text" name="bin" id="locBin" class="form-control" maxlength="50">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Code (auto if blank)</label>
                    <input type="text" name="code" id="locCode" class="form-control" maxlength="80">
                </div>
                <div class="mb-0">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" id="locDesc" class="form-control" maxlength="255">
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
window.LOCATIONS_WAREHOUSES = <?= json_encode(array_map(fn($w)=>['id'=>(int)$w['id'],'name'=>$w['name']], $warehouses)) ?>;
window.WAREHOUSE_CAN_MANAGE = <?= hasPermission('warehouse.manage') ? 'true' : 'false' ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>