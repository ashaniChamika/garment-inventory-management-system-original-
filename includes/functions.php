<?php
/**
 * Global helper functions.
 */

declare(strict_types=1);

if (!defined('GIMS_APP')) { exit('Direct access denied'); }

/* ------------------------------------------------------------------ */
/*  OUTPUT ESCAPING                                                    */
/* ------------------------------------------------------------------ */

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clean(?string $value, int $max = 5000): string
{
    return trim(mb_substr((string)$value, 0, $max));
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text ?: 'item');
}

/* ------------------------------------------------------------------ */
/*  FORMATTING                                                         */
/* ------------------------------------------------------------------ */

function money($amount, string $symbol = null): string
{
    $symbol = $symbol ?? DEFAULT_CURRENCY;
    return $symbol . ' ' . number_format((float)$amount, 2);
}

function qty($q, int $decimals = 2): string
{
    return number_format((float)$q, $decimals);
}

function fdate(?string $date, string $format = DATE_FORMAT): string
{
    if (empty($date) || $date === '0000-00-00') return '—';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function fdatetime(?string $dt): string
{
    return fdate($dt, DATETIME_FORMAT);
}

function timeAgo(?string $dt): string
{
    if (!$dt) return '—';
    $ts = strtotime($dt);
    if (!$ts) return '—';
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date(DATE_FORMAT, $ts);
}

/* ------------------------------------------------------------------ */
/*  REQUEST HELPERS                                                    */
/* ------------------------------------------------------------------ */

function isPost(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function isGet(): bool  { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'; }
function isAjax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}
function get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function intval_or($value, int $default = 0): int
{
    return is_numeric($value) ? (int)$value : $default;
}

function decimal_or($value, float $default = 0.0): float
{
    $clean = str_replace([',', ' '], '', (string)$value);
    return is_numeric($clean) ? (float)$clean : $default;
}

function clientIp(): string
{
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

/* ------------------------------------------------------------------ */
/*  RESPONSES                                                          */
/* ------------------------------------------------------------------ */

function jsonOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonOk($data = null, string $message = 'OK'): void
{
    jsonOut(['success' => true, 'message' => $message, 'data' => $data]);
}

function jsonFail(string $message, int $status = 400, $errors = null): void
{
    jsonOut(['success' => false, 'message' => $message, 'errors' => $errors], $status);
}

function redirect(string $path): void
{
    if (!preg_match('~^https?://~', $path)) {
        $path = BASE_URL . '/' . ltrim($path, '/');
    }
    header('Location: ' . $path);
    exit;
}

/* ------------------------------------------------------------------ */
/*  FLASH MESSAGES                                                     */
/* ------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ------------------------------------------------------------------ */
/*  GENERATORS                                                         */
/* ------------------------------------------------------------------ */

function generateRef(string $prefix, string $table, string $column): string
{
    $pdo = db();
    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';

    $stmt = $pdo->prepare("SELECT $column FROM $table WHERE $column LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$like]);
    $last = $stmt->fetchColumn();

    if ($last && preg_match('/(\d+)$/', $last, $m)) {
        $next = (int)$m[1] + 1;
    } else {
        $next = 1;
    }

    return sprintf('%s-%s-%04d', $prefix, $year, $next);
}

function randomCode(int $length = 8): string
{
    return strtoupper(substr(bin2hex(random_bytes($length)), 0, $length));
}

/* ------------------------------------------------------------------ */
/*  AUDIT LOG                                                          */
/* ------------------------------------------------------------------ */

function logActivity(string $action, string $module, ?int $recordId = null, ?string $description = null): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id, action, module, record_id, description, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $_SESSION['user']['id'] ?? null,
            $action,
            $module,
            $recordId,
            $description,
            clientIp(),
            mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
        ]);
    } catch (Throwable $e) {
        error_log('[GIMS LOG ERROR] ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------------ */
/*  SETTINGS                                                           */
/* ------------------------------------------------------------------ */

function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            foreach ($rows as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

/* ------------------------------------------------------------------ */
/*  NOTIFICATIONS                                                      */
/* ------------------------------------------------------------------ */

function pushNotification(string $title, string $message, string $type = 'info', ?string $module = null, ?string $link = null, ?int $userId = null): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO notifications (user_id, title, message, type, module, link, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, NOW())'
        );
        $stmt->execute([$userId, $title, $message, $type, $module, $link]);
    } catch (Throwable $e) {
        error_log('[GIMS NOTIF ERROR] ' . $e->getMessage());
    }
}

function unreadNotificationCount(?int $userId): int
{
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND (user_id = ? OR user_id IS NULL)');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/* ------------------------------------------------------------------ */
/*  STATUS BADGES                                                      */
/* ------------------------------------------------------------------ */

function statusBadge(?string $status): string
{
    $s = strtolower((string)$status);
    $map = [
        'active'             => 'badge-soft-success',
        'inactive'           => 'badge-soft-secondary',
        'suspended'          => 'badge-soft-danger',
        'pending'            => 'badge-soft-warning',
        'approved'           => 'badge-soft-info',
        'rejected'           => 'badge-soft-danger',
        'cancelled'          => 'badge-soft-danger',
        'draft'              => 'badge-soft-secondary',
        'in_progress'        => 'badge-soft-primary',
        'paused'             => 'badge-soft-warning',
        'completed'          => 'badge-soft-success',
        'confirmed'          => 'badge-soft-info',
        'processing'         => 'badge-soft-primary',
        'delivered'          => 'badge-soft-success',
        'shipped'            => 'badge-soft-info',
        'received'           => 'badge-soft-success',
        'partially_received' => 'badge-soft-warning',
        'partially_passed'   => 'badge-soft-warning',
        'passed'             => 'badge-soft-success',
        'failed'             => 'badge-soft-danger',
        'unpaid'             => 'badge-soft-warning',
        'partial'            => 'badge-soft-info',
        'paid'               => 'badge-soft-success',
        'overdue'            => 'badge-soft-danger',
        'in_transit'         => 'badge-soft-info',
        'planned'            => 'badge-soft-secondary',
        'qc_pending'         => 'badge-soft-warning',
        'present'            => 'badge-soft-success',
        'absent'             => 'badge-soft-danger',
        'late'               => 'badge-soft-warning',
        'leave'              => 'badge-soft-info',
        'half_day'           => 'badge-soft-info',
    ];
    $cls = $map[$s] ?? 'badge-soft-secondary';
    $label = ucwords(str_replace('_', ' ', $s));
    return '<span class="badge ' . $cls . '">' . e($label) . '</span>';
}

/* ------------------------------------------------------------------ */
/*  PAGINATION                                                         */
/* ------------------------------------------------------------------ */

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = max(1, min($page, $pages));
    return [
        'total'    => $total,
        'per_page' => $perPage,
        'page'     => $page,
        'pages'    => $pages,
        'offset'   => ($page - 1) * $perPage,
        'from'     => $total ? (($page - 1) * $perPage) + 1 : 0,
        'to'       => min($page * $perPage, $total),
    ];
}