<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$db = getDB();
$q  = trim($_GET['q'] ?? '');

if ($q !== '') {
    $stmt = $db->prepare(
        "SELECT p.id, p.name, p.sku, p.unit, p.current_stock, p.min_stock, p.storage_location, p.image,
                COALESCE(c.name,'Uncategorised') AS category, COALESCE(s.name,'—') AS supplier
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.name LIKE :q OR p.sku LIKE :q OR c.name LIKE :q
         ORDER BY p.name ASC
         LIMIT 20"
    );
    $stmt->execute(['q' => '%' . $q . '%']);
} else {
    $stmt = $db->query(
        "SELECT p.id, p.name, p.sku, p.unit, p.current_stock, p.min_stock, p.storage_location, p.image,
                COALESCE(c.name,'Uncategorised') AS category, COALESCE(s.name,'—') AS supplier
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN suppliers s ON s.id = p.supplier_id
         ORDER BY p.name ASC
         LIMIT 20"
    );
}

$rows = $stmt->fetchAll();
$results = array_map(function ($p) {
    $status = stock_status((int) $p['current_stock'], (int) $p['min_stock']);
    return [
        'id'            => (int) $p['id'],
        'name'          => $p['name'],
        'sku'           => $p['sku'],
        'category'      => $p['category'],
        'supplier'      => $p['supplier'],
        'unit'          => $p['unit'],
        'current_stock' => (int) $p['current_stock'],
        'min_stock'     => (int) $p['min_stock'],
        'location'      => $p['storage_location'],
        'image'         => product_image_url($p['image']),
        'status_label'  => $status['label'],
        'status_class'  => $status['class'],
    ];
}, $rows);

echo json_encode($results);
