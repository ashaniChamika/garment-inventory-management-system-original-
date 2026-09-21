<?php
require_once __DIR__ . '/config/config.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>404 &middot; Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="gims-auth-body">
<div class="gims-error-page">
    <div class="gims-error-card">
        <div class="gims-error-code">404</div>
        <h2>Page Not Found</h2>
        <p>The page you are looking for doesn't exist or has been moved.</p>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary">
            <i class="bi bi-house me-1"></i>Go to Dashboard
        </a>
    </div>
</div>
</body>
</html>