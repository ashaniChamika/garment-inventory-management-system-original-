<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo    = db();
$action = get('action', 'list');

try {
    switch ($action) {

        /* ---------------- LIST ---------------- */
        case 'list':
            if (!hasPermission('supplier.manage') && !hasPermission('purchase.manage')) {
                jsonFail('Forbidden', 403);
            }

            $q      = trim((string)get('q', ''));
            $status = trim((string)get('status', ''));
            $page   = max(1, (int)get('page', 1));
            $perPage = 15;

            $where  = ['s.deleted_at IS NULL'];
            $params = [];

            if ($q !== '') {
                $where[] = '(s.company_name LIKE ? OR s.contact_person LIKE ? OR s.phone LIKE ? OR s.email LIKE ? OR s.city LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like, $like, $like, $like);
            }
            if ($status !== '') { $where[] = 's.status = ?'; $params[] = $status; }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $c = $pdo->prepare("SELECT COUNT(*) FROM suppliers s $whereSql");
            $c->execute($params);
            $total = (int)$c->fetchColumn();
            $pg = paginate($total, $perPage, $page);

            $sql = "SELECT s.*,
                           (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id AND po.deleted_at IS NULL) AS po_count,
                           (SELECT COALESCE(SUM(po.total),0) FROM purchase_orders po
                              WHERE po.supplier_id = s.id AND po.status <> 'cancelled' AND po.deleted_at IS NULL) AS total_purchased,
                           (SELECT COALESCE(SUM(po.total - po.paid_amount),0) FROM purchase_orders po
                              WHERE po.supplier_id = s.id AND po.status IN ('received','partially_received') AND po.deleted_at IS NULL) AS outstanding
                    FROM suppliers s
                    $whereSql
                    ORDER BY s.company_name ASC
                    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                $r['status_badge']   = statusBadge($r['status']);
                $r['total_purchased']= (float)$r['total_purchased'];
                $r['outstanding']    = (float)$r['outstanding'];
            }
            unset($r);

            jsonOk(['items' => $rows, 'pagination' => $pg]);
            break;

        /* ---------------- GET ONE ---------------- */
        case 'get':
            if (!hasPermission('supplier.manage') && !hasPermission('purchase.manage')) jsonFail('Forbidden', 403);
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$id]);
            $s = $stmt->fetch();
            if (!$s) jsonFail('Supplier not found.', 404);

            $c = $pdo->prepare('SELECT * FROM supplier_contacts WHERE supplier_id = ? ORDER BY id ASC');
            $c->execute([$id]);
            $s['contacts'] = $c->fetchAll();
            jsonOk($s);
            break;

        /* ---------------- SAVE ---------------- */
        case 'save':
            if (isPost() && !verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('supplier.manage')) jsonFail('Forbidden', 403);

            $id            = (int)post('id', 0);
            $companyName   = clean((string)post('company_name', ''), 190);
            $contactPerson = clean((string)post('contact_person', ''), 150);
            $phone         = clean((string)post('phone', ''), 30);
            $email         = clean((string)post('email', ''), 190);
            $address       = clean((string)post('address', ''), 400);
            $city          = clean((string)post('city', ''), 100);
            $country       = clean((string)post('country', 'Sri Lanka'), 100);
            $taxNumber     = clean((string)post('tax_number', ''), 60);
            $paymentTerms  = clean((string)post('payment_terms', ''), 120);
            $bankDetails   = clean((string)post('bank_details', ''), 400);
            $opening       = decimal_or(post('opening_balance', '0'), 0);
            $status        = post('status', 'active') === 'inactive' ? 'inactive' : 'active';

            $errors = [];
            if ($companyName === '') $errors[] = 'Company name is required.';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
            if ($errors) jsonFail('Validation failed.', 422, $errors);

            try {
                if ($id > 0) {
                    $up = $pdo->prepare(
                        'UPDATE suppliers SET company_name=?, contact_person=?, phone=?, email=?, address=?,
                            city=?, country=?, tax_number=?, payment_terms=?, bank_details=?, opening_balance=?,
                            status=?, updated_at=NOW()
                         WHERE id=? AND deleted_at IS NULL'
                    );
                    $up->execute([$companyName, $contactPerson ?: null, $phone ?: null, $email ?: null,
                                  $address ?: null, $city ?: null, $country ?: null, $taxNumber ?: null,
                                  $paymentTerms ?: null, $bankDetails ?: null, $opening, $status, $id]);
                    logActivity('Supplier Updated', 'supplier', $id, "Updated: $companyName");
                    jsonOk(['id' => $id], 'Supplier updated.');
                } else {
                    $in = $pdo->prepare(
                        'INSERT INTO suppliers (company_name, contact_person, phone, email, address, city, country,
                            tax_number, payment_terms, bank_details, opening_balance, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $in->execute([$companyName, $contactPerson ?: null, $phone ?: null, $email ?: null,
                                  $address ?: null, $city ?: null, $country ?: null, $taxNumber ?: null,
                                  $paymentTerms ?: null, $bankDetails ?: null, $opening, $status]);
                    $newId = (int)$pdo->lastInsertId();
                    logActivity('Supplier Created', 'supplier', $newId, "Created: $companyName");
                    jsonOk(['id' => $newId], 'Supplier created.');
                }
            } catch (Throwable $ex) {
                error_log('[GIMS SUPPLIER SAVE] ' . $ex->getMessage());
                jsonFail('Could not save supplier.', 500);
            }
            break;

        /* ---------------- DELETE ---------------- */
        case 'delete':
            if (isPost() && !verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('supplier.manage')) jsonFail('Forbidden', 403);

            $id = (int)post('id', 0);
            if ($id <= 0) jsonFail('Invalid supplier ID.');

            // Prevent deletion if there are active POs
            $chk = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ? AND status NOT IN ('cancelled','received') AND deleted_at IS NULL");
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) {
                jsonFail('Cannot delete supplier with active purchase orders.');
            }

            $n = $pdo->prepare('SELECT company_name FROM suppliers WHERE id = ? AND deleted_at IS NULL');
            $n->execute([$id]);
            $name = $n->fetchColumn();
            if (!$name) jsonFail('Supplier not found.', 404);

            $pdo->prepare('UPDATE suppliers SET deleted_at = NOW(), status = "inactive" WHERE id = ?')->execute([$id]);
            logActivity('Supplier Deleted', 'supplier', $id, "Deleted: $name");
            jsonOk(null, 'Supplier deleted.');
            break;

        /* ---------------- LOOKUP (for selects) ---------------- */
        case 'lookup':
            $q = trim((string)get('q', ''));
            $where = ['deleted_at IS NULL', 'status = "active"'];
            $params = [];
            if ($q !== '') {
                $where[] = '(company_name LIKE ? OR contact_person LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like);
            }
            $stmt = $pdo->prepare('SELECT id, company_name, contact_person, phone, email, payment_terms
                                    FROM suppliers WHERE ' . implode(' AND ', $where) . '
                                    ORDER BY company_name ASC LIMIT 50');
            $stmt->execute($params);
            jsonOk(['items' => $stmt->fetchAll()]);
            break;

        /* ---------------- CONTACTS ---------------- */
        case 'save_contact':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('supplier.manage')) jsonFail('Forbidden', 403);

            $id         = (int)post('id', 0);
            $supplierId = (int)post('supplier_id', 0);
            $name       = clean((string)post('name', ''), 150);
            $position   = clean((string)post('position', ''), 120);
            $phone      = clean((string)post('phone', ''), 30);
            $email      = clean((string)post('email', ''), 190);

            if ($supplierId <= 0) jsonFail('Supplier is required.');
            if ($name === '') jsonFail('Name is required.');

            if ($id > 0) {
                $up = $pdo->prepare('UPDATE supplier_contacts SET name=?, position=?, phone=?, email=? WHERE id=? AND supplier_id=?');
                $up->execute([$name, $position ?: null, $phone ?: null, $email ?: null, $id, $supplierId]);
                jsonOk(['id' => $id], 'Contact updated.');
            } else {
                $in = $pdo->prepare('INSERT INTO supplier_contacts (supplier_id, name, position, phone, email, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                $in->execute([$supplierId, $name, $position ?: null, $phone ?: null, $email ?: null]);
                jsonOk(['id' => (int)$pdo->lastInsertId()], 'Contact added.');
            }
            break;

        case 'delete_contact':
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);
            if (!hasPermission('supplier.manage')) jsonFail('Forbidden', 403);
            $id = (int)post('id', 0);
            $pdo->prepare('DELETE FROM supplier_contacts WHERE id = ?')->execute([$id]);
            jsonOk(null, 'Contact deleted.');
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API SUPPLIERS] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}