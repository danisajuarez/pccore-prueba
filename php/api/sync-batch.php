<?php
require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}
checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$products = $input['products'] ?? [];

if (empty($products) || !is_array($products)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Se requiere un array 'products' con al menos un elemento"]);
    exit();
}

$results = [];
$successful = 0;
$failed = 0;

foreach ($products as $item) {
    $sku = $item['sku'] ?? '';

    if (empty($sku)) {
        $results[] = ['sku' => 'unknown', 'success' => false, 'error' => 'SKU faltante'];
        $failed++;
        continue;
    }

    try {
        // Buscar producto
        $wcProducts = wcRequest('/products?sku=' . urlencode($sku));

        $product = null;
        foreach ($wcProducts as $p) {
            if ($p['sku'] === $sku) {
                $product = $p;
                break;
            }
        }

        if (!$product) {
            $results[] = ['sku' => $sku, 'success' => false, 'error' => 'No encontrado en WooCommerce'];
            $failed++;
            continue;
        }

        // Construir payload
        $payload = [];
        if (isset($item['stock_quantity'])) {
            $payload['stock_quantity'] = (int) $item['stock_quantity'];
        }
        if (isset($item['regular_price'])) {
            $payload['regular_price'] = (string) $item['regular_price'];
        }
        if (isset($item['sale_price'])) {
            $payload['sale_price'] = (string) $item['sale_price'];
        }
        if (isset($item['price_no_taxes'])) {
            $payload['meta_data'] = [
                ['key' => '_price_no_taxes', 'value' => (string) $item['price_no_taxes']]
            ];
        }

        // Actualizar
        wcRequest('/products/' . $product['id'], 'PUT', $payload);

        $results[] = ['sku' => $sku, 'success' => true, 'product_id' => $product['id']];
        $successful++;

    } catch (Exception $e) {
        $results[] = ['sku' => $sku, 'success' => false, 'error' => $e->getMessage()];
        $failed++;
    }

    // Rate limit: 100ms entre requests
    usleep(100000);
}

echo json_encode([
    'success' => true,
    'total' => count($products),
    'successful' => $successful,
    'failed' => $failed,
    'results' => $results
]);
