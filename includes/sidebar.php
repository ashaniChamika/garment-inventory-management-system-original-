<?php
declare(strict_types=1);
if (!defined('GIMS_APP')) { exit('Direct access denied'); }

$__current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$__dir     = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$__user    = currentUser();

/**
 * Helper: is the current menu item active?
 */
$is = function (string $file, ?string $dir = null) use ($__current, $__dir): bool {
    if ($file === $__current) return true;
    if ($dir && $dir === $__dir) return true;
    return false;
};
?>
<aside class="gims-sidebar" id="gimsSidebar">

    <div class="gims-brand">
        <a href="<?= BASE_URL ?>/dashboard.php" class="gims-brand-link">
            <span class="gims-brand-logo"><i class="bi bi-box-seam-fill"></i></span>
            <span class="gims-brand-text">
                <strong>S & A</strong>
                <small>Garment Inventory</small>
            </span>
        </a>
    </div>

    <nav class="gims-nav">

        <p class="gims-nav-label">Main</p>
        <ul class="gims-menu">
            <li>
                <a href="<?= BASE_URL ?>/dashboard.php"
                   class="gims-menu-link <?= $is('dashboard.php') ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                </a>
            </li>
        </ul>

        <?php if (hasAnyPermission(['product.view','inventory.view','warehouse.manage'])): ?>
            <p class="gims-nav-label">Inventory</p>
            <ul class="gims-menu">
                <?php if (hasPermission('product.view')): ?>
                    <li class="gims-menu-parent">
                        <a href="#inventorySub" class="gims-menu-link has-sub" data-bs-toggle="collapse"
                           aria-expanded="<?= in_array($__dir, ['products','categories','inventory','warehouses'], true) ? 'true' : 'false' ?>">
                            <i class="bi bi-boxes"></i><span>Inventory</span>
                            <i class="bi bi-chevron-down gims-chev"></i>
                        </a>
                        <ul class="collapse gims-sub <?= in_array($__dir, ['products','categories','inventory','warehouses'], true) ? 'show' : '' ?>" id="inventorySub">
                            <li><a href="<?= BASE_URL ?>/products/index.php" class="gims-menu-link <?= $is('index.php','products') ? 'active' : '' ?>"><i class="bi bi-tag"></i>Products</a></li>
                            <li><a href="<?= BASE_URL ?>/categories/index.php" class="gims-menu-link <?= $is('index.php','categories') ? 'active' : '' ?>"><i class="bi bi-diagram-3"></i>Categories</a></li>
                            <li><a href="<?= BASE_URL ?>/products/variants.php" class="gims-menu-link <?= $is('variants.php') ? 'active' : '' ?>"><i class="bi bi-palette"></i>Product Variants</a></li>
                            <li><a href="<?= BASE_URL ?>/inventory/stock.php" class="gims-menu-link <?= $is('stock.php') ? 'active' : '' ?>"><i class="bi bi-stack"></i>Stock</a></li>
                            <li><a href="<?= BASE_URL ?>/inventory/adjustments.php" class="gims-menu-link <?= $is('adjustments.php') ? 'active' : '' ?>"><i class="bi bi-sliders"></i>Stock Adjustments</a></li>
                            <li><a href="<?= BASE_URL ?>/warehouses/index.php" class="gims-menu-link <?= $is('index.php','warehouses') ? 'active' : '' ?>"><i class="bi bi-building"></i>Warehouses</a></li>
                            <li><a href="<?= BASE_URL ?>/inventory/transfers.php" class="gims-menu-link <?= $is('transfers.php') ? 'active' : '' ?>"><i class="bi bi-arrow-left-right"></i>Stock Transfers</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

        <?php if (hasPermission('purchase.manage') || hasPermission('supplier.manage')): ?>
            <p class="gims-nav-label">Purchasing</p>
            <ul class="gims-menu">
                <li class="gims-menu-parent">
                    <a href="#purchaseSub" class="gims-menu-link has-sub" data-bs-toggle="collapse"
                       aria-expanded="<?= in_array($__dir, ['suppliers','purchases'], true) ? 'true' : 'false' ?>">
                        <i class="bi bi-cart-check"></i><span>Purchasing</span>
                        <i class="bi bi-chevron-down gims-chev"></i>
                    </a>
                    <ul class="collapse gims-sub <?= in_array($__dir, ['suppliers','purchases'], true) ? 'show' : '' ?>" id="purchaseSub">
                        <li><a href="<?= BASE_URL ?>/suppliers/index.php" class="gims-menu-link <?= $is('index.php','suppliers') ? 'active' : '' ?>"><i class="bi bi-people"></i>Suppliers</a></li>
                        <li><a href="<?= BASE_URL ?>/purchases/purchase-orders.php" class="gims-menu-link <?= $is('purchase-orders.php') ? 'active' : '' ?>"><i class="bi bi-file-earmark-text"></i>Purchase Orders</a></li>
                        <li><a href="<?= BASE_URL ?>/purchases/goods-received.php" class="gims-menu-link <?= $is('goods-received.php') ? 'active' : '' ?>"><i class="bi bi-box-arrow-in-down"></i>Goods Received</a></li>
                        <li><a href="<?= BASE_URL ?>/purchases/returns.php" class="gims-menu-link <?= $is('returns.php') ? 'active' : '' ?>"><i class="bi bi-arrow-return-left"></i>Purchase Returns</a></li>
                    </ul>
                </li>
            </ul>
        <?php endif; ?>

        <?php if (hasPermission('production.manage') || hasPermission('bom.manage')): ?>
            <p class="gims-nav-label">Production</p>
            <ul class="gims-menu">
                <li class="gims-menu-parent">
                    <a href="#prodSub" class="gims-menu-link has-sub" data-bs-toggle="collapse"
                       aria-expanded="<?= in_array($__dir, ['production','bom'], true) ? 'true' : 'false' ?>">
                        <i class="bi bi-gear-wide-connected"></i><span>Production</span>
                        <i class="bi bi-chevron-down gims-chev"></i>
                    </a>
                    <ul class="collapse gims-sub <?= in_array($__dir, ['production','bom'], true) ? 'show' : '' ?>" id="prodSub">
                        <li><a href="<?= BASE_URL ?>/production/production-orders.php" class="gims-menu-link <?= $is('production-orders.php') ? 'active' : '' ?>"><i class="bi bi-list-check"></i>Production Orders</a></li>
                        <li><a href="<?= BASE_URL ?>/production/planning.php" class="gims-menu-link <?= $is('planning.php') ? 'active' : '' ?>"><i class="bi bi-calendar3"></i>Production Planning</a></li>
                        <li><a href="<?= BASE_URL ?>/bom/index.php" class="gims-menu-link <?= $is('index.php','bom') ? 'active' : '' ?>"><i class="bi bi-diagram-2"></i>Bill of Materials</a></li>
                        <li><a href="<?= BASE_URL ?>/production/materials.php" class="gims-menu-link <?= $is('materials.php') ? 'active' : '' ?>"><i class="bi bi-box-seam"></i>Raw Materials</a></li>
                        <li><a href="<?= BASE_URL ?>/production/finished-goods.php" class="gims-menu-link <?= $is('finished-goods.php') ? 'active' : '' ?>"><i class="bi bi-bag-check"></i>Finished Goods</a></li>
                    </ul>
                </li>
            </ul>
        <?php endif; ?>

        <?php if (hasPermission('qc.manage')): ?>
            <p class="gims-nav-label">Quality</p>
            <ul class="gims-menu">
                <li class="gims-menu-parent">
                    <a href="#qcSub" class="gims-menu-link has-sub" data-bs-toggle="collapse"
                       aria-expanded="<?= $__dir === 'quality-control' ? 'true' : 'false' ?>">
                        <i class="bi bi-patch-check"></i><span>Quality Control</span>
                        <i class="bi bi-chevron-down gims-chev"></i>
                    </a>
                    <ul class="collapse gims-sub <?= $__dir === 'quality-control' ? 'show' : '' ?>" id="qcSub">
                        <li><a href="<?= BASE_URL ?>/quality-control/inspections.php" class="gims-menu-link <?= $is('inspections.php') ? 'active' : '' ?>"><i class="bi bi-clipboard-check"></i>QC Inspections</a></li>
                        <li><a href="<?= BASE_URL ?>/quality-control/defects.php" class="gims-menu-link <?= $is('defects.php') ? 'active' : '' ?>"><i class="bi bi-bug"></i>Defective Items</a></li>
                        <li><a href="<?= BASE_URL ?>/quality-control/rejected.php" class="gims-menu-link <?= $is('rejected.php') ? 'active' : '' ?>"><i class="bi bi-x-octagon"></i>Rejected Items</a></li>
                        <li><a href="<?= BASE_URL ?>/quality-control/reports.php" class="gims-menu-link <?= $is('reports.php') ? 'active' : '' ?>"><i class="bi bi-file-earmark-bar-graph"></i>QC Reports</a></li>
                    </ul>
                </li>
            </ul>
        <?php endif; ?>

        <?php if (hasPermission('sales.manage') || hasPermission('customer.manage')): ?>
            <p class="gims-nav-label">Sales</p>
            <ul class="gims-menu">
                <li class="gims-menu-parent">
                    <a href="#salesSub" class="gims-menu-link has-sub" data-bs-toggle="collapse"
                       aria-expanded="<?= in_array($__dir, ['sales','customers'], true) ? 'true' : 'false' ?>">
                        <i class="bi bi-cash-coin"></i><span>Sales</span>
                        <i class="bi bi-chevron-down gims-chev"></i>
                    </a>
                    <ul class="collapse gims-sub <?= in_array($__dir, ['sales','customers'], true) ? 'show' : '' ?>" id="salesSub">
                        <li><a href="<?= BASE_URL ?>/customers/index.php" class="gims-menu-link <?= $is('index.php','customers') ? 'active' : '' ?>"><i class="bi bi-people"></i>Customers</a></li>
                        <li><a href="<?= BASE_URL ?>/sales/sales-orders.php" class="gims-menu-link <?= $is('sales-orders.php') ? 'active' : '' ?>"><i class="bi bi-receipt"></i>Sales Orders</a></li>
                        <li><a href="<?= BASE_URL ?>/sales/invoices.php" class="gims-menu-link <?= $is('invoices.php') ? 'active' : '' ?>"><i class="bi bi-file-earmark-ruled"></i>Invoices</a></li>
                        <li><a href="<?= BASE_URL ?>/sales/delivery.php" class="gims-menu-link <?= $is('delivery.php') ? 'active' : '' ?>"><i class="bi bi-truck"></i>Delivery</a></li>
                        <li><a href="<?= BASE_URL ?>/sales/returns.php" class="gims-menu-link <?= $is('returns.php') && $__dir === 'sales' ? 'active' : '' ?>"><i class="bi bi-arrow-return-left"></i>Sales Returns</a></li>
                    </ul>
                </li>
            </ul>
        <?php endif; ?>

        <?php if (hasPermission('employee.manage') || hasPermission('attendance.manage')): ?>
            <p class="gims-nav-label">Employees</p>
            <ul class="gims-menu">
                <li class="gims-menu-parent">
                    <a href="#empSub" class="gims-menu-link has-sub" data-bs-toggle="collapse"
                       aria-expanded="<?= in_array($__dir, ['employees','attendance'], true) ? 'true' : 'false' ?>">
                        <i class="bi bi-person-badge"></i><span>Employees</span>
                        <i class="bi bi-chevron-down gims-chev"></i>
                    </a>
                    <ul class="collapse gims-sub <?= in_array($__dir, ['employees','attendance'], true) ? 'show' : '' ?>" id="empSub">
                        <li><a href="<?= BASE_URL ?>/employees/index.php" class="gims-menu-link <?= $is('index.php','employees') ? 'active' : '' ?>"><i class="bi bi-people"></i>Employees</a></li>
                        <li><a href="<?= BASE_URL ?>/employees/departments.php" class="gims-menu-link <?= $is('departments.php') ? 'active' : '' ?>"><i class="bi bi-diagram-3"></i>Departments</a></li>
                        <li><a href="<?= BASE_URL ?>/attendance/index.php" class="gims-menu-link <?= $is('index.php','attendance') ? 'active' : '' ?>"><i class="bi bi-calendar-check"></i>Attendance</a></li>
                        <li><a href="<?= BASE_URL ?>/attendance/reports.php" class="gims-menu-link <?= $is('reports.php') && $__dir === 'attendance' ? 'active' : '' ?>"><i class="bi bi-file-earmark-bar-graph"></i>Employee Reports</a></li>
                    </ul>
                </li>
            </ul>
        <?php endif; ?>

        <?php if (hasPermission('barcode.manage')): ?>
            <p class="gims-nav-label">Barcode / QR</p>
            <ul class="gims-menu">
                <li class="gims-menu-parent">
                    <a href="#barcodeSub" class="gims-menu-link has-sub" data-bs-toggle="collapse"
                       aria-expanded="<?= $__dir === 'barcode' ? 'true' : 'false' ?>">
                        <i class="bi bi-upc-scan"></i><span>Barcode / QR</span>
                        <i class="bi bi-chevron-down gims-chev"></i>
                    </a>
                    <ul class="collapse gims-sub <?= $__dir === 'barcode' ? 'show' : '' ?>" id="barcodeSub">
                        <li><a href="<?= BASE_URL ?>/barcode/generate-barcode.php" class="gims-menu-link <?= $is('generate-barcode.php') ? 'active' : '' ?>"><i class="bi bi-upc"></i>Generate Barcode</a></li>
                        <li><a href="<?= BASE_URL ?>/barcode/generate-qr.php" class="gims-menu-link <?= $is('generate-qr.php') ? 'active' : '' ?>"><i class="bi bi-qr-code"></i>Generate QR</a></li>
                        <li><a href="<?= BASE_URL ?>/barcode/scan.php" class="gims-menu-link <?= $is('scan.php') ? 'active' : '' ?>"><i class="bi bi-upc-scan"></i>Scan Product</a></li>
                    </ul>
                </li>
            </ul>
        <?php endif; ?>

        <?php if (hasPermission('reports.view')): ?>
            <p class="gims-nav-label">Reports</p>
            <ul class="gims-menu">
                <li class="gims-menu-parent">
                    <a href="#reportsSub" class="gims-menu-link has-sub" data-bs-toggle="collapse"
                       aria-expanded="<?= $__dir === 'reports' ? 'true' : 'false' ?>">
                        <i class="bi bi-file-earmark-bar-graph"></i><span>Reports</span>
                        <i class="bi bi-chevron-down gims-chev"></i>
                    </a>
                    <ul class="collapse gims-sub <?= $__dir === 'reports' ? 'show' : '' ?>" id="reportsSub">
                        <li><a href="<?= BASE_URL ?>/reports/inventory.php" class="gims-menu-link <?= $is('inventory.php') ? 'active' : '' ?>"><i class="bi bi-boxes"></i>Inventory Reports</a></li>
                        <li><a href="<?= BASE_URL ?>/reports/purchases.php" class="gims-menu-link <?= $is('purchases.php') ? 'active' : '' ?>"><i class="bi bi-cart"></i>Purchase Reports</a></li>
                        <li><a href="<?= BASE_URL ?>/reports/sales.php" class="gims-menu-link <?= $is('sales.php') ? 'active' : '' ?>"><i class="bi bi-graph-up"></i>Sales Reports</a></li>
                        <li><a href="<?= BASE_URL ?>/reports/production.php" class="gims-menu-link <?= $is('production.php') ? 'active' : '' ?>"><i class="bi bi-gear"></i>Production Reports</a></li>
                        <li><a href="<?= BASE_URL ?>/reports/stock-movement.php" class="gims-menu-link <?= $is('stock-movement.php') ? 'active' : '' ?>"><i class="bi bi-arrow-left-right"></i>Stock Movement</a></li>
                        <li><a href="<?= BASE_URL ?>/reports/profit.php" class="gims-menu-link <?= $is('profit.php') ? 'active' : '' ?>"><i class="bi bi-currency-dollar"></i>Profit Reports</a></li>
                    </ul>
                </li>
            </ul>
        <?php endif; ?>

        <p class="gims-nav-label">System</p>
        <ul class="gims-menu">
            <li>
                <a href="<?= BASE_URL ?>/notifications/index.php"
                   class="gims-menu-link <?= $__dir === 'notifications' ? 'active' : '' ?>">
                    <i class="bi bi-bell"></i><span>Notifications</span>
                </a>
            </li>
            <?php if (hasPermission('users.manage')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/users/index.php"
                       class="gims-menu-link <?= $__dir === 'users' ? 'active' : '' ?>">
                        <i class="bi bi-people"></i><span>Users &amp; Roles</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (hasPermission('settings.manage')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/settings/index.php"
                       class="gims-menu-link <?= $__dir === 'settings' ? 'active' : '' ?>">
                        <i class="bi bi-gear"></i><span>Settings</span>
                    </a>
                </li>
            <?php endif; ?>
            <li>
                <a href="<?= BASE_URL ?>/logout.php" class="gims-menu-link text-danger">
                    <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                </a>
            </li>
        </ul>

    </nav>

    <div class="gims-sidebar-footer">
        <div class="gims-side-user">
            <span class="gims-avatar sm"><?= e(strtoupper(substr($__user['name'] ?? 'U', 0, 1))) ?></span>
            <div class="gims-side-user-meta">
                <strong><?= e($__user['name'] ?? 'User') ?></strong>
                <small><?= e($__user['role_name'] ?? '') ?></small>
            </div>
        </div>
    </div>
</aside>

<div class="gims-sidebar-overlay" id="sidebarOverlay"></div>