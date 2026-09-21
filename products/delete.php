<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('product.delete');

if (!isPost()) { redirect('products/index.php'); }
if (!verifyCsrf()) { flash('danger', 'Security token expired.'); redirect('products/index.php'); }

$id = (int)post('id', 0);
if ($id <= 0) { flash('danger', 'Invalid product.'); redirect('products/index.php'); }

$pdo = db();
$stmt = $pdo->prepare('SELECT name FROM products WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$id]);
$name = $stmt->fetchColumn();
if (!$name) { flash('danger', 'Product not found.'); redirect('products/index.php'); }

$pdo->prepare('UPDATE products SET deleted_at = NOW(), status = "inactive" WHERE id = ?')->execute([$id]);
logActivity('Product Deleted', 'product', $id, "Deleted product: $name");

flash('success', "Product \"$name\" deleted successfully.");
redirect('products/index.php');