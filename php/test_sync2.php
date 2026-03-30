<?php
// Test directo del sync

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';

// Simular llamada HTTP con la key correcta
$url = 'http://localhost/api/auto-sync.php?key=pccoreprueba-sync-2024';

// En su lugar, verificar manualmente qué productos trae de WooCommerce
require_once __DIR__ . '/config.php';

echo "=== TEST SYNC ===\n\n";

// 1. Obtener productos de WooCommerce
echo "1. Buscando productos en WooCommerce...\n";
$wcProducts = [];
$page = 1;

$result = wcRequest("/products?per_page=100&page=1&status=publish");
echo "   Productos encontrados: " . count($result) . "\n";

if (!empty($result)) {
    foreach ($result as $p) {
        if (!empty($p['sku'])) {
            $wcProducts[] = ['id' => $p['id'], 'sku' => $p['sku'], 'price' => $p['regular_price']];
        }
    }
}

echo "   Productos con SKU: " . count($wcProducts) . "\n\n";

echo "2. Primeros 10 productos:\n";
foreach (array_slice($wcProducts, 0, 10) as $p) {
    echo "   - " . $p['sku'] . " | Precio: $" . $p['price'] . "\n";
}
