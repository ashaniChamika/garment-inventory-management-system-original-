<?php
/**
 * Database connection (PDO singleton)
 * -----------------------------------------------------------------------------
 * Garment Inventory Management System (GIMS)
 */

declare(strict_types=1);

if (!defined('GIMS_APP')) {
    define('GIMS_APP', true);
}

/* -------------------------------------------------------------------------
 |  DATABASE CREDENTIALS  —  change these for your environment
 * ------------------------------------------------------------------------- */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'garment_inventory');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Return a shared PDO instance.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log('[GIMS DB ERROR] ' . $e->getMessage());
        http_response_code(500);
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
            exit(1);
        }
        die('
            <div style="font-family:system-ui;max-width:640px;margin:80px auto;padding:32px;
                        border:1px solid #e5e7eb;border-radius:14px;background:#fff;
                        box-shadow:0 4px 24px rgba(0,0,0,.06);color:#0f172a">
                <h2 style="margin:0 0 12px;color:#b91c1c">Database Connection Error</h2>
                <p style="color:#475569;line-height:1.55">
                    The application could not connect to the MySQL server.
                    Please verify that MySQL is running and that the credentials in
                    <code>config/database.php</code> are correct.
                </p>
            </div>
        ');
    }

    return $pdo;
}