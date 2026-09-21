<?php
/**
 * Stock engine — the single source of truth for stock changes.
 * Every stock mutation MUST go through these functions so the ledger stays correct.
 */

declare(strict_types=1);

if (!defined('GIMS_APP')) { exit('Direct access denied'); }

/**
 * Get current quantity for a product/variant/warehouse combination.
 */
function getStockQty(PDO $pdo, int $productId, int $variantId, int $warehouseId): float
{
    $stmt = $pdo->prepare(
        'SELECT quantity FROM stock WHERE product_id = ? AND variant_id = ? AND warehouse_id = ?'
    );
    $stmt->execute([$productId, $variantId, $warehouseId]);
    $q = $stmt->fetchColumn();
    return $q === false ? 0.0 : (float)$q;
}

/**
 * Ensure a stock row exists (creates with qty 0 if missing).
 * Returns the stock row id.
 */
function ensureStockRow(PDO $pdo, int $productId, int $variantId, int $warehouseId): int
{
    $stmt = $pdo->prepare('SELECT id FROM stock WHERE product_id = ? AND variant_id = ? AND warehouse_id = ?');
    $stmt->execute([$productId, $variantId, $warehouseId]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;

    $ins = $pdo->prepare(
        'INSERT INTO stock (product_id, variant_id, warehouse_id, quantity, reserved_qty, damaged_qty, rejected_qty, updated_at)
         VALUES (?, ?, ?, 0, 0, 0, 0, NOW())'
    );
    $ins->execute([$productId, $variantId, $warehouseId]);
    return (int)$pdo->lastInsertId();
}

/**
 * Apply a stock movement. Handles the ledger + the balance atomically.
 *
 * @param array $opts [
 *    'product_id'      => int,
 *    'variant_id'      => int (default 0),
 *    'warehouse_id'    => int,
 *    'movement_type'   => enum value,
 *    'direction'       => 'in'|'out',
 *    'quantity'        => float,
 *    'unit_cost'       => float (optional),
 *    'reference_type'  => string (optional),
 *    'reference_id'    => int (optional),
 *    'reference_no'    => string (optional),
 *    'notes'           => string (optional),
 * ]
 * @return int movement row id
 * @throws RuntimeException on negative stock or other errors
 */
function applyStockMovement(PDO $pdo, array $opts): int
{
    $required = ['product_id','warehouse_id','movement_type','direction','quantity'];
    foreach ($required as $k) {
        if (!isset($opts[$k])) {
            throw new RuntimeException("Missing required stock movement key: $k");
        }
    }

    $productId    = (int)$opts['product_id'];
    $variantId    = (int)($opts['variant_id'] ?? 0);
    $warehouseId  = (int)$opts['warehouse_id'];
    $movementType = (string)$opts['movement_type'];
    $direction    = $opts['direction'] === 'out' ? 'out' : 'in';
    $quantity     = (float)$opts['quantity'];
    $unitCost     = (float)($opts['unit_cost'] ?? 0);
    $refType      = $opts['reference_type'] ?? null;
    $refId        = isset($opts['reference_id']) ? (int)$opts['reference_id'] : null;
    $refNo        = $opts['reference_no'] ?? null;
    $notes        = $opts['notes'] ?? null;

    if ($quantity <= 0) {
        throw new RuntimeException('Stock movement quantity must be greater than zero.');
    }

    // Lock the row (or create it)
    ensureStockRow($pdo, $productId, $variantId, $warehouseId);

    $lock = $pdo->prepare(
        'SELECT quantity FROM stock
         WHERE product_id = ? AND variant_id = ? AND warehouse_id = ?
         FOR UPDATE'
    );
    $lock->execute([$productId, $variantId, $warehouseId]);
    $current = (float)$lock->fetchColumn();

    if ($direction === 'out' && !ALLOW_NEGATIVE_STOCK && $current < $quantity) {
        throw new RuntimeException(
            sprintf('Insufficient stock: available %.4f, requested %.4f.', $current, $quantity)
        );
    }

    $newBalance = $direction === 'in' ? $current + $quantity : $current - $quantity;

    $up = $pdo->prepare('UPDATE stock SET quantity = ?, updated_at = NOW() WHERE product_id = ? AND variant_id = ? AND warehouse_id = ?');
    $up->execute([$newBalance, $productId, $variantId, $warehouseId]);

    // Insert ledger row
    $ins = $pdo->prepare(
        'INSERT INTO stock_movements
            (product_id, variant_id, warehouse_id, movement_type, direction, reference_type,
             reference_id, reference_no, quantity, balance_after, unit_cost, notes, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $ins->execute([
        $productId, $variantId, $warehouseId, $movementType, $direction,
        $refType, $refId, $refNo, $quantity, $newBalance, $unitCost, $notes,
        currentUserId()
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Low-stock watcher: push a notification when a product drops at/below reorder level.
 */
function maybeAlertLowStock(PDO $pdo, int $productId): void
{
    try {
        $stmt = $pdo->prepare(
            'SELECT p.name, p.sku, p.reorder_level,
                    COALESCE(SUM(s.quantity),0) qty
             FROM products p
             LEFT JOIN stock s ON s.product_id = p.id
             WHERE p.id = ?
             GROUP BY p.id'
        );
        $stmt->execute([$productId]);
        $row = $stmt->fetch();
        if (!$row) return;

        if ((float)$row['qty'] <= (float)$row['reorder_level']) {
            // Avoid spamming: only alert once every 24h per product
            $chk = $pdo->prepare(
                "SELECT COUNT(*) FROM notifications
                 WHERE module = 'inventory' AND title LIKE ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $chk->execute(['Low Stock: ' . $row['name'] . '%']);
            if ((int)$chk->fetchColumn() > 0) return;

            pushNotification(
                'Low Stock: ' . $row['name'],
                "Product {$row['sku']} has fallen to " . number_format((float)$row['qty'], 2) .
                    ' (reorder level ' . number_format((float)$row['reorder_level'], 2) . ').',
                'warning',
                'inventory',
                BASE_URL . '/inventory/stock.php?product_id=' . $productId
            );
        }
    } catch (Throwable $e) {
        error_log('[GIMS LOW STOCK ALERT] ' . $e->getMessage());
    }
}