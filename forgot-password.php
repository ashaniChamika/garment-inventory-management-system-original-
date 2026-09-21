<?php
require_once __DIR__ . '/config/config.php';
requireGuest();

$errors  = [];
$success = null;
$email   = '';

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $email = strtolower(clean((string)post('email', ''), 190));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $token = createPasswordResetToken($email);

            // Always show the same message so we don't leak account existence.
            $success = 'If an account exists for that email, a reset link has been generated.';

            if ($token) {
                $link = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' .
                        ($_SERVER['HTTP_HOST'] ?? 'localhost') .
                        BASE_URL . '/reset-password.php?token=' . urlencode($token);

                // In production this would be emailed. For local dev we display it.
                $success .= '<br><br><span class="small">Development link:</span><br>'
                          . '<a class="small" href="' . e($link) . '">' . e($link) . '</a>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password &middot; GIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="gims-auth-body">
<div class="gims-auth-wrap">
    <div class="gims-auth-card">
        <div class="gims-auth-side">
            <div class="gims-auth-side-inner">
                <div class="gims-brand-logo xl"><i class="bi bi-key"></i></div>
                <h2>Password<br>recovery</h2>
                <p>We'll help you regain access to your GIMS account securely.</p>
            </div>
        </div>
        <div class="gims-auth-form">
            <h3 class="mb-1">Forgot password?</h3>
            <p class="text-muted small">Enter the email associated with your account.</p>

            <?php if ($errors): ?>
                <div class="alert alert-danger py-2 small">
                    <?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success py-2 small"><?= $success /* already escaped above */ ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label">Email address</label>
                    <input type="email" name="email" class="form-control" required value="<?= e($email) ?>">
                </div>
                <button class="btn btn-primary w-100 gims-btn-lg" type="submit">
                    <i class="bi bi-send me-1"></i> Send Reset Link
                </button>
            </form>

            <div class="text-center small text-muted mt-3">
                <a href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left"></i> Back to login</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>