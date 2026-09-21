<?php
/**
 * Application bootstrap / configuration.
 * -----------------------------------------------------------------------------
 * Include this file at the very top of every PHP entry point.
 */

declare(strict_types=1);

if (!defined('GIMS_APP')) {
    define('GIMS_APP', true);
}

/* -------------------------------------------------------------------------
 |  ERROR REPORTING
 * ------------------------------------------------------------------------- */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

if (!is_dir(__DIR__ . '/../storage/logs')) {
    @mkdir(__DIR__ . '/../storage/logs', 0775, true);
}
ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');

/* -------------------------------------------------------------------------
 |  SESSION HARDENING
 * ------------------------------------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('GIMSSESSID');
    session_start();
}

/* -------------------------------------------------------------------------
 |  PATH / URL CONSTANTS
 * ------------------------------------------------------------------------- */
define('BASE_PATH', realpath(__DIR__ . '/..'));

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');

// Detect app base path (folder name of the project)
$projectFolder = basename(BASE_PATH);
if (strpos($scriptDir, '/' . $projectFolder) === false) {
    $baseUrl = '/' . $projectFolder;
} else {
    $baseUrl = substr($scriptDir, 0, strpos($scriptDir, '/' . $projectFolder) + strlen($projectFolder) + 1);
    $baseUrl = rtrim($baseUrl, '/');
}

define('BASE_URL', $baseUrl === '' ? '/' : $baseUrl);
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', ASSETS_URL . '/uploads');
define('UPLOADS_PATH', BASE_PATH . '/assets/uploads');
define('APP_NAME', 'Garment Inventory Management System');
define('APP_SHORT_NAME', 'GIMS');
define('APP_VERSION', '1.0.0');
define('DEFAULT_CURRENCY', 'Rs.');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i');
define('RECORDS_PER_PAGE', 15);
define('LOW_STOCK_THRESHOLD', 10);
define('ALLOW_NEGATIVE_STOCK', false);

/* -------------------------------------------------------------------------
 |  SECURITY TOKEN
 * ------------------------------------------------------------------------- */
if (empty($_SESSION['_app_token'])) {
    $_SESSION['_app_token'] = bin2hex(random_bytes(24));
}

/* -------------------------------------------------------------------------
 |  LOAD CORE FILES
 * ------------------------------------------------------------------------- */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';