<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('barcode.manage');

$pdo = db();

$pageTitle    = 'Generate QR Code';
$pageSubtitle = 'Create QR codes that link to product information';
$breadcrumbs  = ['Barcode / QR' => null, 'Generate QR' => null];
$pageScripts  = [
    'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js',
    ASSETS_URL . '/js/barcode.js'
];

$products = $pdo->query(
    'SELECT p.id, p.sku, p.name, p.selling_price, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.deleted_at IS NULL AND p.status = "active"
     ORDER BY p.name ASC LIMIT 500'
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Select Product</h5></div>
            <div class="gims-card-body">
                <div class="mb-3">
                    <label class="form-label">Search</label>
                    <input type="text" id="qrSearch" class="form-control" placeholder="Name, SKU…">
                </div>
                <div class="mb-3">
                    <label class="form-label">Product</label>
                    <select id="qrSelect" class="form-select" size="12" style="height:auto">
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"
                                    data-sku="<?= e($p['sku']) ?>"
                                    data-name="<?= e($p['name']) ?>"
                                    data-price="<?= (float)$p['selling_price'] ?>"
                                    data-cat="<?= e($p['category_name'] ?: '') ?>">
                                <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">QR Size</label>
                    <select id="qrSize" class="form-select">
                        <option value="128">Small (128px)</option>
                        <option value="200" selected>Medium (200px)</option>
                        <option value="320">Large (320px)</option>
                    </select>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" id="qrGenerate"><i class="bi bi-qr-code me-1"></i> Generate QR</button>
                    <button class="btn btn-success" id="qrPrint" disabled><i class="bi bi-printer me-1"></i> Print</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title">QR Preview</h5>
                <small class="text-muted">Scan to view product details</small>
            </div>
            <div class="gims-card-body text-center">
                <div id="qrPreview" class="py-5 text-muted">
                    <i class="bi bi-qr-code" style="font-size:64px;opacity:.3"></i>
                    <p class="mt-3">No product selected</p>
                </div>
                <div id="qrInfo" class="d-none mt-3 text-start">
                    <table class="table gims-table mb-0">
                        <tr><td class="text-muted small">Product</td><td id="qrProductName" class="fw-semibold"></td></tr>
                        <tr><td class="text-muted small">SKU</td><td id="qrProductSku"></td></tr>
                        <tr><td class="text-muted small">Price</td><td id="qrProductPrice"></td></tr>
                        <tr><td class="text-muted small">Category</td><td id="qrProductCat"></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>