<?php
require_once __DIR__ . '/config/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('stock_in.php');
}

$productId = (int) ($_POST['product_id'] ?? 0);
$quantity  = (int) ($_POST['quantity'] ?? 0);
$remarks   = trim($_POST['remarks'] ?? '');
$userId    = $_SESSION['user_id'];


$entryMethod = ($_POST['entry_method'] ?? 'manual') === 'qr' ? 'qr' : 'manual';
$redirectTo  = ($_POST['redirect_to'] ?? '') === 'scan.php?mode=in'
    ? 'scan.php?mode=in'
    : 'stock_in.php';


$rawDate = trim($_POST['transaction_date'] ?? '');
$today   = date('Y-m-d');
$dt      = DateTime::createFromFormat('Y-m-d', $rawDate);
if ($rawDate === '' || !$dt || $dt->format('Y-m-d') !== $rawDate) {
    $transactionDate = $today;
} elseif ($rawDate > $today) {
    set_flash('danger', 'The date cannot be in the future.');
    redirect($redirectTo);
} else {
    $transactionDate = $rawDate;
}

if ($productId <= 0) {
    set_flash('danger', 'Please search for and select a product first.');
    redirect($redirectTo);
}
if ($quantity <= 0) {
    set_flash('danger', 'Enter a valid quantity before saving.');
    redirect($redirectTo);
}

$db = getDB();

try {
    $db->beginTransaction();

    $stmt = $db->prepare('SELECT id, name, unit, current_stock FROM products WHERE id = ? FOR UPDATE');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        $db->rollBack();
        set_flash('danger', 'That product could not be found. It may have been removed.');
        redirect($redirectTo);
    }

    $stmt = $db->prepare('INSERT INTO stock_in (product_id, quantity, transaction_date, remarks, entry_method, user_id) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$productId, $quantity, $transactionDate, $remarks !== '' ? $remarks : null, $entryMethod, $userId]);

    $stmt = $db->prepare('UPDATE products SET current_stock = current_stock + ? WHERE id = ?');
    $stmt->execute([$quantity, $productId]);

    $db->commit();

    $newStock = (int) $product['current_stock'] + $quantity;
    $dateLabel = date('d M Y', strtotime($transactionDate));
    set_flash('success', 'Stock in saved: +' . number_format($quantity) . ' ' . $product['unit'] . ' of "' . $product['name'] . '" (dated ' . $dateLabel . '). New stock level: ' . number_format($newStock) . ' ' . $product['unit'] . '.');


    log_activity('Stock In',
        'Received +' . number_format($quantity) . ' ' . $product['unit'] . ' of ' . $product['name']
        . ($entryMethod === 'qr' ? ' (via QR scan)' : ''),
        $productId, $product['name'], $quantity);

    log_audit('Stock In', 'UPDATE', 'Product', $productId, [
        'current_stock' => ['old' => $product['current_stock'], 'new' => $newStock],
    ]);

    notify('stock_in', 'Stock in recorded',
        $product['name'] . ' +' . number_format($quantity) . ' ' . $product['unit']
        . ' — new level ' . number_format($newStock) . ' ' . $product['unit'],
        'stock_in.php');

    try {
        $chk = $db->prepare('SELECT name, current_stock, min_stock, unit FROM products WHERE id = ?');
        $chk->execute([$productId]);
        if ($p2 = $chk->fetch()) {
            if ((int) $p2['current_stock'] < (int) $p2['min_stock']) {
                notify('low_stock', 'Low stock: ' . $p2['name'],
                    'Only ' . number_format($p2['current_stock']) . ' ' . $p2['unit']
                    . ' left (minimum ' . number_format($p2['min_stock']) . ')',
                    'low_stock.php');
            }
        }
    } catch (Exception $e) { /* best effort */ }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    set_flash('danger', 'Something went wrong while saving this transaction. Please try again.');
}

redirect($redirectTo);
