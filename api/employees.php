<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();
require_once __DIR__ . '/../includes/upload.php';

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        case 'list':
            if (!hasPermission('employee.manage')) jsonFail('Forbidden', 403);

            $q = trim((string)get('q', ''));
            $deptId = (int)get('department_id', 0);
            $status = trim((string)get('status', ''));
            $page = max(1, (int)get('page', 1));
            $perPage = 15;

            $where = ['e.deleted_at IS NULL'];
            $params = [];
            if ($q !== '') {
                $where[] = '(e.full_name LIKE ? OR e.employee_no LIKE ? OR e.nic LIKE ? OR e.phone LIKE ? OR e.email LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like, $like, $like, $like);
            }
            if ($deptId > 0) { $where[] = 'e.department_id = ?'; $params[] = $deptId; }
            if ($status !== '') { $where[] = 'e.status = ?'; $params[] = $status; }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $cnt = $pdo->prepare("SELECT COUNT(*) FROM employees e $whereSql");
            $cnt->execute($params);
            $total = (int)$cnt->fetchColumn();
            $pg = paginate($total, $perPage, $page);

            $sql = "SELECT e.*, d.name AS department_name
                    FROM employees e
                    LEFT JOIN departments d ON d.id = e.department_id
                    $whereSql
                    ORDER BY e.full_name ASC
                    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) $r['status_badge'] = statusBadge($r['status']);
            unset($r);

            jsonOk(['items' => $rows, 'pagination' => $pg]);
            break;

        case 'get':
            if (!hasPermission('employee.manage')) jsonFail('Forbidden', 403);
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare('SELECT e.*, d.name AS department_name FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE e.id = ? AND e.deleted_at IS NULL');
            $stmt->execute([$id]);
            $e = $stmt->fetch();
            if (!$e) jsonFail('Employee not found.', 404);
            jsonOk($e);
            break;

        case 'save':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('employee.manage')) jsonFail('Forbidden', 403);

            $id            = (int)post('id', 0);
            $employeeNo    = clean((string)post('employee_no', ''), 40);
            $fullName      = clean((string)post('full_name', ''), 150);
            $nic           = clean((string)post('nic', ''), 30);
            $gender        = post('gender', '');
            $dob           = post('dob') ?: null;
            $phone         = clean((string)post('phone', ''), 30);
            $email         = clean((string)post('email', ''), 190);
            $address       = clean((string)post('address', ''), 400);
            $departmentId  = (int)post('department_id', 0);
            $position      = clean((string)post('position', ''), 120);
            $joinDate      = post('join_date') ?: null;
            $salary        = decimal_or(post('salary', 0), 0);
            $emergency     = clean((string)post('emergency_contact', ''), 120);
            $status        = in_array(post('status'), ['active','inactive','resigned','terminated'], true) ? post('status') : 'active';

            $errors = [];
            if ($fullName === '') $errors[] = 'Full name is required.';
            if ($employeeNo === '') $errors[] = 'Employee number is required.';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            // uniqueness
            $chk = $pdo->prepare('SELECT id FROM employees WHERE employee_no = ? AND id <> ? AND deleted_at IS NULL');
            $chk->execute([$employeeNo, $id]);
            if ($chk->fetchColumn()) jsonFail('Employee number already exists.');

            // image
            $imagePath = null;
            if (!empty($_FILES['image']['name'])) {
                try { $imagePath = handleImageUpload($_FILES['image'], 'employees'); }
                catch (Throwable $ex) { jsonFail('Image upload: ' . $ex->getMessage()); }
            }

            try {
                if ($id > 0) {
                    $sql = 'UPDATE employees SET employee_no=?, full_name=?, nic=?, gender=?, dob=?, phone=?, email=?,
                            address=?, department_id=?, position=?, join_date=?, salary=?, emergency_contact=?, status=?';
                    $args = [$employeeNo, $fullName, $nic ?: null, $gender ?: null, $dob, $phone ?: null, $email ?: null,
                             $address ?: null, $departmentId ?: null, $position ?: null, $joinDate, $salary,
                             $emergency ?: null, $status];
                    if ($imagePath) { $sql .= ', image = ?'; $args[] = $imagePath; }
                    $sql .= ', updated_at=NOW() WHERE id=?';
                    $args[] = $id;
                    $pdo->prepare($sql)->execute($args);
                    logActivity('Employee Updated', 'employee', $id, "Updated: $fullName");
                    jsonOk(['id' => $id], 'Employee updated.');
                } else {
                    $ins = $pdo->prepare('INSERT INTO employees (employee_no, full_name, nic, gender, dob, phone, email, address,
                        department_id, position, join_date, salary, emergency_contact, image, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                    $ins->execute([$employeeNo, $fullName, $nic ?: null, $gender ?: null, $dob, $phone ?: null, $email ?: null,
                                   $address ?: null, $departmentId ?: null, $position ?: null, $joinDate, $salary,
                                   $emergency ?: null, $imagePath, $status]);
                    $newId = (int)$pdo->lastInsertId();
                    logActivity('Employee Created', 'employee', $newId, "Created: $fullName");
                    jsonOk(['id' => $newId], 'Employee created.');
                }
            } catch (Throwable $ex) {
                error_log('[GIMS EMP SAVE] ' . $ex->getMessage());
                jsonFail('Could not save employee.', 500);
            }
            break;

        case 'delete':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('employee.manage')) jsonFail('Forbidden', 403);
            $id = (int)post('id', 0);
            $n = $pdo->prepare('SELECT full_name FROM employees WHERE id = ? AND deleted_at IS NULL');
            $n->execute([$id]);
            $name = $n->fetchColumn();
            if (!$name) jsonFail('Employee not found.', 404);
            $pdo->prepare('UPDATE employees SET deleted_at = NOW(), status = "inactive" WHERE id = ?')->execute([$id]);
            logActivity('Employee Deleted', 'employee', $id, "Deleted: $name");
            jsonOk(null, 'Employee deleted.');
            break;

        case 'lookup':
            $q = trim((string)get('q', ''));
            $where = ['deleted_at IS NULL', 'status = "active"'];
            $params = [];
            if ($q !== '') {
                $where[] = '(full_name LIKE ? OR employee_no LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like);
            }
            $stmt = $pdo->prepare('SELECT id, employee_no, full_name, department_id, position FROM employees WHERE ' . implode(' AND ', $where) . ' ORDER BY full_name LIMIT 50');
            $stmt->execute($params);
            jsonOk(['items' => $stmt->fetchAll()]);
            break;

        /* ==================================================
           DEPARTMENTS
           ================================================== */
        case 'departments_list':
            if (!hasPermission('employee.manage')) jsonFail('Forbidden', 403);

            $sql = 'SELECT d.*,
                           (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.deleted_at IS NULL) AS emp_count
                    FROM departments d
                    ORDER BY d.name ASC';
            $rows = $pdo->query($sql)->fetchAll();
            foreach ($rows as &$r) $r['status_badge'] = statusBadge($r['status']);
            unset($r);
            jsonOk(['items' => $rows]);
            break;

        case 'save_department':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('employee.manage')) jsonFail('Forbidden', 403);

            $id     = (int)post('id', 0);
            $name   = clean((string)post('name', ''), 120);
            $code   = strtoupper(clean((string)post('code', ''), 30));
            $desc   = clean((string)post('description', ''), 255);
            $status = post('status', 'active') === 'inactive' ? 'inactive' : 'active';

            if ($name === '' || $code === '') jsonFail('Name and code are required.');

            $chk = $pdo->prepare('SELECT id FROM departments WHERE code = ? AND id <> ?');
            $chk->execute([$code, $id]);
            if ($chk->fetchColumn()) jsonFail('Department code already exists.');

            if ($id > 0) {
                $pdo->prepare('UPDATE departments SET name=?, code=?, description=?, status=?, updated_at=NOW() WHERE id=?')
                    ->execute([$name, $code, $desc ?: null, $status, $id]);
                logActivity('Department Updated', 'employee', $id, "Updated: $name");
                jsonOk(['id' => $id], 'Department updated.');
            } else {
                $pdo->prepare('INSERT INTO departments (name, code, description, status, created_at) VALUES (?, ?, ?, ?, NOW())')
                    ->execute([$name, $code, $desc ?: null, $status]);
                $newId = (int)$pdo->lastInsertId();
                logActivity('Department Created', 'employee', $newId, "Created: $name");
                jsonOk(['id' => $newId], 'Department created.');
            }
            break;

        case 'delete_department':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('employee.manage')) jsonFail('Forbidden', 403);
            $id = (int)post('id', 0);
            $chk = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE department_id = ? AND deleted_at IS NULL');
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) jsonFail('Cannot delete department with employees assigned.');
            $pdo->prepare('DELETE FROM departments WHERE id = ?')->execute([$id]);
            jsonOk(null, 'Department deleted.');
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API EMPLOYEES] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}