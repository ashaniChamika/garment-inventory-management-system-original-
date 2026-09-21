<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('bom.manage');

$pageTitle    = 'Bill of Materials';
$pageSubtitle = 'Define what raw materials each finished product requires';
$breadcrumbs  = ['Production' => null, 'Bill of Materials' => null];
$pageScripts  = [ASSETS_URL . '/js/production.js'];

$pageActions = '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bomModal" onclick="openBom()">'
             . '<i class="bi bi-plus-lg me-1"></i> New BOM</button>';

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <input type="text" id="bomSearch" class="form-control" placeholder="BOM name, product…">
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select id="bomStatus" class="form-select">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm" id="bomReset"><i class="bi bi-arrow-clockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">All BOMs</h5>
            <small class="text-muted" id="bomCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>BOM Name</th>
                        <th>Product</th>
                        <th>Version</th>
                        <th class="text-end">Materials</th>
                        <th>Created By</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="bomBody">
                    <tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- BOM Modal -->
<div class="modal fade" id="bomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="bomForm">
            <div class="modal-header">
                <h5 class="modal-title" id="bomModalTitle">New BOM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="bomId" value="">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="bomProduct" class="form-select" required>
                            <option value="">— Select finished product —</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Version</label>
                        <input type="text" name="version" id="bomVersion" class="form-control" value="1.0" maxlength="30">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="bomStatusInput" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">BOM Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="bomName" class="form-control" required maxlength="150"
                               placeholder="e.g. Basic T-Shirt Standard BOM">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="bomNotes" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                </div>

                <label class="form-label">Materials <span class="text-danger">*</span></label>
                <div class="table-responsive">
                    <table class="table gims-table">
                        <thead>
                            <tr>
                                <th style="width:32%">Material</th>
                                <th class="text-end" style="width:13%">Qty</th>
                                <th style="width:10%">Unit</th>
                                <th class="text-end" style="width:13%">Wastage %</th>
                                <th style="width:27%">Notes</th>
                                <th style="width:5%"></th>
                            </tr>
                        </thead>
                        <tbody id="bomItems"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addBomItem"><i class="bi bi-plus"></i> Add Material</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save BOM</button>
            </div>
        </form>
    </div>
</div>

<script>
window.BOM_PRODUCTS  = <?= json_encode(array_map(fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name'],'sku'=>$r['sku']], db()->query('SELECT id, name, sku FROM products WHERE deleted_at IS NULL AND product_type="finished_garment" AND status="active" ORDER BY name ASC')->fetchAll())) ?>;
window.BOM_MATERIALS = <?= json_encode(array_map(fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name'],'sku'=>$r['sku'],'unit'=>$r['unit']], db()->query('SELECT id, name, sku, unit FROM products WHERE deleted_at IS NULL AND product_type <> "finished_garment" AND status="active" ORDER BY name ASC')->fetchAll())) ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>