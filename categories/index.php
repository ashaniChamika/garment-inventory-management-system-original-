<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('product.view');

$pageTitle    = 'Categories';
$pageSubtitle = 'Organise your catalogue with categories and sub-categories';
$breadcrumbs  = ['Inventory' => null, 'Categories' => null];
$pageScripts  = [ASSETS_URL . '/js/products.js'];

$pageActions = '';
if (hasPermission('product.create')) {
    $pageActions = '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" onclick="resetCatForm()">'
                 . '<i class="bi bi-plus-lg me-1"></i> Add Category</button>';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">All Categories</h5>
            <small class="text-muted" id="catCount">Loading…</small>
        </div>
        <div class="d-flex gap-2">
            <input type="text" class="form-control form-control-sm" id="catSearch" placeholder="Search…" style="width:220px">
            <select class="form-select form-select-sm" id="catFilterParent" style="width:170px">
                <option value="">All levels</option>
                <option value="root">Top-level only</option>
            </select>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Parent</th>
                        <th class="text-end">Products</th>
                        <th class="text-end">Sub-cats</th>
                        <th>Status</th>
                        <th class="text-end" style="width:130px">Actions</th>
                    </tr>
                </thead>
                <tbody id="catBody">
                    <tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="catForm">
            <div class="modal-header">
                <h5 class="modal-title" id="catModalTitle">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="catId" value="">
                <div class="mb-3">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="catName" class="form-control" required maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label">Parent Category</label>
                    <select name="parent_id" id="catParent" class="form-select">
                        <option value="">— None (top level) —</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="catDescription" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label">Status</label>
                    <select name="status" id="catStatus" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save</button>
            </div>
        </form>
    </div>
</div>

<script>
window.CATEGORIES_CAN_EDIT   = <?= hasPermission('product.edit') ? 'true' : 'false' ?>;
window.CATEGORIES_CAN_DELETE = <?= hasPermission('product.delete') ? 'true' : 'false' ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>