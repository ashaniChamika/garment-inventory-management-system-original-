<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('barcode.manage');

$pageTitle    = 'Scan Product';
$pageSubtitle = 'Scan or enter a barcode to look up a product';
$breadcrumbs  = ['Barcode / QR' => null, 'Scan Product' => null];
$pageScripts  = [ASSETS_URL . '/js/barcode.js'];

include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Scan Barcode</h5></div>
            <div class="gims-card-body">
                <div class="input-group input-group-lg mb-3">
                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                    <input type="text" id="scanInput" class="form-control" placeholder="Scan or type barcode/SKU and press Enter" autofocus>
                    <button class="btn btn-primary" id="lookupBtn"><i class="bi bi-search"></i> Look up</button>
                </div>
                <div class="form-text">Focus stays in this field. USB barcode scanners work as keyboards — just scan away.</div>
            </div>
        </div>

        <div class="gims-card" id="resultCard" style="display:none">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Product Found</h5>
                <span class="badge badge-soft-success" id="resultType">—</span>
            </div>
            <div class="gims-card-body">
                <h4 class="mb-1" id="rName"></h4>
                <p class="text-muted small mb-3" id="rSku"></p>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="gims-kpi gims-kpi-primary">
                            <div class="gims-kpi-icon"><i class="bi bi-tag"></i></div>
                            <div class="gims-kpi-body">
                                <span class="gims-kpi-label">Unit</span>
                                <strong class="gims-kpi-value" id="rUnit">—</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="gims-kpi gims-kpi-success">
                            <div class="gims-kpi-icon"><i class="bi bi-currency-exchange"></i></div>
                            <div class="gims-kpi-body">
                                <span class="gims-kpi-label">Selling Price</span>
                                <strong class="gims-kpi-value" id="rPrice">—</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="gims-kpi gims-kpi-info">
                            <div class="gims-kpi-icon"><i class="bi bi-palette"></i></div>
                            <div class="gims-kpi-body">
                                <span class="gims-kpi-label">Variant</span>
                                <strong class="gims-kpi-value" id="rVariant">—</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="#" id="rView" class="btn btn-primary"><i class="bi bi-box-arrow-up-right me-1"></i> Open Product</a>
                    <a href="#" id="rBarcode" class="btn btn-outline-secondary"><i class="bi bi-upc me-1"></i> Generate Label</a>
                </div>
            </div>
        </div>

        <div class="alert alert-danger d-none" id="resultError"></div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>