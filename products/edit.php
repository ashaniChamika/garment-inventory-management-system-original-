<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('product.edit');
require_once __DIR__ . '/../includes/upload.php';

$pdo = db();
$id  = (int)get('id', 0);
if ($id <= 0) { flash('danger', 'Invalid product.'); redirect('products/index.php'); }

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { flash('danger', 'Product not found.'); redirect('products/index.php'); }

$pageTitle    = 'Edit Product';
$pageSubtitle = $product['name'];
$breadcrumbs  = ['Inventory' => null, 'Products' => BASE_URL . '/products/index.php', 'Edit' => null];

$categories = $pdo->query('SELECT id, name, parent_id FROM categories WHERE deleted_at IS NULL ORDER BY (parent_id IS NULL) DESC, name ASC')->fetchAll();
$suppliers  = $pdo->query('SELECT id, company_name FROM suppliers WHERE deleted_at IS NULL ORDER BY company_name ASC')->fetchAll();

$errors = [];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $data = [];
        foreach (['name','sku','product_code','product_type','brand','description','unit','barcode','status'] as $f) {
            $data[$f] = clean((string)post($f, ''), 2000);
        }
        $data['category_id']     = (int)post('category_id', 0);
        $data['sub_category_id'] = (int)post('sub_category_id', 0);
        $data['supplier_id']     = (int)post('supplier_id', 0);
        $data['cost_price']      = decimal_or(post('cost_price', '0'), 0);
        $data['selling_price']   = decimal_or(post('selling_price', '0'), 0);
        $data['min_stock']       = decimal_or(post('min_stock', '0'), 0);
        $data['max_stock']       = decimal_or(post('max_stock', '0'), 0);
        $data['reorder_level']   = decimal_or(post('reorder_level', '0'), 0);
        $data['has_variants']    = post('has_variants') ? 1 : 0;

        if ($data['name'] === '') $errors[] = 'Name is required.';
        if ($data['sku'] === '')  $errors[] = 'SKU is required.';
        if ($data['product_code'] === '') $errors[] = 'Product code is required.';
        if ($data['unit'] === '') $errors[] = 'Unit is required.';

        if (!$errors) {
            $chk = $pdo->prepare('SELECT id FROM products WHERE (sku = ? OR product_code = ?) AND id <> ? AND deleted_at IS NULL LIMIT 1');
            $chk->execute([$data['sku'], $data['product_code'], $id]);
            if ($chk->fetchColumn()) $errors[] = 'SKU or Product Code already exists on another product.';
        }

        $imagePath = $product['image'];

        // Replace image
        if (!empty($_FILES['image']['name'])) {
            try {
                $newPath = handleImageUpload($_FILES['image'], 'products');
                if ($newPath) {
                    deleteUploadedFile($imagePath);
                    $imagePath = $newPath;
                }
            } catch (Throwable $ex) {
                $errors[] = 'Image upload: ' . $ex->getMessage();
            }
        }

        // Remove image
        if (post('remove_image') === '1') {
            deleteUploadedFile($imagePath);
            $imagePath = null;
        }

        if (!$errors) {
            try {
                $up = $pdo->prepare(
                    'UPDATE products SET
                        sku=?, product_code=?, name=?, product_type=?, category_id=?, sub_category_id=?,
                        brand=?, description=?, unit=?, supplier_id=?, cost_price=?, selling_price=?,
                        min_stock=?, max_stock=?, reorder_level=?, barcode=?, image=?,
                        has_variants=?, status=?, updated_at=NOW()
                     WHERE id=?'
                );
                $up->execute([
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
                    $id
                ]);

                logActivity('Product Updated', 'product', $id, 'Updated product: ' . $data['name']);
                flash('success', 'Product updated successfully.');
                redirect('products/view.php?id=' . $id);
            } catch (Throwable $ex) {
                error_log('[GIMS PRODUCT UPDATE] ' . $ex->getMessage());
                $errors[] = 'Could not update product: ' . $ex->getMessage();
            }
        }

        // keep submitted values on error
        $product = array_merge($product, $data);
        $product['image'] = $imagePath;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?= csrfField() ?>

    <div class="row g-3">
        <div class="col-lg-8">

            <div class="gims-card mb-3">
                <div class="gims-card-head"><h5 class="gims-card-title">Basic Information</h5></div>
                <div class="gims-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="<?= e($product['name']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">SKU <span class="text-danger">*</span></label>
                            <input type="text" name="sku" class="form-control" required value="<?= e($product['sku']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Product Code <span class="text-danger">*</span></label>
                            <input type="text" name="product_code" class="form-control" required value="<?= e($product['product_code']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Product Type <span class="text-danger">*</span></label>
                            <select name="product_type" class="form-select" required>
                                <?php foreach ([
                                    'raw_material'=>'Raw Material','fabric'=>'Fabric','thread'=>'Thread',
                                    'button'=>'Button','zipper'=>'Zipper','label'=>'Label',
                                    'packaging'=>'Packaging Material','finished_garment'=>'Finished Garment',
                                    'accessory'=>'Accessory',
                                ] as $k => $lbl): ?>
                                    <option value="<?= $k ?>" <?= $product['product_type'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="catSelect" class="form-select">
                                <option value="">— None —</option>
                                <?php foreach ($categories as $c): if ($c['parent_id']) continue; ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= (int)$product['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
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
                                        <?= (int)$product['sub_category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" class="form-control" value="<?= e($product['brand']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <input type="text" name="unit" class="form-control" required value="<?= e($product['unit']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" class="form-select">
                                <option value="">— None —</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>" <?= (int)$product['supplier_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                        <?= e($s['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= e($product['description']) ?></textarea>
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
                            <input type="number" step="0.01" min="0" name="cost_price" class="form-control" value="<?= e($product['cost_price']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Selling Price</label>
                            <input type="number" step="0.01" min="0" name="selling_price" class="form-control" value="<?= e($product['selling_price']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" step="0.01" min="0" name="reorder_level" class="form-control" value="<?= e($product['reorder_level']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Stock</label>
                            <input type="number" step="0.01" min="0" name="min_stock" class="form-control" value="<?= e($product['min_stock']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Maximum Stock</label>
                            <input type="number" step="0.01" min="0" name="max_stock" class="form-control" value="<?= e($product['max_stock']) ?>">
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
                        <?php if (!empty($product['image'])): ?>
                            <img id="imgPreview" src="<?= UPLOADS_URL . '/' . e($product['image']) ?>" alt="">
                            <i class="bi bi-image" id="imgPlaceholder" style="display:none"></i>
                        <?php else: ?>
                            <img id="imgPreview" src="" alt="" style="display:none">
                            <i class="bi bi-image" id="imgPlaceholder"></i>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="image" id="imageInput" class="form-control form-control-sm"
                           accept="image/jpeg,image/png,image/gif,image/webp">
                    <?php if (!empty($product['image'])): ?>
                        <div class="form-check mt-2 text-start">
                            <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage">
                            <label class="form-check-label small" for="removeImage">Remove current image</label>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="gims-card mb-3">
                <div class="gims-card-head"><h5 class="gims-card-title">Options</h5></div>
                <div class="gims-card-body">
                    <div class="mb-3">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" class="form-control" value="<?= e($product['barcode']) ?>">
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="has_variants" value="1"
                               id="hasVariants" <?= $product['has_variants'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="hasVariants">Has Variants</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="isActive"
                               <?= $product['status'] === 'active' ? 'checked' : '' ?>
                               onchange="document.getElementById('statusInput').value = this.checked ? 'active' : 'inactive'">
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                    <input type="hidden" name="status" id="statusInput" value="<?= e($product['status']) ?>">
                </div>
            </div>

            <div class="gims-card">
                <div class="gims-card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary gims-btn-lg"><i class="bi bi-check2-circle me-1"></i> Update Product</button>
                    <a href="<?= BASE_URL ?>/products/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>

        </div>
    </div>
</form>

<script>
document.getElementById('catSelect')?.addEventListener('change', function () {
    const parent = this.value;
    const sel = document.getElementById('subCatSelect');
    [...sel.options].forEach(opt => {
        if (!opt.value) return;
        opt.style.display = (!parent || opt.dataset.parent === parent) ? '' : 'none';
    });
});
document.getElementById('catSelect')?.dispatchEvent(new Event('change'));

document.getElementById('imageInput')?.addEventListener('change', function () {
    const file = this.files[0]; if (!file) return;
    const img = document.getElementById('imgPreview');
    const ph  = document.getElementById('imgPlaceholder');
    img.src = URL.createObjectURL(file); img.style.display='block'; ph.style.display='none';
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