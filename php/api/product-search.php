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
    // Buscar directamente en WooCommerce
    $wooProducto = null;
    $producto = null;

    $wcProducts = wcRequest('/products?sku=' . urlencode($sku));

    if (!empty($wcProducts)) {
        foreach ($wcProducts as $p) {
            if ($p['sku'] === $sku) {
                $wooProducto = [
                    'id' => $p['id'],
                    'status' => $p['status'],
                    'permalink' => $p['permalink'],
                    'regular_price' => $p['regular_price'],
                    'stock_quantity' => $p['stock_quantity']
                ];

                // Crear producto desde datos de WooCommerce
                $producto = [
                    'sku' => $p['sku'],
                    'nombre' => $p['name'],
                    'part_number' => $p['sku'],
                    'descripcion_larga' => strip_tags($p['description'] ?? ''),
                    'precio_sin_iva' => round(floatval($p['regular_price']) / 1.21, 2),
                    'precio' => $p['regular_price'],
                    'stock' => $p['stock_quantity'] ?? 0,
                    'peso' => $p['weight'] ?? null,
                    'alto' => $p['dimensions']['height'] ?? null,
                    'ancho' => $p['dimensions']['width'] ?? null,
                    'profundidad' => $p['dimensions']['length'] ?? null,
                    'atributos' => []
                ];

                // Extraer atributos
                if (!empty($p['attributes'])) {
                    foreach ($p['attributes'] as $attr) {
                        $producto['atributos'][] = [
                            'nombre' => $attr['name'],
                            'valor' => implode(', ', $attr['options'] ?? [])
                        ];
                    }
                }
                break;
            }
        }
    }

    if ($producto === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Producto '$sku' no encontrado en WooCommerce"]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'producto' => $producto,
        'woo_producto' => $wooProducto
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
