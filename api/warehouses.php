<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        case 'list':
            if (!hasPermission('inventory.view')) jsonFail('Forbidden', 403);

            $q      = trim((string)get('q', ''));
            $status = trim((string)get('status', ''));

            $where  = ['w.deleted_at IS NULL'];
            $params = [];

            if ($q !== '') {
                $where[] = '(w.name LIKE ? OR w.code LIKE ? OR w.city LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like, $like);
            }
            if ($status !== '') { $where[] = 'w.status = ?'; $params[] = $status; }

            $sql = 'SELECT w.*, e.full_name AS manager_name,
                           (SELECT COUNT(*) FROM stock s WHERE s.warehouse_id = w.id AND s.quantity > 0) AS product_count,
                           (SELECT COALESCE(SUM(s.quantity * p.cost_price),0)
                              FROM stock s JOIN products p ON p.id = s.product_id
                              WHERE s.warehouse_id = w.id) AS stock_value
                    FROM warehouses w
                    LEFT JOIN employees e ON e.id = w.manager_id
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY w.is_default DESC, w.name ASC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                $r['status_badge'] = statusBadge($r['status']);
                $r['stock_value']  = (float)$r['stock_value'];
            }
            unset($r);

            jsonOk(['items' => $rows]);
            break;

        case 'get':
            if (!hasPermission('inventory.view')) jsonFail('Forbidden', 403);
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare('SELECT * FROM warehouses WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$id]);
            $w = $stmt->fetch();
            if (!$w) jsonFail('Warehouse not found.', 404);

            $l = $pdo->prepare('SELECT * FROM warehouse_locations WHERE warehouse_id = ? ORDER BY code ASC');
            $l->execute([$id]);
            $w['locations'] = $l->fetchAll();
            jsonOk($w);
            break;

        case 'save':
            if (isPost() && !verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('warehouse.manage')) jsonFail('Forbidden', 403);

            $id       = (int)post('id', 0);
            $name     = clean((string)post('name', ''), 150);
            $code     = strtoupper(clean((string)post('code', ''), 40));
            $address  = clean((string)post('address', ''), 400);
            $city     = clean((string)post('city', ''), 100);
            $country  = clean((string)post('country', 'Sri Lanka'), 100);
            $manager  = (int)post('manager_id', 0);
            $phone    = clean((string)post('phone', ''), 30);
            $email    = clean((string)post('email', ''), 190);
            $isDefault= post('is_default') ? 1 : 0;
            $status   = post('status', 'active') === 'inactive' ? 'inactive' : 'active';

            $errors = [];
            if ($name === '') $errors[] = 'Name is required.';
            if ($code === '') $errors[] = 'Code is required.';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';

            if (!$errors) {
                $chk = $pdo->prepare('SELECT id FROM warehouses WHERE code = ? AND id <> ? AND deleted_at IS NULL');
                $chk->execute([$code, $id]);
                if ($chk->fetchColumn()) $errors[] = 'Warehouse code already exists.';
            }

            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                $pdo->beginTransaction();

                if ($isDefault) {
                    $pdo->exec('UPDATE warehouses SET is_default = 0');
                }

                if ($id > 0) {
                    $up = $pdo->prepare(
                        'UPDATE warehouses SET name=?, code=?, address=?, city=?, country=?, manager_id=?,
                            phone=?, email=?, is_default=?, status=?, updated_at=NOW()
                         WHERE id=? AND deleted_at IS NULL'
                    );
                    $up->execute([$name, $code, $address ?: null, $city ?: null, $country ?: null,
                                  $manager ?: null, $phone ?: null, $email ?: null, $isDefault, $status, $id]);
                    $wid = $id;
                    logActivity('Warehouse Updated', 'warehouse', $wid, "Updated: $name");
                } else {
                    $in = $pdo->prepare(
                        'INSERT INTO warehouses (name, code, address, city, country, manager_id, phone, email, is_default, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $in->execute([$name, $code, $address ?: null, $city ?: null, $country ?: null,
                                  $manager ?: null, $phone ?: null, $email ?: null, $isDefault, $status]);
                    $wid = (int)$pdo->lastInsertId();
                    logActivity('Warehouse Created', 'warehouse', $wid, "Created: $name");
                }

                $pdo->commit();
                jsonOk(['id' => $wid], $id > 0 ? 'Warehouse updated.' : 'Warehouse created.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[GIMS WH SAVE] ' . $ex->getMessage());
                jsonFail('Could not save warehouse.', 500);
            }
            break;

        case 'delete':
            if (isPost() && !verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('warehouse.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            if ($id <= 0) jsonFail('Invalid warehouse ID.');

            // Prevent deleting warehouse with stock
            $chk = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM stock WHERE warehouse_id = ?');
            $chk->execute([$id]);
            if ((float)$chk->fetchColumn() > 0) {
                jsonFail('Cannot delete warehouse with existing stock. Transfer stock first.');
            }

            $nameStmt = $pdo->prepare('SELECT name, is_default FROM warehouses WHERE id = ? AND deleted_at IS NULL');
            $nameStmt->execute([$id]);
            $row = $nameStmt->fetch();
            if (!$row) jsonFail('Warehouse not found.', 404);
            if ((int)$row['is_default'] === 1) jsonFail('Cannot delete the default warehouse.');

            $pdo->prepare('UPDATE warehouses SET deleted_at = NOW(), status = "inactive" WHERE id = ?')->execute([$id]);
            logActivity('Warehouse Deleted', 'warehouse', $id, 'Deleted: ' . $row['name']);
            jsonOk(null, 'Warehouse deleted.');
            break;

        case 'locations':
            $id = (int)get('warehouse_id', 0);
            $stmt = $pdo->prepare('SELECT * FROM warehouse_locations WHERE warehouse_id = ? ORDER BY code ASC');
            $stmt->execute([$id]);
            jsonOk(['items' => $stmt->fetchAll()]);
            break;

        case 'save_location':
            if (isPost() && !verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('warehouse.manage')) jsonFail('Forbidden', 403);

            $id       = (int)post('id', 0);
            $whId     = (int)post('warehouse_id', 0);
            $rack     = clean((string)post('rack', ''), 50);
            $bin      = clean((string)post('bin', ''), 50);
            $code     = strtoupper(clean((string)post('code', ''), 80));
            $desc     = clean((string)post('description', ''), 255);

            if ($whId <= 0) jsonFail('Warehouse is required.');
            if ($code === '') {
                // Auto-generate: WH-CODE-R{rack}-B{bin}
                $whCode = $pdo->query("SELECT code FROM warehouses WHERE id = " . (int)$whId)->fetchColumn();
                $code = trim(($whCode ?: 'WH') . '-' . ($rack ? 'R' . $rack : '') . ($bin ? '-B' . $bin : ''), '-');
            }

            try {
                if ($id > 0) {
                    $up = $pdo->prepare('UPDATE warehouse_locations SET rack=?, bin=?, code=?, description=? WHERE id=?');
                    $up->execute([$rack ?: null, $bin ?: null, $code, $desc ?: null, $id]);
                    jsonOk(['id' => $id], 'Location updated.');
                } else {
                    $in = $pdo->prepare('INSERT INTO warehouse_locations (warehouse_id, rack, bin, code, description, created_at)
                                         VALUES (?, ?, ?, ?, ?, NOW())');
                    $in->execute([$whId, $rack ?: null, $bin ?: null, $code, $desc ?: null]);
                    jsonOk(['id' => (int)$pdo->lastInsertId()], 'Location created.');
                }
            } catch (Throwable $ex) {
                jsonFail('Could not save location: ' . $ex->getMessage(), 500);
            }
            break;

        case 'delete_location':
            if (isPost() && !verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('warehouse.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT COUNT(*) FROM stock WHERE location_id = ? AND quantity > 0');
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) jsonFail('Cannot delete location with stock.');

            $pdo->prepare('DELETE FROM warehouse_locations WHERE id = ?')->execute([$id]);
            jsonOk(null, 'Location deleted.');
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API WAREHOUSES] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}