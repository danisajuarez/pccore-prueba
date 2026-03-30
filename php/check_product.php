<?php
/**
 * Script temporal para verificar un producto
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$sku = $argv[1] ?? 'DCPT530DW';

try {
    $db = getDbConnection();
} catch (Exception $e) {
    echo "Error conectando a BD: " . $e->getMessage() . "\n";
    exit(1);
}

$sql = "SELECT
    TRIM(a.ART_IDArticulo) as sku,
    a.ART_DesArticulo as nombre,
    a.art_articuloweb,
    (p.PAL_PrecVtaArt * m.MON_CotizMon * (1 + (a.ART_PorcIVARI / 100))) as precio_con_iva
FROM sige_art_articulo a
LEFT JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo AND p.LIS_IDListaPrecio = " . SIGE_LISTA_PRECIO . "
LEFT JOIN sige_mon_moneda m ON m.MON_IdMon = 2
WHERE TRIM(a.ART_IDArticulo) = ?";

$stmt = $db->prepare($sql);
if (!$stmt) {
    echo "Error en prepare: " . $db->error . "\n";
    exit(1);
}
$stmt->bind_param("s", $sku);
$stmt->execute();
$result = $stmt->get_result();

echo "=== PRODUCTO EN SIGE ===\n";
if ($row = $result->fetch_assoc()) {
    echo "SKU: " . $row['sku'] . "\n";
    echo "Nombre: " . $row['nombre'] . "\n";
    echo "art_articuloweb: [" . ($row['art_articuloweb'] ?? 'NULL') . "]\n";
    echo "Precio con IVA: $" . number_format($row['precio_con_iva'] ?? 0, 2) . "\n";
} else {
    echo "Producto NO encontrado en SIGE\n";
}

// Verificar en WooCommerce
echo "\n=== PRODUCTO EN WOOCOMMERCE ===\n";
try {
    $wcProducts = wcRequest('/products?sku=' . urlencode($sku));
    if (!empty($wcProducts)) {
        foreach ($wcProducts as $p) {
            if (strcasecmp(trim($p['sku']), $sku) === 0) {
                echo "ID: " . $p['id'] . "\n";
                echo "SKU: " . $p['sku'] . "\n";
                echo "Nombre: " . $p['name'] . "\n";
                echo "Precio: $" . $p['regular_price'] . "\n";
                echo "Stock: " . $p['stock_quantity'] . "\n";
                echo "Estado: " . $p['status'] . "\n";
                break;
            }
        }
    } else {
        echo "Producto NO encontrado en WooCommerce\n";
    }
} catch (Exception $e) {
    echo "Error consultando WooCommerce: " . $e->getMessage() . "\n";
}

$db->close();
