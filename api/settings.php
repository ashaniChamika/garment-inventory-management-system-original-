<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('settings.manage');
require_once __DIR__ . '/../includes/upload.php';

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        case 'list':
            $rows = $pdo->query('SELECT * FROM settings ORDER BY setting_group, setting_key')->fetchAll();
            $grouped = [];
            foreach ($rows as $r) $grouped[$r['setting_group']][] = $r;
            jsonOk(['items'=>$rows, 'grouped'=>$grouped]);
            break;

        case 'save':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);

            $settings = post('settings', []);
            if (!is_array($settings)) jsonFail('No settings provided.');

            try {
                $pdo->beginTransaction();
                $up = $pdo->prepare(
                    'INSERT INTO settings (setting_key, setting_value, setting_group, created_at)
                     VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
                );

                $groupGuess = [
                    'company' => 'general', 'currency' => 'general', 'tax' => 'general', 'date' => 'general',
                    'time' => 'general', 'records' => 'general', 'low_stock' => 'inventory',
                    'allow_negative' => 'inventory', 'default_warehouse' => 'inventory',
                    'theme' => 'appearance', 'sidebar' => 'appearance',
                    'prefix' => 'numbering', 'maintenance' => 'system',
                ];

                foreach ($settings as $key => $val) {
                    $key = preg_replace('/[^a-z0-9_]/', '', strtolower($key));
                    if ($key === '') continue;
                    $grp = 'general';
                    foreach ($groupGuess as $k => $g) if (strpos($key, $k) !== false) { $grp = $g; break; }
                    $up->execute([$key, (string)$val, $grp]);
                }

                // Handle logo upload
                if (!empty($_FILES['company_logo']['name'])) {
                    try {
                        $path = handleImageUpload($_FILES['company_logo'], 'branding');
                        if ($path) {
                            $up->execute(['company_logo', $path, 'general']);
                        }
                    } catch (Throwable $ex) {
                        // non-fatal
                    }
                }

                $pdo->commit();

                logActivity('Settings Updated', 'settings', null, 'Updated system settings');
                jsonOk(null, 'Settings saved. Reload to see changes.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 500);
            }
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API SETTINGS] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}