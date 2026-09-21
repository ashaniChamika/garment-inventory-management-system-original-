<?php
require_once __DIR__ . '/config/config.php';
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>500 &middot; Server Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="gims-auth-body">
<div class="gims-error-page">
    <div class="gims-error-card">
        <div class="gims-error-code">500</div>
        <h2>Something Went Wrong</h2>
        <p>An unexpected error occurred on our end. Our team has been notified. Please try again in a moment.</p>
        <div class="d-flex gap-2 justify-content-center">
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary"><i class="bi bi-house me-1"></i>Dashboard</a>
            <a href="javascript:location.reload()" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i>Retry</a>
        </div>
    </div>
</div>
</body>
</html>