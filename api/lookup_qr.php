<?php

require_once __DIR__ . '/../config/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    echo json_encode(['ok' => false, 'message' => 'No QR code was received.']);
    exit;
}

/* Some printed labels encode a URL (e.g. https://botolanggun.com/p/BTL-PET-500)
   rather than the bare code. Take the last path segment in that case so both
   styles of label work. */
if (preg_match('#^https?://#i', $code)) {
    $path = parse_url($code, PHP_URL_PATH) ?: '';
    $segments = array_values(array_filter(explode('/', $path)));
    if (!empty($segments)) {
        $code = end($segments);
    }
}

$db = getDB();

/* Match on the stored QR payload first, then fall back to SKU -- so a label
   printed before the qr_code column was populated still resolves. */
$stmt = $db->prepare(
    "SELECT p.*,
            COALESCE(c.name,'Uncategorised') AS category_name,
            COALESCE(s.name,'—')            AS supplier_name
       FROM products p
       LEFT JOIN categories c ON c.id = p.category_id
       LEFT JOIN suppliers  s ON s.id = p.supplier_id
      WHERE p.qr_code = ? OR p.sku = ?
      LIMIT 1"
);
$stmt->execute([$code, $code]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode([
        'ok'      => false,
        'message' => 'No product is linked to this QR code (' . $code . ').',
    ]);
    exit;
}

$status = stock_status((int) $product['current_stock'], (int) $product['min_stock']);

echo json_encode([
    'ok'      => true,
    'product' => [
        'id'            => (int) $product['id'],
        'sku'           => $product['sku'],
        'name'          => $product['name'],
        'category'      => $product['category_name'],
        'supplier'      => $product['supplier_name'],
        'unit'          => $product['unit'],
        'current_stock' => (int) $product['current_stock'],
        'min_stock'     => (int) $product['min_stock'],
        'location'      => $product['storage_location'],
        'image'         => product_image_url($product['image'], $product['category_name']),
        'status_label'  => $status['label'],
        'status_class'  => $status['class'],
    ],
]);
