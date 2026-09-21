<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo  = db();
$user = currentUser();
$errors = [];
$success = null;

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Security token expired.';
    } else {
        $action = post('action', 'profile');

        if ($action === 'profile') {
            $name  = clean((string)post('name', ''), 150);
            $phone = clean((string)post('phone', ''), 30);
            if ($name === '') $errors[] = 'Name is required.';
            if (!$errors) {
                $pdo->prepare('UPDATE users SET name=?, phone=?, updated_at=NOW() WHERE id=?')
                    ->execute([$name, $phone ?: null, $user['id']]);
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['phone'] = $phone;
                logActivity('Profile Updated', 'users', $user['id'], 'Updated profile');
                flash('success', 'Profile updated.');
                redirect('users/profile.php');
            }
        } elseif ($action === 'password') {
            $current = (string)post('current_password', '');
            $new     = (string)post('new_password', '');
            $confirm = (string)post('confirm_password', '');

            $row = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $row->execute([$user['id']]);
            $hash = $row->fetchColumn();

            if (!password_verify($current, $hash)) $errors[] = 'Current password is incorrect.';
            if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
            if ($new !== $confirm) $errors[] = 'New passwords do not match.';

            if (!$errors) {
                $pdo->prepare('UPDATE users SET password=?, updated_at=NOW() WHERE id=?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                logActivity('Password Changed', 'users', $user['id'], 'Changed own password');
                flash('success', 'Password changed successfully.');
                redirect('users/profile.php');
            }
        }
    }
}

$pageTitle    = 'My Profile';
$pageSubtitle = 'Manage your account';
$breadcrumbs  = ['Users' => null, 'Profile' => null];

$fresh = $pdo->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
$fresh->execute([$user['id']]);
$u = $fresh->fetch();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="gims-card">
            <div class="gims-card-body text-center">
                <span class="gims-avatar" style="width:80px;height:80px;font-size:32px;margin:0 auto 12px">
                    <?= e(strtoupper(substr($u['name'], 0, 1))) ?>
                </span>
                <h5 class="mb-1"><?= e($u['name']) ?></h5>
                <p class="text-muted small mb-2"><?= e($u['email']) ?></p>
                <span class="badge badge-soft-primary"><?= e($u['role_name']) ?></span>
                <div class="mt-3 small text-muted">
                    <div><i class="bi bi-clock me-1"></i>Last login: <?= $u['last_login'] ? fdatetime($u['last_login']) : 'Never' ?></div>
                    <div><i class="bi bi-calendar me-1"></i>Member since: <?= fdate($u['created_at']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="gims-card mb-3">
            <div class="gims-card-head"><h5 class="gims-card-title">Profile Information</h5></div>
            <div class="gims-card-body">
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="profile">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required value="<?= e($u['name']) ?>" maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= e($u['email']) ?>" disabled>
                        <small class="text-muted">Email cannot be changed here.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($u['phone']) ?>" maxlength="30">
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="gims-card">
            <div class="gims-card-head"><h5 class="gims-card-title">Change Password</h5></div>
            <div class="gims-card-body">
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="8">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="8">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>