<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$pdo    = db();
$action = get('action', 'product_info');

try {
    switch ($action) {

        case 'product_info':
            if (!hasPermission('barcode.manage')) jsonFail('Forbidden', 403);
            $id = (int)get('id', 0);
            $stmt = $pdo->prepare(
                'SELECT p.id, p.sku, p.product_code, p.name, p.barcode, p.unit, p.cost_price, p.selling_price,
                        c.name AS category_name
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.id = ? AND p.deleted_at IS NULL'
            );
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) jsonFail('Product not found.', 404);

            if (empty($p['barcode'])) {
                $p['barcode'] = 'GIMS' . str_pad((string)$p['id'], 8, '0', STR_PAD_LEFT);
                $pdo->prepare('UPDATE products SET barcode = ? WHERE id = ?')->execute([$p['barcode'], $id]);
            }
            $p['qr_data'] = json_encode([
                'id'    => (int)$p['id'],
                'sku'   => $p['sku'],
                'name'  => $p['name'],
                'unit'  => $p['unit'],
                'price' => (float)$p['selling_price'],
            ]);
            jsonOk($p);
            break;

        case 'lookup_barcode':
            // Scan a barcode and find the matching product / variant
            $code = trim((string)get('code', ''));
            if ($code === '') jsonFail('Barcode is required.');

            // Try product variant first
            $v = $pdo->prepare(
                'SELECT v.id AS variant_id, v.product_id, v.sku AS variant_sku, v.size, v.color,
                        p.name, p.sku, p.unit, p.selling_price, p.barcode
                 FROM product_variants v
                 JOIN products p ON p.id = v.product_id
                 WHERE v.barcode = ? AND v.deleted_at IS NULL
                 LIMIT 1'
            );
            $v->execute([$code]);
            $row = $v->fetch();
            if ($row) { jsonOk(['type'=>'variant', 'data'=>$row]); break; }

            // Then try base product
            $p = $pdo->prepare(
                'SELECT id, name, sku, product_code, unit, selling_price, barcode
                 FROM products WHERE (barcode = ? OR sku = ? OR product_code = ?) AND deleted_at IS NULL
                 LIMIT 1'
            );
            $p->execute([$code, $code, $code]);
            $row = $p->fetch();
            if ($row) { jsonOk(['type'=>'product', 'data'=>$row]); break; }

            jsonFail('No product matched that barcode.', 404);
            break;

        case 'bulk_generate':
            // Generate barcodes for products missing them
            if (!hasPermission('barcode.manage')) jsonFail('Forbidden', 403);
            if (!verifyCsrf()) jsonFail('Invalid CSRF token.', 419);

            $rows = $pdo->query('SELECT id FROM products WHERE (barcode IS NULL OR barcode = "") AND deleted_at IS NULL')->fetchAll(PDO::FETCH_COLUMN);
            $up = $pdo->prepare('UPDATE products SET barcode = ? WHERE id = ?');
            $count = 0;
            foreach ($rows as $id) {
                $up->execute(['GIMS' . str_pad((string)$id, 8, '0', STR_PAD_LEFT), (int)$id]);
                $count++;
            }
            logActivity('Barcodes Generated', 'barcode', null, "Generated $count barcodes");
            jsonOk(['count'=>$count], "$count barcode(s) generated.");
            break;

        case 'print_batch':
            // Returns list of products with their barcode + qty for bulk printing
            if (!hasPermission('barcode.manage')) jsonFail('Forbidden', 403);
            $ids = array_filter(array_map('intval', explode(',', (string)get('ids', ''))));
            if (empty($ids)) jsonFail('No products selected.');

            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare(
                "SELECT p.id, p.name, p.sku, p.barcode, p.selling_price, c.name AS category_name
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.id IN ($in) AND p.deleted_at IS NULL"
            );
            $stmt->execute($ids);
            $items = $stmt->fetchAll();

            // Ensure barcodes exist
            $up = $pdo->prepare('UPDATE products SET barcode = ? WHERE id = ?');
            foreach ($items as &$it) {
                if (empty($it['barcode'])) {
                    $it['barcode'] = 'GIMS' . str_pad((string)$it['id'], 8, '0', STR_PAD_LEFT);
                    $up->execute([$it['barcode'], (int)$it['id']]);
                }
            }
            unset($it);

            jsonOk(['items'=>$items]);
            break;

        default:
            jsonFail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('[GIMS API BARCODE] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}