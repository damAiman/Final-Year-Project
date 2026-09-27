<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('products/index.php');
}

$id = (int) ($_POST['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare('SELECT name, image FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Product not found.');
    redirect('products/index.php');
}

/* stock_in / stock_out reference products with ON DELETE RESTRICT, so a product
   that already has transaction history cannot be deleted -- this protects the
   integrity of past records. Staff should stop using a product going forward
   instead of deleting one that has movement history. */
try {
    $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);

    if (!empty($product['image'])) {
        $path = __DIR__ . '/../uploads/products/' . $product['image'];
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    set_flash('success', 'Product "' . $product['name'] . '" was deleted.');

    log_activity('Product Management', 'Deleted the product "' . $product['name'] . '"', null, $product['name']);
    log_audit('Product Management', 'DELETE', 'Product', $id, [
        'name' => ['old' => $product['name'], 'new' => null],
    ]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000 || strpos($e->getMessage(), 'foreign key') !== false) {
        set_flash('danger', 'Cannot delete "' . $product['name'] . '" — it already has stock in/out transaction history. Remove or reassign those records first.');
    } else {
        set_flash('danger', 'Could not delete this product. Please try again.');
    }
}

redirect('products/index.php');
