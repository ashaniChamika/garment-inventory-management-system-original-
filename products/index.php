<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('product.view');

$pageTitle    = 'Products';
$pageSubtitle = 'Manage your garment product catalogue';
$breadcrumbs  = ['Inventory' => null, 'Products' => null];
$pageScripts  = [ASSETS_URL . '/js/products.js'];

$pageActions = '';
if (hasPermission('product.create')) {
    $pageActions .= '<a href="' . BASE_URL . '/products/create.php" class="btn btn-primary">'
                  . '<i class="bi bi-plus-lg me-1"></i> Add Product</a>';
}
$pageActions .= ' <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>';

/* -------- Filter data -------- */
$pdo = db();
$categories = $pdo->query('SELECT id, name, parent_id FROM categories WHERE deleted_at IS NULL ORDER BY (parent_id IS NULL) DESC, name ASC')->fetchAll();

/* -------- Small KPI summary -------- */
$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL')->fetchColumn();
$activeProducts = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status = "active"')->fetchColumn();
$totalValue = (float)$pdo->query('SELECT COALESCE(SUM(s.quantity * p.cost_price),0) FROM stock s JOIN products p ON p.id = s.product_id')->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<!-- KPI mini row -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-box-seam"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Total Products</span>
                <strong class="gims-kpi-value"><?= number_format($totalProducts) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="gims-kpi gims-kpi-success">
            <div class="gims-kpi-icon"><i class="bi bi-check2-circle"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Active</span>
                <strong class="gims-kpi-value"><?= number_format($activeProducts) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="gims-kpi gims-kpi-dark">
            <div class="gims-kpi-icon"><i class="bi bi-currency-exchange"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Inventory Value</span>
                <strong class="gims-kpi-value"><?= money($totalValue) ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Filter panel -->
<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" id="pSearch" class="form-control" placeholder="Name, SKU, code, brand…">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select id="pCategory" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>">
                            <?= $c['parent_id'] ? '— ' : '' ?><?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select id="pType" class="form-select">
                    <option value="">All types</option>
                    <option value="fabric">Fabric</option>
                    <option value="thread">Thread</option>
                    <option value="button">Button</option>
                    <option value="zipper">Zipper</option>
                    <option value="label">Label</option>
                    <option value="packaging">Packaging</option>
                    <option value="accessory">Accessory</option>
                    <option value="finished_garment">Finished Garment</option>
                    <option value="raw_material">Raw Material</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Stock</label>
                <select id="pStock" class="form-select">
                    <option value="">All stock</option>
                    <option value="low">Low stock</option>
                    <option value="out">Out of stock</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select id="pStatus" class="form-select">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-12 text-end mt-2">
                <button class="btn btn-outline-secondary btn-sm" id="pReset"><i class="bi bi-arrow-clockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Product Catalogue</h5>
            <small class="text-muted" id="pCount">Loading…</small>
        </div>
    </div>

    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th style="width:60px">Image</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Selling</th>
                        <th class="text-end">Stock</th>
                        <th>Status</th>
                        <th class="text-end" style="width:130px">Actions</th>
                    </tr>
                </thead>
                <tbody id="pBody">
                    <tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading products…</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="pPagerWrap" style="display:none">
            <small class="text-muted" id="pPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="pPager"></ul></nav>
        </div>
    </div>
</div>

<script>
window.PRODUCTS_CAN_EDIT   = <?= hasPermission('product.edit') ? 'true' : 'false' ?>;
window.PRODUCTS_CAN_DELETE = <?= hasPermission('product.delete') ? 'true' : 'false' ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>