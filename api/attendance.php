<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        case 'list':
            if (!hasPermission('attendance.manage') && !hasPermission('employee.manage')) jsonFail('Forbidden', 403);

            $date       = trim((string)get('date', date('Y-m-d')));
            $deptId     = (int)get('department_id', 0);
            $status     = trim((string)get('status', ''));
            $page       = max(1, (int)get('page', 1));
            $perPage    = 20;

            $where = ['e.deleted_at IS NULL'];
            $params = [];
            if ($deptId > 0) { $where[] = 'e.department_id = ?'; $params[] = $deptId; }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            // count total active employees matching
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM employees e $whereSql");
            $cnt->execute($params);
            $total = (int)$cnt->fetchColumn();
            $pg = paginate($total, $perPage, $page);

            // Status filter after LEFT JOIN
            $statusCond = $status !== '' ? "AND COALESCE(a.status,'absent') = " . $pdo->quote($status) : '';

            $sql = "SELECT e.id AS employee_id, e.employee_no, e.full_name, e.position,
                           d.name AS department_name,
                           a.id AS attendance_id, a.check_in, a.check_out, a.status, a.remarks
                    FROM employees e
                    LEFT JOIN departments d ON d.id = e.department_id
                    LEFT JOIN attendance a ON a.employee_id = e.id AND a.att_date = ?
                    $whereSql $statusCond
                    ORDER BY e.full_name ASC
                    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$date], $params));
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                if (empty($r['status'])) {
                    $r['status'] = 'not_marked';
                    $r['status_label'] = '<span class="badge badge-soft-secondary">Not Marked</span>';
                } else {
                    $r['status_label'] = statusBadge($r['status']);
                }
            }
            unset($r);

            jsonOk(['items' => $rows, 'pagination' => $pg, 'date' => $date]);
            break;

        case 'save':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('attendance.manage')) jsonFail('Forbidden', 403);

            $employeeId = (int)post('employee_id', 0);
            $date       = post('att_date', date('Y-m-d'));
            $checkIn    = post('check_in') ?: null;
            $checkOut   = post('check_out') ?: null;
            $status     = post('status', 'present');
            $remarks    = clean((string)post('remarks', ''), 255);

            if ($employeeId <= 0) jsonFail('Employee is required.');
            if (!in_array($status, ['present','absent','late','half_day','leave'], true)) jsonFail('Invalid status.');

            try {
                $pdo->prepare(
                    'INSERT INTO attendance (employee_id, att_date, check_in, check_out, status, remarks, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), check_out=VALUES(check_out),
                         status=VALUES(status), remarks=VALUES(remarks), updated_at=NOW()'
                )->execute([$employeeId, $date, $checkIn, $checkOut, $status, $remarks ?: null]);

                logActivity('Attendance Marked', 'employee', $employeeId, "Marked $status on $date");
                jsonOk(null, 'Attendance saved.');
            } catch (Throwable $ex) {
                jsonFail($ex->getMessage(), 500);
            }
            break;

        case 'bulk_mark':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('attendance.manage')) jsonFail('Forbidden', 403);

            $date  = post('att_date', date('Y-m-d'));
            $items = post('items', []);

            if (!is_array($items) || !count($items)) jsonFail('No items to save.');

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO attendance (employee_id, att_date, check_in, check_out, status, remarks, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), check_out=VALUES(check_out),
                         status=VALUES(status), remarks=VALUES(remarks), updated_at=NOW()'
                );
                foreach ($items as $row) {
                    $eid = (int)($row['employee_id'] ?? 0);
                    if ($eid <= 0) continue;
                    $st = in_array($row['status'] ?? '', ['present','absent','late','half_day','leave'], true) ? $row['status'] : 'present';
                    $stmt->execute([$eid, $date, $row['check_in'] ?? null, $row['check_out'] ?? null, $st, $row['remarks'] ?? null]);
                }
                $pdo->commit();
                logActivity('Bulk Attendance Marked', 'employee', null, "Bulk attendance for $date");
                jsonOk(null, 'Attendance saved for ' . count($items) . ' employee(s).');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                jsonFail($ex->getMessage(), 500);
            }
            break;

        case 'checkin':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            $eid = (int)post('employee_id', 0);
            if ($eid <= 0) jsonFail('Employee required.');
            $now = date('H:i:s');
            $today = date('Y-m-d');

            // Determine if late (after 8:15 AM)
            $status = (strtotime($now) > strtotime('08:15:00')) ? 'late' : 'present';

            $pdo->prepare(
                'INSERT INTO attendance (employee_id, att_date, check_in, status, created_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE check_in = COALESCE(check_in, VALUES(check_in)),
                     status = VALUES(status), updated_at = NOW()'
            )->execute([$eid, $today, $now, $status]);

            jsonOk(['check_in'=>$now, 'status'=>$status], 'Checked in at ' . $now);
            break;

        case 'checkout':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            $eid = (int)post('employee_id', 0);
            if ($eid <= 0) jsonFail('Employee required.');
            $now = date('H:i:s');
            $today = date('Y-m-d');

            $pdo->prepare('UPDATE attendance SET check_out = ?, updated_at = NOW() WHERE employee_id = ? AND att_date = ?')
                ->execute([$now, $eid, $today]);
            jsonOk(['check_out'=>$now], 'Checked out at ' . $now);
            break;

        case 'summary':
            if (!hasPermission('attendance.manage') && !hasPermission('employee.manage')) jsonFail('Forbidden', 403);

            $date = trim((string)get('date', date('Y-m-d')));

            $stats = $pdo->prepare(
                "SELECT COALESCE(a.status, 'not_marked') AS status, COUNT(*) AS c
                 FROM employees e
                 LEFT JOIN attendance a ON a.employee_id = e.id AND a.att_date = ?
                 WHERE e.deleted_at IS NULL AND e.status = 'active'
                 GROUP BY COALESCE(a.status, 'not_marked')"
            );
            $stats->execute([$date]);
            $rows = $stats->fetchAll();

            $result = ['present'=>0,'absent'=>0,'late'=>0,'half_day'=>0,'leave'=>0,'not_marked'=>0];
            foreach ($rows as $r) $result[$r['status']] = (int)$r['c'];

            jsonOk($result);
            break;

        case 'report':
            if (!hasPermission('attendance.manage')) jsonFail('Forbidden', 403);

            $from = trim((string)get('date_from', date('Y-m-01')));
            $to   = trim((string)get('date_to', date('Y-m-d')));
            $deptId = (int)get('department_id', 0);

            $deptCond = $deptId > 0 ? 'AND e.department_id = ' . $deptId : '';

            $sql = "SELECT e.id, e.employee_no, e.full_name, d.name AS department_name,
                           SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present_days,
                           SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) AS absent_days,
                           SUM(CASE WHEN a.status='late' THEN 1 ELSE 0 END) AS late_days,
                           SUM(CASE WHEN a.status='leave' THEN 1 ELSE 0 END) AS leave_days,
                           SUM(CASE WHEN a.status='half_day' THEN 0.5 ELSE 0 END) AS half_days
                    FROM employees e
                    LEFT JOIN departments d ON d.id = e.department_id
                    LEFT JOIN attendance a ON a.employee_id = e.id AND a.att_date BETWEEN ? AND ?
                    WHERE e.deleted_at IS NULL $deptCond
                    GROUP BY e.id
                    ORDER BY e.full_name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$from, $to]);
            jsonOk(['items' => $stmt->fetchAll(), 'from'=>$from, 'to'=>$to]);
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API ATTENDANCE] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}