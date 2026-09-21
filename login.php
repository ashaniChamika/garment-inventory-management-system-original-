<?php
require_once __DIR__ . '/config/config.php';
requireGuest();

$errors = [];
$email  = '';

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $email    = clean((string)post('email', ''));
        $password = (string)post('password', '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if (!$errors) {
            $result = attemptLogin($email, $password);
            if ($result['ok']) {
                $intended = $_SESSION['_intended'] ?? null;
                unset($_SESSION['_intended']);
                flash('success', 'Welcome back, ' . e($result['user']['name']) . '!');
                if ($intended) {
                    header('Location: ' . $intended);
                    exit;
                }
                redirect('dashboard.php');
            }
            $errors[] = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in &middot; GIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="gims-auth-body">

<div class="gims-auth-wrap">
    <div class="gims-auth-card">
        <div class="gims-auth-side">
            <div class="gims-auth-side-inner">
                <div class="gims-brand-logo xl"><i class="bi bi-box-seam-fill"></i></div>
                <h2>A & C<br>Garment Inventory<br>Management System</h2>
                <p>Enterprise-grade inventory, purchasing, production, quality control and sales management built for the modern garment factory.</p>
                <ul class="gims-auth-points">
                    <li><i class="bi bi-check2-circle"></i> Real-time stock visibility</li>
                    <li><i class="bi bi-check2-circle"></i> Purchase to production workflow</li>
                    <li><i class="bi bi-check2-circle"></i> QC &amp; defect tracking</li>
                    <li><i class="bi bi-check2-circle"></i> Powerful reports &amp; analytics</li>
                </ul>
            </div>
        </div>

        <div class="gims-auth-form">
            <div class="mb-4">
                <h3 class="mb-1">Welcome back</h3>
                <p class="text-muted small mb-0">Sign in to access your dashboard</p>
            </div>

            <?php foreach (getFlashes() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div>
            <?php endforeach; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger py-2 small">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" novalidate autocomplete="off">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label">Email address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" value="<?= e($email) ?>"
                               placeholder="you@company.com" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label d-flex justify-content-between">
                        <span>Password</span>
                        <a href="<?= BASE_URL ?>/forgot-password.php" class="small">Forgot?</a>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="loginPassword" class="form-control"
                               placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePwd">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 gims-btn-lg">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </form>

            <div class="text-center small text-muted mt-4">
                Don't have an account?
                <a href="<?= BASE_URL ?>/register.php">Create one</a>
            </div>

            <!--<div class="gims-auth-demo">
                <strong><i class="bi bi-info-circle me-1"></i>Demo credentials</strong>
                <div>Admin: <code>admin@garment.local</code> / <code>password</code></div>
            </div>-->
        </div>
    </div>
</div>

<script>
document.getElementById('togglePwd')?.addEventListener('click', function () {
    const p = document.getElementById('loginPassword');
    const i = this.querySelector('i');
    if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { p.type = 'password'; i.className = 'bi bi-eye'; }
});
</script>
</body>
</html>