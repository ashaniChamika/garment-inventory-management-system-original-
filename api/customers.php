<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        case 'list':
            if (!hasPermission('sales.manage') && !hasPermission('customer.manage')) jsonFail('Forbidden', 403);

            $q      = trim((string)get('q', ''));
            $status = trim((string)get('status', ''));
            $page   = max(1, (int)get('page', 1));
            $perPage = 15;

            $where  = ['c.deleted_at IS NULL'];
            $params = [];
            if ($q !== '') {
                $where[] = '(c.name LIKE ? OR c.company LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.city LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like, $like, $like, $like);
            }
            if ($status !== '') { $where[] = 'c.status = ?'; $params[] = $status; }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $cnt = $pdo->prepare("SELECT COUNT(*) FROM customers c $whereSql");
            $cnt->execute($params);
            $total = (int)$cnt->fetchColumn();
            $pg = paginate($total, $perPage, $page);

            $sql = "SELECT c.*,
                           (SELECT COUNT(*) FROM sales_orders so WHERE so.customer_id = c.id AND so.deleted_at IS NULL) AS order_count,
                           (SELECT COALESCE(SUM(i.total),0) FROM invoices i WHERE i.customer_id = c.id AND i.status <> 'cancelled') AS total_sales,
                           (SELECT COALESCE(SUM(i.total - i.paid_amount),0) FROM invoices i
                              WHERE i.customer_id = c.id AND i.status IN ('unpaid','partial','overdue')) AS outstanding
                    FROM customers c
                    $whereSql
                    ORDER BY c.name ASC
                    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                $r['status_badge'] = statusBadge($r['status']);
                $r['total_sales']  = (float)$r['total_sales'];
                $r['outstanding']  = (float)$r['outstanding'];
            }
            unset($r);

            jsonOk(['items' => $rows, 'pagination' => $pg]);
            break;

        case 'get':
            if (!hasPermission('sales.manage') && !hasPermission('customer.manage')) jsonFail('Forbidden', 403);
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$id]);
            $c = $stmt->fetch();
            if (!$c) jsonFail('Customer not found.', 404);
            jsonOk($c);
            break;

        case 'save':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('customer.manage')) jsonFail('Forbidden', 403);

            $id          = (int)post('id', 0);
            $name        = clean((string)post('name', ''), 190);
            $company     = clean((string)post('company', ''), 190);
            $phone       = clean((string)post('phone', ''), 30);
            $email       = clean((string)post('email', ''), 190);
            $address     = clean((string)post('address', ''), 400);
            $city        = clean((string)post('city', ''), 100);
            $country     = clean((string)post('country', 'Sri Lanka'), 100);
            $taxNumber   = clean((string)post('tax_number', ''), 60);
            $creditLimit = decimal_or(post('credit_limit', '0'), 0);
            $status      = post('status', 'active') === 'inactive' ? 'inactive' : 'active';

            $errors = [];
            if ($name === '') $errors[] = 'Customer name is required.';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                if ($id > 0) {
                    $up = $pdo->prepare(
                        'UPDATE customers SET name=?, company=?, phone=?, email=?, address=?, city=?, country=?,
                            tax_number=?, credit_limit=?, status=?, updated_at=NOW()
                         WHERE id=? AND deleted_at IS NULL'
                    );
                    $up->execute([$name, $company ?: null, $phone ?: null, $email ?: null, $address ?: null,
                                  $city ?: null, $country ?: null, $taxNumber ?: null, $creditLimit, $status, $id]);
                    logActivity('Customer Updated', 'customer', $id, "Updated: $name");
                    jsonOk(['id' => $id], 'Customer updated.');
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO customers (name, company, phone, email, address, city, country, tax_number,
                            credit_limit, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $ins->execute([$name, $company ?: null, $phone ?: null, $email ?: null, $address ?: null,
                                   $city ?: null, $country ?: null, $taxNumber ?: null, $creditLimit, $status]);
                    $newId = (int)$pdo->lastInsertId();
                    logActivity('Customer Created', 'customer', $newId, "Created: $name");
                    jsonOk(['id' => $newId], 'Customer created.');
                }
            } catch (Throwable $ex) {
                error_log('[GIMS CUSTOMER SAVE] ' . $ex->getMessage());
                jsonFail('Could not save customer.', 500);
            }
            break;

        case 'delete':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('customer.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            $chk = $pdo->prepare("SELECT COUNT(*) FROM sales_orders WHERE customer_id = ? AND status NOT IN ('delivered','cancelled') AND deleted_at IS NULL");
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) jsonFail('Cannot delete customer with active orders.');

            $n = $pdo->prepare('SELECT name FROM customers WHERE id = ? AND deleted_at IS NULL');
            $n->execute([$id]);
            $name = $n->fetchColumn();
            if (!$name) jsonFail('Customer not found.', 404);

            $pdo->prepare('UPDATE customers SET deleted_at = NOW(), status = "inactive" WHERE id = ?')->execute([$id]);
            logActivity('Customer Deleted', 'customer', $id, "Deleted: $name");
            jsonOk(null, 'Customer deleted.');
            break;

        case 'lookup':
            $q = trim((string)get('q', ''));
            $where = ['deleted_at IS NULL', 'status = "active"'];
            $params = [];
            if ($q !== '') {
                $where[] = '(name LIKE ? OR company LIKE ? OR phone LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like, $like);
            }
            $stmt = $pdo->prepare('SELECT id, name, company, phone, email, credit_limit, balance
                                    FROM customers WHERE ' . implode(' AND ', $where) . '
                                    ORDER BY name ASC LIMIT 50');
            $stmt->execute($params);
            jsonOk(['items' => $stmt->fetchAll()]);
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API CUSTOMERS] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}