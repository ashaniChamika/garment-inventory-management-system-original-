<?php
/**
 * CSRF protection helpers.
 */

declare(strict_types=1);

if (!defined('GIMS_APP')) { exit('Direct access denied'); }

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(?string $token = null): bool
{
    $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void
{
    if (isPost() && !verifyCsrf()) {
        if (isAjax()) {
            jsonFail('Security token mismatch. Please refresh the page.', 419);
        }
        flash('danger', 'Your session has expired. Please try again.');
        redirect('dashboard.php');
    }
}

/* Verify only when POST & requested by caller */
function csrfInput(): string { return csrfField(); }