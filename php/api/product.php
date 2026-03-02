<?php
require_once __DIR__ . '/../config.php';
checkAuth();

$sku = $_GET['sku'] ?? '';

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU requerido']);
    exit();
}

try {
    $products = wcRequest('/products?sku=' . urlencode($sku));

    // Buscar match exacto
    $product = null;
    foreach ($products as $p) {
        if ($p['sku'] === $sku) {
            $product = $p;
            break;
        }
    }

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Producto con SKU \"$sku\" no encontrado"]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'product' => [
            'id' => $product['id'],
            'sku' => $product['sku'],
            'name' => $product['name'],
            'type' => $product['type'],
            'stock_quantity' => $product['stock_quantity'],
            'regular_price' => $product['regular_price'],
            'sale_price' => $product['sale_price']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
