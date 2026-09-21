<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

try {
    $stats = [
        'products'          => (int)$pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn(),
        'total_stock_qty'   => (float)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM stock")->fetchColumn(),
        'low_stock_items'   => (int)$pdo->query("SELECT COUNT(*) FROM (
                                    SELECT p.id FROM products p
                                    LEFT JOIN stock s ON s.product_id = p.id
                                    WHERE p.deleted_at IS NULL AND p.status = 'active'
                                    GROUP BY p.id, p.reorder_level
                                    HAVING COALESCE(SUM(s.quantity),0) <= p.reorder_level AND COALESCE(SUM(s.quantity),0) > 0
                                ) AS t")->fetchColumn(),
        'out_of_stock'      => (int)$pdo->query("SELECT COUNT(*) FROM (
                                    SELECT p.id FROM products p
                                    LEFT JOIN stock s ON s.product_id = p.id
                                    WHERE p.deleted_at IS NULL AND p.status='active'
                                    GROUP BY p.id HAVING COALESCE(SUM(s.quantity),0) <= 0
                                ) AS t")->fetchColumn(),
        'suppliers'         => (int)$pdo->query("SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL")->fetchColumn(),
        'customers'         => (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL")->fetchColumn(),
        'pending_po'        => (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('pending','approved','partially_received') AND deleted_at IS NULL")->fetchColumn(),
        'production_orders' => (int)$pdo->query("SELECT COUNT(*) FROM production_orders WHERE status IN ('planned','in_progress','paused') AND deleted_at IS NULL")->fetchColumn(),
        'completed_prod'    => (int)$pdo->query("SELECT COUNT(*) FROM production_orders WHERE status='completed' AND deleted_at IS NULL")->fetchColumn(),
        'pending_qc'        => (int)$pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE status='pending'")->fetchColumn(),
        'today_sales'       => (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE DATE(invoice_date)=CURDATE() AND status <> 'cancelled'")->fetchColumn(),
        'month_sales'       => (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE MONTH(invoice_date)=MONTH(CURDATE()) AND YEAR(invoice_date)=YEAR(CURDATE()) AND status <> 'cancelled'")->fetchColumn(),
        'inventory_value'   => (float)$pdo->query("SELECT COALESCE(SUM(s.quantity * p.cost_price),0) FROM stock s JOIN products p ON p.id=s.product_id")->fetchColumn(),
    ];

    jsonOk($stats);
} catch (Throwable $e) {
    error_log('[GIMS API DASHBOARD] ' . $e->getMessage());
    jsonFail('Unable to fetch dashboard statistics.', 500);
}