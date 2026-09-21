<?php
declare(strict_types=1);
if (!defined('GIMS_APP')) { exit('Direct access denied'); }

requireLogin();

$__user    = currentUser();
$__flashes = getFlashes();
$__pageTitle = $pageTitle ?? 'Dashboard';
$__breadcrumbs = $breadcrumbs ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <title><?= e($__pageTitle) ?> &middot; <?= e(APP_SHORT_NAME) ?></title>

    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/style.css" rel="stylesheet">

    <script>
        window.GIMS = {
            baseUrl:     <?= json_encode(BASE_URL) ?>,
            assetsUrl:   <?= json_encode(ASSETS_URL) ?>,
            csrfToken:   <?= json_encode(csrfToken()) ?>,
            currency:    <?= json_encode(DEFAULT_CURRENCY) ?>,
            currentUser: <?= json_encode($__user) ?>
        };
    </script>
</head>
<body class="gims-body">

<div class="gims-app">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="gims-main">

        <?php include __DIR__ . '/navbar.php'; ?>

        <main class="gims-content" id="gimsContent">

            <?php if (!empty($__breadcrumbs)): ?>
                <nav aria-label="breadcrumb" class="gims-breadcrumb-wrap">
                    <ol class="breadcrumb gims-breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="<?= BASE_URL ?>/dashboard.php">
                                <i class="bi bi-house-door"></i> Home
                            </a>
                        </li>
                        <?php foreach ($__breadcrumbs as $label => $url): ?>
                            <?php if ($url): ?>
                                <li class="breadcrumb-item"><a href="<?= e($url) ?>"><?= e($label) ?></a></li>
                            <?php else: ?>
                                <li class="breadcrumb-item active"><?= e($label) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            <?php endif; ?>

            <?php if (!empty($__flashes)): ?>
                <div class="gims-flash-stack">
                    <?php foreach ($__flashes as $f): ?>
                        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                            <i class="bi bi-info-circle me-2"></i><?= e($f['message']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>