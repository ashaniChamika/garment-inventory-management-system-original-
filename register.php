<?php
require_once __DIR__ . '/config/config.php';
requireGuest();

$errors = [];
$data = ['name' => '', 'email' => '', 'phone' => ''];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Security token expired. Please refresh and try again.';
    } else {
        $data['name']  = clean((string)post('name', ''), 150);
        $data['email'] = strtolower(clean((string)post('email', ''), 190));
        $data['phone'] = clean((string)post('phone', ''), 30);
        $password      = (string)post('password', '');
        $confirm       = (string)post('password_confirm', '');

        if ($data['name'] === '') $errors[] = 'Full name is required.';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            $pdo = db();
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $chk->execute([$data['email']]);
            if ($chk->fetchColumn()) {
                $errors[] = 'An account with that email already exists.';
            } else {
                // New self-registered users get the "Employee" role (id = 10).
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, phone, role_id, status, created_at)
                     VALUES (?, ?, ?, ?, 10, "active", NOW())'
                );
                $stmt->execute([
                    $data['name'],
                    $data['email'],
                    password_hash($password, PASSWORD_DEFAULT),
                    $data['phone'] ?: null,
                ]);
                $newId = (int)$pdo->lastInsertId();

                try {
                    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, 10)')->execute([$newId]);
                } catch (Throwable $e) {}

                logActivity('User Registered', 'auth', $newId, 'New self-registration');
                flash('success', 'Account created successfully. You can now sign in.');
                redirect('login.php');
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
    <title>Create Account &middot; GIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="gims-auth-body">

<div class="gims-auth-wrap">
    <div class="gims-auth-card">
        <div class="gims-auth-side">
            <div class="gims-auth-side-inner">
                <div class="gims-brand-logo xl"><i class="bi bi-person-plus"></i></div>
                <h2>Join the platform</h2>
                <p>Create your account to request access. A Super Admin can upgrade your role once your account is created.</p>
                <ul class="gims-auth-points">
                    <li><i class="bi bi-check2-circle"></i> Self-service signup</li>
                    <li><i class="bi bi-check2-circle"></i> Role based access control</li>
                    <li><i class="bi bi-check2-circle"></i> Secure password hashing</li>
                </ul>
            </div>
        </div>

        <div class="gims-auth-form">
            <h3 class="mb-1">Create your account</h3>
            <p class="text-muted small">It only takes a minute</p>

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
                    <label class="form-label">Full name</label>
                    <input type="text" name="name" class="form-control" required value="<?= e($data['name']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email address</label>
                    <input type="email" name="email" class="form-control" required value="<?= e($data['email']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone (optional)</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($data['phone']) ?>">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Confirm password</label>
                        <input type="password" name="password_confirm" class="form-control" required minlength="8">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 gims-btn-lg">
                    <i class="bi bi-person-plus me-1"></i> Create Account
                </button>
            </form>

            <div class="text-center small text-muted mt-3">
                Already have an account? <a href="<?= BASE_URL ?>/login.php">Sign in</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>