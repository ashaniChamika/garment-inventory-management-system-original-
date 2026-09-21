<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('settings.manage');

$pageTitle    = 'System Settings';
$pageSubtitle = 'Configure your GIMS installation';
$breadcrumbs  = ['Settings' => null];
$pageScripts  = [ASSETS_URL . '/js/settings.js'];

$pdo = db();
$rows = $pdo->query('SELECT setting_key, setting_value, setting_group FROM settings ORDER BY setting_group, setting_key')->fetchAll();

$settings = [];
$grouped = [];
foreach ($rows as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
    $grouped[$r['setting_group']][] = $r;
}

include __DIR__ . '/../includes/header.php';
?>

<form id="settingsForm" enctype="multipart/form-data">
    <?= csrfField() ?>

    <div class="row g-3">
        <div class="col-lg-3">
            <div class="gims-card">
                <div class="gims-card-head"><h5 class="gims-card-title">Sections</h5></div>
                <div class="gims-card-body p-0">
                    <nav class="gims-settings-nav">
                        <a href="#general" class="active"><i class="bi bi-building me-2"></i>General</a>
                        <a href="#inventory"><i class="bi bi-boxes me-2"></i>Inventory</a>
                        <a href="#numbering"><i class="bi bi-hash me-2"></i>Numbering</a>
                        <a href="#appearance"><i class="bi bi-palette me-2"></i>Appearance</a>
                        <a href="#system"><i class="bi bi-cpu me-2"></i>System</a>
                    </nav>
                </div>
            </div>
        </div>

        <div class="col-lg-9">

            <!-- GENERAL -->
            <div class="gims-card mb-3" id="general">
                <div class="gims-card-head"><h5 class="gims-card-title">General</h5></div>
                <div class="gims-card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="settings[company_name]" class="form-control" value="<?= e($settings['company_name'] ?? '') ?>" maxlength="190">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Short Name</label>
                            <input type="text" name="settings[company_short_name]" class="form-control" value="<?= e($settings['company_short_name'] ?? 'GIMS') ?>" maxlength="40">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <input type="text" name="settings[company_address]" class="form-control" value="<?= e($settings['company_address'] ?? '') ?>" maxlength="400">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="settings[company_phone]" class="form-control" value="<?= e($settings['company_phone'] ?? '') ?>" maxlength="30">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="settings[company_email]" class="form-control" value="<?= e($settings['company_email'] ?? '') ?>" maxlength="190">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tax Number</label>
                            <input type="text" name="settings[company_tax_number]" class="form-control" value="<?= e($settings['company_tax_number'] ?? '') ?>" maxlength="60">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Currency Symbol</label>
                            <input type="text" name="settings[currency_symbol]" class="form-control" value="<?= e($settings['currency_symbol'] ?? 'Rs.') ?>" maxlength="10">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Currency Code</label>
                            <input type="text" name="settings[currency_code]" class="form-control" value="<?= e($settings['currency_code'] ?? 'LKR') ?>" maxlength="10">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tax Rate (%)</label>
                            <input type="number" step="0.01" min="0" name="settings[tax_rate]" class="form-control" value="<?= e($settings['tax_rate'] ?? '0') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date Format</label>
                            <select name="settings[date_format]" class="form-select">
                                <?php foreach (['Y-m-d' => 'YYYY-MM-DD', 'd/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($settings['date_format'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Company Logo</label>
                            <input type="file" name="company_logo" class="form-control" accept="image/*">
                            <?php if (!empty($settings['company_logo'])): ?>
                                <small class="text-muted d-block mt-1">Current: <?= e($settings['company_logo']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INVENTORY -->
            <div class="gims-card mb-3" id="inventory">
                <div class="gims-card-head"><h5 class="gims-card-title">Inventory</h5></div>
                <div class="gims-card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Low Stock Threshold</label>
                            <input type="number" step="0.01" min="0" name="settings[low_stock_threshold]" class="form-control" value="<?= e($settings['low_stock_threshold'] ?? '10') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Default Warehouse ID</label>
                            <input type="number" min="1" name="settings[default_warehouse_id]" class="form-control" value="<?= e($settings['default_warehouse_id'] ?? '1') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">Allow Negative Stock</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="allowNeg"
                                       <?= ($settings['allow_negative_stock'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <input type="hidden" name="settings[allow_negative_stock]" id="allowNegInput" value="<?= e($settings['allow_negative_stock'] ?? '0') ?>">
                                <label class="form-check-label" for="allowNeg">Enable negative stock</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Records Per Page</label>
                            <input type="number" min="5" max="100" name="settings[records_per_page]" class="form-control" value="<?= e($settings['records_per_page'] ?? '15') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- NUMBERING -->
            <div class="gims-card mb-3" id="numbering">
                <div class="gims-card-head"><h5 class="gims-card-title">Document Numbering</h5></div>
                <div class="gims-card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Invoice Prefix</label>
                            <input type="text" name="settings[invoice_prefix]" class="form-control" value="<?= e($settings['invoice_prefix'] ?? 'INV') ?>" maxlength="10">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PO Prefix</label>
                            <input type="text" name="settings[po_prefix]" class="form-control" value="<?= e($settings['po_prefix'] ?? 'PO') ?>" maxlength="10">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">SO Prefix</label>
                            <input type="text" name="settings[so_prefix]" class="form-control" value="<?= e($settings['so_prefix'] ?? 'SO') ?>" maxlength="10">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Production Prefix</label>
                            <input type="text" name="settings[production_prefix]" class="form-control" value="<?= e($settings['production_prefix'] ?? 'PRD') ?>" maxlength="10">
                        </div>
                    </div>
                </div>
            </div>

            <!-- APPEARANCE -->
            <div class="gims-card mb-3" id="appearance">
                <div class="gims-card-head"><h5 class="gims-card-title">Appearance</h5></div>
                <div class="gims-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">System Theme</label>
                            <select name="settings[system_theme]" class="form-select">
                                <option value="light" <?= ($settings['system_theme'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option>
                                <option value="dark"  <?= ($settings['system_theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sidebar Theme</label>
                            <select name="settings[sidebar_theme]" class="form-select">
                                <option value="dark"  <?= ($settings['sidebar_theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark Navy</option>
                                <option value="light" <?= ($settings['sidebar_theme'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SYSTEM -->
            <div class="gims-card mb-3" id="system">
                <div class="gims-card-head"><h5 class="gims-card-title">System</h5></div>
                <div class="gims-card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="maintenance"
                               <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <input type="hidden" name="settings[maintenance_mode]" id="maintenanceInput" value="<?= e($settings['maintenance_mode'] ?? '0') ?>">
                        <label class="form-check-label" for="maintenance">Enable maintenance mode</label>
                    </div>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Warning:</strong> Enabling maintenance mode blocks all non-admin access.
                    </div>
                </div>
            </div>

            <div class="gims-card">
                <div class="gims-card-body d-flex justify-content-end gap-2">
                    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save Settings</button>
                </div>
            </div>

        </div>
    </div>
</form>

<style>
.gims-settings-nav { display: flex; flex-direction: column; }
.gims-settings-nav a {
    padding: 12px 18px;
    border-left: 3px solid transparent;
    color: var(--gims-navy-2);
    text-decoration: none;
    font-size: 13.5px;
    transition: all .18s ease;
}
.gims-settings-nav a:hover { background: #f8fafc; color: var(--gims-primary); }
.gims-settings-nav a.active {
    background: #eff6ff;
    color: var(--gims-primary);
    border-left-color: var(--gims-primary);
    font-weight: 600;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>