<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Access Denied';
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>403 &middot; Access Denied</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="gims-auth-body">
<div class="gims-error-page">
    <div class="gims-error-card">
        <div class="gims-error-code">403</div>
        <h2>Access Denied</h2>
        <p>You do not have permission to access this page. Please contact your system administrator if you believe this is a mistake.</p>
        <div class="d-flex gap-2 justify-content-center">
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary"><i class="bi bi-house me-1"></i>Go to Dashboard</a>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-right me-1"></i>Sign Out</a>
        </div>
    </div>
</div>
</body>
</html>