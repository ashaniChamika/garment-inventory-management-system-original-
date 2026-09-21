<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('barcode.manage');

$pdo = db();
$id  = (int)get('id', 0);

$pageTitle    = 'Generate Barcode';
$pageSubtitle = 'Create and print product barcodes';
$breadcrumbs  = ['Barcode / QR' => null, 'Generate Barcode' => null];
$pageScripts  = [
    'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js',
    ASSETS_URL . '/js/barcode.js'
];

$products = $pdo->query(
    'SELECT p.id, p.sku, p.name, p.barcode, p.selling_price, c.name AS category_name
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
                    <label class="form-label">Search Product</label>
                    <input type="text" id="prodSearch" class="form-control" placeholder="Name, SKU…">
                </div>
                <div class="mb-3">
                    <label class="form-label">Product</label>
                    <select id="prodSelect" class="form-select" size="12" style="height:auto">
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" data-sku="<?= e($p['sku']) ?>" data-name="<?= e($p['name']) ?>" data-code="<?= e($p['barcode']) ?>" data-cat="<?= e($p['category_name'] ?: '') ?>">
                                <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Label Quantity</label>
                    <input type="number" id="labelQty" class="form-control" value="1" min="1" max="100">
                </div>
                <div class="mb-3">
                    <label class="form-label">Barcode Format</label>
                    <select id="barFormat" class="form-select">
                        <option value="CODE128">CODE128</option>
                        <option value="EAN13">EAN13 (12-13 digits)</option>
                        <option value="UPC">UPC (11-12 digits)</option>
                        <option value="CODE39">CODE39</option>
                    </select>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" id="generateBtn"><i class="bi bi-upc-scan me-1"></i> Generate</button>
                    <button class="btn btn-success" id="printBtn" disabled><i class="bi bi-printer me-1"></i> Print Labels</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Barcode Preview</h5>
                <small class="text-muted">Select a product to generate its barcode</small>
            </div>
            <div class="gims-card-body">
                <div id="preview" class="text-center py-5 text-muted">
                    <i class="bi bi-upc-scan" style="font-size:64px;opacity:.3"></i>
                    <p class="mt-3">No product selected</p>
                </div>

                <div id="labelsContainer" class="d-none gims-label-grid">
                    <!-- Generated labels will go here -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.gims-label-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
}
.gims-label {
    border: 1px dashed #cbd5e1;
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    background: #fff;
    page-break-inside: avoid;
}
.gims-label .brand { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
.gims-label .name { font-size: 11.5px; font-weight: 700; margin: 4px 0; line-height: 1.2; }
.gims-label .sku { font-size: 10px; color: #64748b; }
.gims-label svg { max-width: 100%; }

@media print {
    body * { visibility: hidden; }
    .gims-label-grid, .gims-label-grid * { visibility: visible; }
    .gims-label-grid { position: absolute; left: 0; top: 0; width: 100%; }
    .gims-label { border: none; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>