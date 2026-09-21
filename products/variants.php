<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('product.view');

$pdo = db();
$id  = (int)get('id', 0);
if ($id <= 0) { flash('danger', 'Invalid product.'); redirect('products/index.php'); }

$stmt = $pdo->prepare('SELECT id, name, sku, unit, cost_price, selling_price, has_variants FROM products WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { flash('danger', 'Product not found.'); redirect('products/index.php'); }

$pageTitle    = 'Product Variants';
$pageSubtitle = $product['name'] . ' (' . $product['sku'] . ')';
$breadcrumbs  = ['Inventory' => null, 'Products' => BASE_URL . '/products/index.php',
                 $product['name'] => BASE_URL . '/products/view.php?id=' . $id, 'Variants' => null];
$pageScripts  = [ASSETS_URL . '/js/products.js'];

// Handle POST (create/update variant)
$errors = [];
if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Security token expired.';
    } else {
        $action = post('action', 'create');
        $vid    = (int)post('variant_id', 0);

        $size       = clean((string)post('size', ''), 30);
        $color      = clean((string)post('color', ''), 50);
        $sku        = clean((string)post('sku', ''), 100);
        $barcode    = clean((string)post('barcode', ''), 120);
        $cost       = decimal_or(post('cost_price', $product['cost_price']), (float)$product['cost_price']);
        $selling    = decimal_or(post('selling_price', $product['selling_price']), (float)$product['selling_price']);
        $status     = post('status', 'active') === 'inactive' ? 'inactive' : 'active';

        if ($size === '') $errors[] = 'Size is required.';
        if ($color === '') $errors[] = 'Color is required.';
        if ($sku === '')  $sku = $product['sku'] . '-' . strtoupper(substr(slugify($color),0,3)) . '-' . strtoupper($size);

        // uniqueness
        if (!$errors) {
            $chk = $pdo->prepare('SELECT id FROM product_variants WHERE sku = ? AND id <> ? LIMIT 1');
            $chk->execute([$sku, $vid]);
            if ($chk->fetchColumn()) $errors[] = "SKU \"$sku\" is already used.";
        }

        if (!$errors) {
            try {
                if ($action === 'update' && $vid > 0) {
                    $up = $pdo->prepare(
                        'UPDATE product_variants SET size=?, color=?, sku=?, barcode=?, cost_price=?, selling_price=?, status=?, updated_at=NOW()
                         WHERE id=? AND product_id=?'
                    );
                    $up->execute([$size, $color, $sku, $barcode ?: null, $cost, $selling, $status, $vid, $id]);
                    logActivity('Variant Updated', 'product', $id, "Updated variant $sku");
                    flash('success', 'Variant updated.');
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO product_variants (product_id, size, color, sku, barcode, cost_price, selling_price, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $ins->execute([$id, $size, $color, $sku, $barcode ?: null, $cost, $selling, $status]);

                    // initialize stock row for each warehouse
                    $whs = $pdo->query('SELECT id FROM warehouses WHERE deleted_at IS NULL AND status = "active"')->fetchAll(PDO::FETCH_COLUMN);
                    $insStock = $pdo->prepare('INSERT IGNORE INTO stock (product_id, variant_id, warehouse_id, quantity) VALUES (?, ?, ?, 0)');
                    $newVid = (int)$pdo->lastInsertId();
                    foreach ($whs as $whId) { $insStock->execute([$id, $newVid, (int)$whId]); }

                    // ensure product has_variants flag
                    $pdo->prepare('UPDATE products SET has_variants = 1 WHERE id = ?')->execute([$id]);

                    logActivity('Variant Created', 'product', $id, "Created variant $sku");
                    flash('success', 'Variant created.');
                }
                redirect('products/variants.php?id=' . $id);
            } catch (Throwable $ex) {
                error_log('[GIMS VARIANT SAVE] ' . $ex->getMessage());
                $errors[] = 'Could not save variant: ' . $ex->getMessage();
            }
        }
    }
}

// Handle delete
if (get('delete')) {
    requirePermission('product.delete');
    if (!verifyCsrf(get('token'))) {
        flash('danger', 'Invalid token.');
    } else {
        $vid = (int)get('delete');
        $chk = $pdo->prepare('SELECT sku FROM product_variants WHERE id = ? AND product_id = ?');
        $chk->execute([$vid, $id]);
        if ($sku = $chk->fetchColumn()) {
            $pdo->prepare('UPDATE product_variants SET deleted_at = NOW(), status = "inactive" WHERE id = ?')->execute([$vid]);
            logActivity('Variant Deleted', 'product', $id, "Deleted variant $sku");
            flash('success', 'Variant deleted.');
        }
    }
    redirect('products/variants.php?id=' . $id);
}

// Load variants
$vstmt = $pdo->prepare(
    'SELECT v.*, COALESCE((SELECT SUM(quantity) FROM stock WHERE variant_id = v.id), 0) AS stock_qty
     FROM product_variants v
     WHERE v.product_id = ? AND v.deleted_at IS NULL
     ORDER BY v.color ASC, v.size ASC'
);
$vstmt->execute([$id]);
$variants = $vstmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title" id="formTitle">Add Variant</h5></div>
            <div class="gims-card-body">
                <form method="post" id="variantForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" id="vAction" value="create">
                    <input type="hidden" name="variant_id" id="vId" value="">

                    <div class="mb-3">
                        <label class="form-label">Size <span class="text-danger">*</span></label>
                        <input type="text" name="size" id="vSize" class="form-control" list="sizeList" required maxlength="30">
                        <datalist id="sizeList">
                            <option value="XS"><option value="S"><option value="M"><option value="L">
                            <option value="XL"><option value="XXL"><option value="XXXL">
                            <option value="28"><option value="30"><option value="32"><option value="34"><option value="36">
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color <span class="text-danger">*</span></label>
                        <input type="text" name="color" id="vColor" class="form-control" list="colorList" required maxlength="50">
                        <datalist id="colorList">
                            <option value="Black"><option value="White"><option value="Red">
                            <option value="Blue"><option value="Green"><option value="Navy">
                            <option value="Grey"><option value="Charcoal"><option value="Indigo">
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" id="vSku" class="form-control" maxlength="100" placeholder="Auto-generated if blank">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" id="vBarcode" class="form-control" maxlength="120">
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label">Cost</label>
                            <input type="number" step="0.01" min="0" name="cost_price" id="vCost" class="form-control"
                                   value="<?= e($product['cost_price']) ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Selling</label>
                            <input type="number" step="0.01" min="0" name="selling_price" id="vSelling" class="form-control"
                                   value="<?= e($product['selling_price']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="vStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save Variant</button>
                        <button type="button" class="btn btn-outline-secondary" id="resetForm">Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title">All Variants</h5>
                <span class="badge badge-soft-primary"><?= count($variants) ?> variant(s)</span>
            </div>
            <div class="gims-card-body p-0">
                <?php if (empty($variants)): ?>
                    <div class="gims-empty"><i class="bi bi-palette"></i><p>No variants yet. Add your first size/colour combination.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr>
                                <th>Size</th><th>Color</th><th>SKU</th>
                                <th class="text-end">Cost</th><th class="text-end">Selling</th>
                                <th class="text-end">Stock</th><th>Status</th><th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($variants as $v): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($v['size']) ?></td>
                                <td><?= e($v['color']) ?></td>
                                <td><code><?= e($v['sku']) ?></code></td>
                                <td class="text-end"><?= money($v['cost_price']) ?></td>
                                <td class="text-end"><?= money($v['selling_price']) ?></td>
                                <td class="text-end fw-semibold"><?= qty($v['stock_qty']) ?></td>
                                <td><?= statusBadge($v['status']) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary edit-variant"
                                        data-id="<?= (int)$v['id'] ?>"
                                        data-size="<?= e($v['size']) ?>"
                                        data-color="<?= e($v['color']) ?>"
                                        data-sku="<?= e($v['sku']) ?>"
                                        data-barcode="<?= e($v['barcode']) ?>"
                                        data-cost="<?= e($v['cost_price']) ?>"
                                        data-selling="<?= e($v['selling_price']) ?>"
                                        data-status="<?= e($v['status']) ?>"
                                        title="Edit"><i class="bi bi-pencil"></i></button>
                                    <?php if (hasPermission('product.delete')): ?>
                                        <a class="btn btn-sm btn-outline-danger"
                                           href="?id=<?= $id ?>&delete=<?= (int)$v['id'] ?>&token=<?= e(csrfToken()) ?>"
                                           data-confirm="Delete this variant?"><i class="bi bi-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.edit-variant').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('formTitle').textContent = 'Edit Variant';
        document.getElementById('vAction').value = 'update';
        document.getElementById('vId').value     = btn.dataset.id;
        document.getElementById('vSize').value   = btn.dataset.size;
        document.getElementById('vColor').value  = btn.dataset.color;
        document.getElementById('vSku').value    = btn.dataset.sku;
        document.getElementById('vBarcode').value= btn.dataset.barcode || '';
        document.getElementById('vCost').value   = btn.dataset.cost;
        document.getElementById('vSelling').value= btn.dataset.selling;
        document.getElementById('vStatus').value = btn.dataset.status;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
document.getElementById('resetForm')?.addEventListener('click', () => {
    document.getElementById('variantForm').reset();
    document.getElementById('formTitle').textContent = 'Add Variant';
    document.getElementById('vAction').value = 'create';
    document.getElementById('vId').value = '';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>