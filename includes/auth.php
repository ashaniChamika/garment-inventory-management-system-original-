<?php
/**
 * Authentication helpers.
 */

declare(strict_types=1);

if (!defined('GIMS_APP')) { exit('Direct access denied'); }

const MAX_LOGIN_ATTEMPTS = 5;
const LOCK_TIME_SECONDS  = 900; // 15 minutes

/* ------------------------------------------------------------------ */
/*  SESSION STATE                                                      */
/* ------------------------------------------------------------------ */

function isLoggedIn(): bool
{
    return !empty($_SESSION['user']['id']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function currentUserId(): ?int
{
    return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
}

/* ------------------------------------------------------------------ */
/*  LOGIN                                                              */
/* ------------------------------------------------------------------ */

function attemptLogin(string $email, string $password): array
{
    $pdo = db();
    $email = strtolower(trim($email));

    $stmt = $pdo->prepare(
        'SELECT u.*, r.name AS role_name, r.slug AS role_slug
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.email = ? AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['ok' => false, 'message' => 'Invalid email or password.'];
    }

    if ($user['status'] !== 'active') {
        return ['ok' => false, 'message' => 'Your account is not active. Please contact the administrator.'];
    }

    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
        return ['ok' => false, 'message' => "Account temporarily locked. Try again in {$mins} minute(s)."];
    }

    if (!password_verify($password, $user['password'])) {
        $attempts = (int)$user['login_attempts'] + 1;
        $lockedUntil = null;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOCK_TIME_SECONDS);
            $attempts = 0;
        }
        $up = $pdo->prepare('UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?');
        $up->execute([$attempts, $lockedUntil, $user['id']]);

        return ['ok' => false, 'message' => 'Invalid email or password.'];
    }

    // Success
    $up = $pdo->prepare('UPDATE users SET last_login = NOW(), login_attempts = 0, locked_until = NULL WHERE id = ?');
    $up->execute([$user['id']]);

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'name'      => $user['name'],
        'email'     => $user['email'],
        'phone'     => $user['phone'],
        'avatar'    => $user['avatar'],
        'role_id'   => (int)$user['role_id'],
        'role_name' => $user['role_name'],
        'role_slug' => $user['role_slug'],
    ];

    // Load permissions into session
    $_SESSION['permissions'] = loadUserPermissions((int)$user['id']);

    logActivity('User Login', 'auth', (int)$user['id'], 'User logged in successfully');

    return ['ok' => true, 'user' => $_SESSION['user']];
}

/* ------------------------------------------------------------------ */
/*  LOGOUT                                                             */
/* ------------------------------------------------------------------ */

function logout(): void
{
    if (isLoggedIn()) {
        logActivity('User Logout', 'auth', currentUserId(), 'User logged out');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ------------------------------------------------------------------ */
/*  ROUTE GUARDS                                                       */
/* ------------------------------------------------------------------ */

function requireLogin(): void
{
    if (!isLoggedIn()) {
        if (isAjax()) {
            jsonFail('Authentication required.', 401);
        }
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? null;
        flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }
}

function requireGuest(): void
{
    if (isLoggedIn()) {
        redirect('dashboard.php');
    }
}

/* ------------------------------------------------------------------ */
/*  PASSWORD RESET                                                     */
/* ------------------------------------------------------------------ */

function createPasswordResetToken(string $email): ?string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([strtolower($email)]);
    $id = $stmt->fetchColumn();
    if (!$id) return null;

    $token = bin2hex(random_bytes(32));
    $up = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
    $up->execute([$token, $id]);

    return $token;
}

function validateResetToken(string $token): ?int
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$token]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function resetUserPassword(int $userId, string $newPassword): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL, login_attempts = 0, locked_until = NULL WHERE id = ?');
    return $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
}