<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('products/index.php');
}

$db = getDB();

$id              = trim($_POST['id'] ?? '');
$name            = trim($_POST['name'] ?? '');
$categoryId      = ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null;
$supplierId      = ($_POST['supplier_id'] ?? '') !== '' ? (int) $_POST['supplier_id'] : null;
$unit            = trim($_POST['unit'] ?? 'pcs');
$pricePerUnit    = (float) ($_POST['price_per_unit'] ?? 0);
$sellingPrice    = (float) ($_POST['selling_price'] ?? 0);
$currentStock    = (int) ($_POST['current_stock'] ?? 0);
$minStock        = (int) ($_POST['min_stock'] ?? 0);
$storageLocation = trim($_POST['storage_location'] ?? '');
$qrCode          = trim($_POST['qr_code'] ?? '');
$description     = trim($_POST['description'] ?? '');

/* -------------------------------------------------- Validation -------------------------------------------------- */
$errors = [];
if ($name === '') {
    $errors[] = 'Product name is required.';
}
if (!$categoryId) {
    $errors[] = 'Please select a category.';
}
if (!$supplierId) {
    $errors[] = 'Please select a supplier.';
}
if ($currentStock < 0 || $minStock < 0) {
    $errors[] = 'Stock quantities cannot be negative.';
}
if ($pricePerUnit < 0) {
    $errors[] = 'Price per unit cannot be negative.';
}
if ($sellingPrice < 0) {
    $errors[] = 'Selling price cannot be negative.';
}

if ($errors) {
    set_flash('danger', implode(' ', $errors));
    redirect('products/index.php');
}

/* -------------------------------------------------- Image upload -------------------------------------------------- */
$imageFilename = null; // null = "no change" when editing; set to a filename or '' explicitly when needed
$uploadDir = __DIR__ . '/../uploads/products/';

if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        set_flash('danger', 'Product image must be a JPG, PNG, or WEBP file.');
        redirect('products/index.php');
    }
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        set_flash('danger', 'Product image must be smaller than 5MB.');
        redirect('products/index.php');
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $imageFilename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageFilename)) {
        set_flash('danger', 'Could not save the uploaded image. Please try again.');
        redirect('products/index.php');
    }
}

/* -------------------------------------------------- Insert / Update -------------------------------------------------- */
if ($id !== '') {
    /* ---- Editing an existing product ---- */
    $stmt = $db->prepare('SELECT image FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        set_flash('danger', 'Product not found.');
        redirect('products/index.php');
    }

    if ($imageFilename === null) {
        // No new file uploaded -- keep the existing image.
        $imageFilename = $existing['image'];
    } elseif (!empty($existing['image']) && file_exists($uploadDir . $existing['image'])) {
        // A new image replaced the old one -- remove the old file.
        @unlink($uploadDir . $existing['image']);
    }

    $stmt = $db->prepare(
        'UPDATE products SET name=?, category_id=?, supplier_id=?, unit=?, price_per_unit=?, selling_price=?, current_stock=?, min_stock=?,
         storage_location=?, qr_code=?, description=?, image=? WHERE id=?'
    );
    $stmt->execute([
        $name, $categoryId, $supplierId, $unit, $pricePerUnit, $sellingPrice, $currentStock, $minStock,
        $storageLocation, ($qrCode !== '' ? $qrCode : null), $description, $imageFilename, $id,
    ]);
    set_flash('success', 'Product "' . $name . '" was updated successfully.');

    log_activity('Product Management', 'Updated the product "' . $name . '"', $id, $name);
    log_audit('Product Management', 'UPDATE', 'Product', $id, [
        'name'             => ['old' => $existing['name'],             'new' => $name],
        'category_id'      => ['old' => $existing['category_id'],      'new' => $categoryId],
        'supplier_id'      => ['old' => $existing['supplier_id'],      'new' => $supplierId],
        'unit'             => ['old' => $existing['unit'],             'new' => $unit],
        'price_per_unit'   => ['old' => $existing['price_per_unit'],   'new' => $pricePerUnit],
        'selling_price'    => ['old' => $existing['selling_price'],    'new' => $sellingPrice],
        'current_stock'    => ['old' => $existing['current_stock'],    'new' => $currentStock],
        'min_stock'        => ['old' => $existing['min_stock'],        'new' => $minStock],
        'storage_location' => ['old' => $existing['storage_location'], 'new' => $storageLocation],
    ]);
} else {
    /* ---- Creating a new product ---- */
    $sku = generate_sku($db, $name);

    $stmt = $db->prepare(
        'INSERT INTO products (sku, qr_code, name, category_id, supplier_id, unit, price_per_unit, selling_price, current_stock, min_stock, storage_location, description, image)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $sku, ($qrCode !== '' ? $qrCode : $sku), $name, $categoryId, $supplierId, $unit, $pricePerUnit, $sellingPrice, $currentStock, $minStock,
        $storageLocation, $description, $imageFilename,
    ]);
    $newId = (int) $db->lastInsertId();
    set_flash('success', 'Product "' . $name . '" was added successfully.');

    log_activity('Product Management', 'Added the new product "' . $name . '" (' . $sku . ')', $newId, $name, $currentStock);
    log_audit('Product Management', 'CREATE', 'Product', $newId, [
        'name'          => ['old' => null, 'new' => $name],
        'sku'           => ['old' => null, 'new' => $sku],
        'current_stock' => ['old' => null, 'new' => $currentStock],
        'min_stock'     => ['old' => null, 'new' => $minStock],
    ]);
}

redirect('products/index.php');
