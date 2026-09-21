<?php
require_once __DIR__ . '/config/config.php';
requireGuest();

$token  = clean((string)get('token', post('token', '')), 120);
$errors = [];
$done   = false;

$userId = $token ? validateResetToken($token) : null;

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Security token expired. Please try again.';
    } elseif (!$userId) {
        $errors[] = 'This reset link is invalid or has expired.';
    } else {
        $password = (string)post('password', '');
        $confirm  = (string)post('password_confirm', '');

        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            if (resetUserPassword($userId, $password)) {
                logActivity('Password Reset', 'auth', $userId, 'User reset password via token');
                flash('success', 'Your password has been reset. Please sign in.');
                redirect('login.php');
            } else {
                $errors[] = 'We could not reset your password. Please try again.';
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
    <title>Reset Password &middot; GIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="gims-auth-body">
<div class="gims-auth-wrap">
    <div class="gims-auth-card">
        <div class="gims-auth-side">
            <div class="gims-auth-side-inner">
                <div class="gims-brand-logo xl"><i class="bi bi-shield-lock"></i></div>
                <h2>Set a new<br>password</h2>
                <p>Choose a strong password you haven't used before.</p>
            </div>
        </div>
        <div class="gims-auth-form">
            <h3 class="mb-1">Reset your password</h3>
            <p class="text-muted small">Enter your new credentials below.</p>

            <?php if ($errors): ?>
                <div class="alert alert-danger py-2 small">
                    <?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$userId && !isPost()): ?>
                <div class="alert alert-warning py-2 small">
                    This reset link is invalid or has expired.
                    <a href="<?= BASE_URL ?>/forgot-password.php">Request a new one</a>.
                </div>
            <?php endif; ?>

            <?php if ($userId): ?>
            <form method="post" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="mb-3">
                    <label class="form-label">New password</label>
                    <input type="password" name="password" class="form-control" required minlength="8">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm new password</label>
                    <input type="password" name="password_confirm" class="form-control" required minlength="8">
                </div>
                <button class="btn btn-primary w-100 gims-btn-lg" type="submit">
                    <i class="bi bi-check2-circle me-1"></i> Reset Password
                </button>
            </form>
            <?php endif; ?>

            <div class="text-center small text-muted mt-3">
                <a href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left"></i> Back to login</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>