<?php
/**
 * API: Búsqueda directa exhaustiva de productos en WooCommerce
 *
 * Busca productos de forma más agresiva cuando la búsqueda normal falla
 */

require_once __DIR__ . '/../config.php';
checkAuth();

$sku = $_GET['sku'] ?? '';

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU requerido']);
    exit();
}

try {
    // Estrategia 1: Buscar con status=any (todos los estados)
    $wcProducts = wcRequest('/products?sku=' . urlencode($sku) . '&status=any');

    if (!empty($wcProducts)) {
        foreach ($wcProducts as $p) {
            // Comparación exacta case-insensitive
            if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                echo json_encode([
                    'success' => true,
                    'product_id' => $p['id'],
                    'status' => $p['status'],
                    'name' => $p['name'],
                    'regular_price' => $p['regular_price'],
                    'stock_quantity' => $p['stock_quantity'],
                    'search_method' => 'direct_sku_match'
                ]);
                exit();
            }
        }
    }

    // Estrategia 2: Buscar todos los productos y filtrar manualmente (menos eficiente pero más exhaustivo)
    $allProducts = wcRequest('/products?per_page=100&status=any');

    if (!empty($allProducts)) {
        foreach ($allProducts as $p) {
            // Comparación más flexible
            $productSku = trim($p['sku'] ?? '');
            $searchSku = trim($sku);

            // Comparación exacta
            if (strcasecmp($productSku, $searchSku) === 0) {
                echo json_encode([
                    'success' => true,
                    'product_id' => $p['id'],
                    'status' => $p['status'],
                    'name' => $p['name'],
                    'regular_price' => $p['regular_price'],
                    'stock_quantity' => $p['stock_quantity'],
                    'search_method' => 'manual_filtering'
                ]);
                exit();
            }

            // Comparación sin ceros a la izquierda (01075 = 1075)
            if (ltrim($productSku, '0') === ltrim($searchSku, '0')) {
                echo json_encode([
                    'success' => true,
                    'product_id' => $p['id'],
                    'status' => $p['status'],
                    'name' => $p['name'],
                    'regular_price' => $p['regular_price'],
                    'stock_quantity' => $p['stock_quantity'],
                    'sku_encontrado' => $productSku,
                    'sku_buscado' => $searchSku,
                    'search_method' => 'normalized_match',
                    'notice' => 'El SKU en WooCommerce difiere en formato pero representa el mismo producto'
                ]);
                exit();
            }
        }
    }

    // Estrategia 3: Buscar con diferentes variaciones del SKU
    $skuVariations = [
        ltrim($sku, '0'),           // Sin ceros a la izquierda: 01075 -> 1075
        str_pad($sku, 5, '0', STR_PAD_LEFT),  // Con ceros: 1075 -> 01075
        strtoupper($sku),            // Mayúsculas
        strtolower($sku)             // Minúsculas
    ];

    foreach ($skuVariations as $variation) {
        if ($variation === $sku) continue; // Ya lo intentamos

        $wcProducts = wcRequest('/products?sku=' . urlencode($variation) . '&status=any');

        if (!empty($wcProducts)) {
            foreach ($wcProducts as $p) {
                if (strcasecmp(trim($p['sku']), trim($variation)) === 0) {
                    echo json_encode([
                        'success' => true,
                        'product_id' => $p['id'],
                        'status' => $p['status'],
                        'name' => $p['name'],
                        'regular_price' => $p['regular_price'],
                        'stock_quantity' => $p['stock_quantity'],
                        'sku_encontrado' => $p['sku'],
                        'sku_buscado' => $sku,
                        'search_method' => 'variation_match',
                        'notice' => 'Encontrado con variación de SKU'
                    ]);
                    exit();
                }
            }
        }
    }

    // No se encontró con ninguna estrategia
    echo json_encode([
        'success' => false,
        'error' => 'Producto no encontrado',
        'sku_searched' => $sku,
        'strategies_tried' => [
            'direct_sku_match',
            'manual_filtering',
            'normalized_match',
            'variation_match'
        ],
        'suggestion' => 'Verificar manualmente en WooCommerce Admin que el SKU sea exactamente: ' . $sku
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
