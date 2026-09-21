<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('product.create');
require_once __DIR__ . '/../includes/upload.php';

$pageTitle    = 'Add Product';
$pageSubtitle = 'Create a new item in the catalogue';
$breadcrumbs  = ['Inventory' => null, 'Products' => BASE_URL . '/products/index.php', 'Add Product' => null];

$pdo = db();
$categories = $pdo->query('SELECT id, name, parent_id FROM categories WHERE deleted_at IS NULL ORDER BY (parent_id IS NULL) DESC, name ASC')->fetchAll();
$suppliers  = $pdo->query('SELECT id, company_name FROM suppliers WHERE deleted_at IS NULL ORDER BY company_name ASC')->fetchAll();

$errors = [];
$data = [
    'name' => '', 'sku' => '', 'product_code' => '', 'product_type' => 'raw_material',
    'category_id' => 0, 'sub_category_id' => 0, 'brand' => '', 'description' => '',
    'unit' => 'pcs', 'supplier_id' => 0, 'cost_price' => '', 'selling_price' => '',
    'min_stock' => '', 'max_stock' => '', 'reorder_level' => '',
    'barcode' => '', 'status' => 'active', 'has_variants' => 0,
];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        foreach ($data as $k => $v) {
            if ($k === 'has_variants') {
                $data[$k] = post($k) ? 1 : 0;
            } else {
                $data[$k] = clean((string)post($k, ''));
            }
        }

        // numeric
        $data['category_id']     = (int)post('category_id', 0);
        $data['sub_category_id'] = (int)post('sub_category_id', 0);
        $data['supplier_id']     = (int)post('supplier_id', 0);
        $data['cost_price']      = decimal_or(post('cost_price', '0'), 0);
        $data['selling_price']   = decimal_or(post('selling_price', '0'), 0);
        $data['min_stock']       = decimal_or(post('min_stock', '0'), 0);
        $data['max_stock']       = decimal_or(post('max_stock', '0'), 0);
        $data['reorder_level']   = decimal_or(post('reorder_level', '0'), 0);

        // validate
        if ($data['name'] === '') $errors[] = 'Product name is required.';
        if ($data['sku'] === '')  $errors[] = 'SKU is required.';
        if ($data['product_code'] === '') $errors[] = 'Product code is required.';
        if ($data['unit'] === '') $errors[] = 'Unit is required.';
        if ($data['cost_price'] < 0)    $errors[] = 'Cost price cannot be negative.';
        if ($data['selling_price'] < 0) $errors[] = 'Selling price cannot be negative.';
        if ($data['reorder_level'] < 0) $errors[] = 'Reorder level cannot be negative.';

        // uniqueness
        if (!$errors) {
            $chk = $pdo->prepare('SELECT id FROM products WHERE (sku = ? OR product_code = ?) AND deleted_at IS NULL LIMIT 1');
            $chk->execute([$data['sku'], $data['product_code']]);
            if ($chk->fetchColumn()) $errors[] = 'SKU or Product Code already exists.';
        }

        // image
        $imagePath = null;
        if (!$errors && !empty($_FILES['image']['name'])) {
            try {
                $imagePath = handleImageUpload($_FILES['image'], 'products');
            } catch (Throwable $ex) {
                $errors[] = 'Image upload: ' . $ex->getMessage();
            }
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO products
                    (sku, product_code, name, product_type, category_id, sub_category_id, brand, description,
                     unit, supplier_id, cost_price, selling_price, min_stock, max_stock, reorder_level,
                     barcode, image, has_variants, status, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([
                    $data['sku'], $data['product_code'], $data['name'], $data['product_type'],
                    $data['category_id'] ?: null,
                    $data['sub_category_id'] ?: null,
                    $data['brand'] ?: null,
                    $data['description'] ?: null,
                    $data['unit'],
                    $data['supplier_id'] ?: null,
                    $data['cost_price'], $data['selling_price'],
                    $data['min_stock'], $data['max_stock'], $data['reorder_level'],
                    $data['barcode'] ?: null,
                    $imagePath,
                    $data['has_variants'],
                    $data['status'],
                    currentUserId()
                ]);
                $newId = (int)$pdo->lastInsertId();

                // Auto-create initial stock row in default warehouse (qty 0)
                $defaultWh = (int)($pdo->query("SELECT id FROM warehouses WHERE is_default = 1 AND deleted_at IS NULL LIMIT 1")->fetchColumn() ?: 1);
                if ($defaultWh > 0) {
                    $pdo->prepare('INSERT INTO stock (product_id, variant_id, warehouse_id, quantity) VALUES (?, 0, ?, 0)')
                        ->execute([$newId, $defaultWh]);
                }

                $pdo->commit();

                logActivity('Product Created', 'product', $newId, 'Created product: ' . $data['name']);
                pushNotification('New Product', "Product {$data['name']} was created.", 'info', 'product', BASE_URL . '/products/view.php?id=' . $newId);

                flash('success', 'Product created successfully.');
                redirect('products/view.php?id=' . $newId);

            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[GIMS PRODUCT CREATE] ' . $ex->getMessage());
                $errors[] = 'Could not save product: ' . $ex->getMessage();
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" id="productForm">
    <?= csrfField() ?>

    <div class="row g-3">
        <div class="col-lg-8">

            <div class="gims-card mb-3">
                <div class="gims-card-head"><h5 class="gims-card-title">Basic Information</h5></div>
                <div class="gims-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="190"
                                   value="<?= e($data['name']) ?>" placeholder="e.g. Basic Crew Neck T-Shirt">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">SKU <span class="text-danger">*</span></label>
                            <input type="text" name="sku" class="form-control" required maxlength="80"
                                   value="<?= e($data['sku']) ?>" placeholder="TS-BAS-001">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Product Code <span class="text-danger">*</span></label>
                            <input type="text" name="product_code" class="form-control" required maxlength="80"
                                   value="<?= e($data['product_code']) ?>" placeholder="PC-FG-001">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Product Type <span class="text-danger">*</span></label>
                            <select name="product_type" class="form-select" required>
                                <?php
                                $types = [
                                    'raw_material'      => 'Raw Material',
                                    'fabric'            => 'Fabric',
                                    'thread'            => 'Thread',
                                    'button'            => 'Button',
                                    'zipper'            => 'Zipper',
                                    'label'             => 'Label',
                                    'packaging'         => 'Packaging Material',
                                    'finished_garment'  => 'Finished Garment',
                                    'accessory'         => 'Accessory',
                                ];
                                foreach ($types as $k => $lbl):
                                ?>
                                    <option value="<?= $k ?>" <?= $data['product_type'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="catSelect" class="form-select">
                                <option value="">— None —</option>
                                <?php foreach ($categories as $c): if ($c['parent_id']) continue; ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= (int)$data['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sub Category</label>
                            <select name="sub_category_id" id="subCatSelect" class="form-select">
                                <option value="">— None —</option>
                                <?php foreach ($categories as $c): if (!$c['parent_id']) continue; ?>
                                    <option value="<?= (int)$c['id'] ?>" data-parent="<?= (int)$c['parent_id'] ?>"
                                        <?= (int)$data['sub_category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" class="form-control" maxlength="120" value="<?= e($data['brand']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <input type="text" name="unit" class="form-control" required maxlength="30" value="<?= e($data['unit']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" class="form-select">
                                <option value="">— None —</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>" <?= (int)$data['supplier_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                        <?= e($s['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" maxlength="2000"><?= e($data['description']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="gims-card mb-3">
                <div class="gims-card-head"><h5 class="gims-card-title">Pricing &amp; Stock Levels</h5></div>
                <div class="gims-card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Cost Price</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= e(DEFAULT_CURRENCY) ?></span>
                                <input type="number" step="0.01" min="0" name="cost_price" class="form-control"
                                       value="<?= e((string)$data['cost_price']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Selling Price</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= e(DEFAULT_CURRENCY) ?></span>
                                <input type="number" step="0.01" min="0" name="selling_price" class="form-control"
                                       value="<?= e((string)$data['selling_price']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" step="0.01" min="0" name="reorder_level" class="form-control"
                                   value="<?= e((string)$data['reorder_level']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Stock</label>
                            <input type="number" step="0.01" min="0" name="min_stock" class="form-control"
                                   value="<?= e((string)$data['min_stock']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Maximum Stock</label>
                            <input type="number" step="0.01" min="0" name="max_stock" class="form-control"
                                   value="<?= e((string)$data['max_stock']) ?>">
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="col-lg-4">

            <div class="gims-card mb-3">
                <div class="gims-card-head"><h5 class="gims-card-title">Product Image</h5></div>
                <div class="gims-card-body text-center">
                    <div class="gims-upload-preview mb-2">
                        <img id="imgPreview" src="" alt="" style="display:none">
                        <i class="bi bi-image" id="imgPlaceholder"></i>
                    </div>
                    <input type="file" name="image" id="imageInput" class="form-control form-control-sm"
                           accept="image/jpeg,image/png,image/gif,image/webp">
                    <small class="text-muted d-block mt-2">JPG / PNG / WEBP · max 2 MB</small>
                </div>
            </div>

            <div class="gims-card mb-3">
                <div class="gims-card-head"><h5 class="gims-card-title">Options</h5></div>
                <div class="gims-card-body">
                    <div class="mb-3">
                        <label class="form-label">Barcode (optional)</label>
                        <input type="text" name="barcode" class="form-control" maxlength="120" value="<?= e($data['barcode']) ?>">
                        <small class="text-muted">Leave blank to auto-generate.</small>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="has_variants" id="hasVariants"
                               value="1" <?= $data['has_variants'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="hasVariants">Has Variants (size/color)</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="isActive"
                               value="1" <?= $data['status'] === 'active' ? 'checked' : '' ?>
                               onchange="document.getElementById('statusInput').value = this.checked ? 'active' : 'inactive'">
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                    <input type="hidden" name="status" id="statusInput" value="<?= e($data['status']) ?>">
                </div>
            </div>

            <div class="gims-card">
                <div class="gims-card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary gims-btn-lg">
                        <i class="bi bi-check2-circle me-1"></i> Save Product
                    </button>
                    <a href="<?= BASE_URL ?>/products/index.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>

        </div>
    </div>
</form>

<script>
/* Sub-category filter */
document.getElementById('catSelect')?.addEventListener('change', function () {
    const parent = this.value;
    const sel = document.getElementById('subCatSelect');
    [...sel.options].forEach(opt => {
        if (!opt.value) return;
        opt.style.display = (!parent || opt.dataset.parent === parent) ? '' : 'none';
    });
    const cur = sel.selectedOptions[0];
    if (cur && cur.style.display === 'none') sel.value = '';
});
document.getElementById('catSelect')?.dispatchEvent(new Event('change'));

/* Image preview */
document.getElementById('imageInput')?.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { alert('File too large (max 2 MB).'); this.value = ''; return; }
    const img = document.getElementById('imgPreview');
    const ph  = document.getElementById('imgPlaceholder');
    img.src = URL.createObjectURL(file);
    img.style.display = 'block';
    ph.style.display  = 'none';
});
</script>

<style>
.gims-upload-preview {
    width: 100%; height: 180px;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    display: grid; place-items: center;
    background: #f8fafc;
    overflow: hidden;
    color: #94a3b8;
    font-size: 42px;
}
.gims-upload-preview img { width: 100%; height: 100%; object-fit: contain; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>