<?php
// TEST: Probar sync de UN solo producto
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(60);
header('Content-Type: text/plain');

$key = $_GET['key'] ?? '';
if ($key !== 'pccore-sync-2024') {
    die("ERROR: API Key invalida");
}

require_once __DIR__ . '/../config.php';

echo "=== TEST SYNC UN PRODUCTO ===\n\n";

// Conectar BD
echo "1. Conectando BD...\n";
$db = getDbConnection();
echo "   OK\n\n";

// Traer UN producto
echo "2. Buscando 1 producto con diferencia...\n";
$sql = "SELECT s.art_idarticulo, s.pal_precvtaart, s.ads_disponible
        FROM sige_prs_presho s
        INNER JOIN sige_art_articulo a ON a.ART_IDArticulo = s.art_idarticulo
        WHERE s.pal_precvtaart <> s.prs_precvtaart
           OR s.prs_disponible <> s.ads_disponible
        LIMIT 1";

$result = $db->query($sql);
$prod = $result->fetch_assoc();

if (!$prod) {
    die("   No hay productos con diferencias\n");
}

$sku = $prod['art_idarticulo'];
$precio = $prod['pal_precvtaart'];
$stock = $prod['ads_disponible'];

echo "   SKU: $sku\n";
echo "   Precio: $precio\n";
echo "   Stock: $stock\n\n";

// Buscar en WooCommerce
echo "3. Buscando SKU $sku en WooCommerce...\n";
$startTime = microtime(true);

try {
    $wcProducts = wcRequest('/products?sku=' . urlencode($sku));
    $elapsed = round(microtime(true) - $startTime, 2);
    echo "   Tiempo: {$elapsed}s\n";

    if (empty($wcProducts)) {
        echo "   Resultado: NO EXISTE en WooCommerce\n";
    } else {
        $wcId = $wcProducts[0]['id'];
        $wcPrice = $wcProducts[0]['regular_price'] ?? 'N/A';
        echo "   Resultado: ENCONTRADO (ID: $wcId, Precio actual: $wcPrice)\n\n";

        // Actualizar
        echo "4. Actualizando en WooCommerce...\n";
        $startTime = microtime(true);

        $payload = [
            'regular_price' => number_format((float)$precio, 2, '.', ''),
            'manage_stock'  => true,
            'stock_quantity' => (int)$stock,
            'stock_status'  => ($stock > 0) ? 'instock' : 'outofstock'
        ];

        $updated = wcRequest('/products/' . $wcId, 'PUT', $payload);
        $elapsed = round(microtime(true) - $startTime, 2);
        echo "   Tiempo: {$elapsed}s\n";
        echo "   Nuevo precio en Woo: " . ($updated['regular_price'] ?? 'N/A') . "\n";
        echo "   OK!\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

$db->close();
echo "\n=== FIN TEST ===\n";
