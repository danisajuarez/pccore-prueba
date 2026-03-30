<?php
/**
 * Test: verificar por qué DCPT530DW no se sincroniza
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$sku = $argv[1] ?? 'DCPT530DW';

echo "=== TEST DE SINCRONIZACION PARA: $sku ===\n\n";

$db = getDbConnection();

// 1. Verificar si está en el query del auto-sync
echo "1. ¿Está en el query del auto-sync?\n";
$sql = "SELECT
            TRIM(a.ART_IDArticulo) as sku,
            a.ART_DesArticulo as nombre,
            a.art_articuloweb,
            (p.PAL_PrecVtaArt * m.MON_CotizMon * (1 + (a.ART_PorcIVARI / 100))) as precio_con_iva
        FROM sige_art_articulo a
        INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
        INNER JOIN sige_mon_moneda m ON m.MON_IdMon = 2
        WHERE p.LIS_IDListaPrecio = " . SIGE_LISTA_PRECIO . "
          AND p.PAL_PrecVtaArt > 0
          AND UPPER(TRIM(COALESCE(a.art_articuloweb, ''))) = 'S'
          AND TRIM(a.ART_IDArticulo) = '$sku'";

$result = $db->query($sql);
if ($row = $result->fetch_assoc()) {
    echo "   SI - Encontrado en SIGE\n";
    echo "   SKU: " . $row['sku'] . "\n";
    echo "   art_articuloweb: [" . $row['art_articuloweb'] . "]\n";
    echo "   Precio: $" . number_format($row['precio_con_iva'], 2) . "\n";
} else {
    echo "   NO - No está en el query del auto-sync\n";
    echo "   Verificando por qué...\n";

    // Verificar cada condición
    $sql2 = "SELECT
                TRIM(a.ART_IDArticulo) as sku,
                a.art_articuloweb,
                p.PAL_PrecVtaArt as precio,
                p.LIS_IDListaPrecio as lista
             FROM sige_art_articulo a
             LEFT JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
             WHERE TRIM(a.ART_IDArticulo) = '$sku'";
    $r2 = $db->query($sql2);
    while ($row2 = $r2->fetch_assoc()) {
        echo "   - art_articuloweb: [" . ($row2['art_articuloweb'] ?? 'NULL') . "]\n";
        echo "   - Lista precio: " . ($row2['lista'] ?? 'NULL') . " (esperada: " . SIGE_LISTA_PRECIO . ")\n";
        echo "   - Precio: " . ($row2['precio'] ?? 'NULL') . "\n";
    }
}

// 2. Buscar en WooCommerce
echo "\n2. Búsqueda en WooCommerce:\n";
try {
    $wcProducts = wcRequest('/products?sku=' . urlencode($sku));
    echo "   Respuesta de WooCommerce: " . count($wcProducts) . " productos\n";

    if (!empty($wcProducts)) {
        foreach ($wcProducts as $p) {
            echo "   - ID: " . $p['id'] . ", SKU: [" . $p['sku'] . "]\n";
            $match = (strcasecmp(trim($p['sku']), $sku) === 0);
            echo "   - ¿Coincide exactamente? " . ($match ? "SI" : "NO") . "\n";
        }
    } else {
        echo "   No se encontró en WooCommerce\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 3. Ver en qué posición está en el listado general
echo "\n3. Posición en el listado (LIMIT 50):\n";
$sql3 = "SELECT TRIM(a.ART_IDArticulo) as sku
         FROM sige_art_articulo a
         INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
         INNER JOIN sige_mon_moneda m ON m.MON_IdMon = 2
         WHERE p.LIS_IDListaPrecio = " . SIGE_LISTA_PRECIO . "
           AND p.PAL_PrecVtaArt > 0
           AND UPPER(TRIM(COALESCE(a.art_articuloweb, ''))) = 'S'
         LIMIT 50";
$r3 = $db->query($sql3);
$pos = 0;
$found = false;
$skus = [];
while ($row3 = $r3->fetch_assoc()) {
    $pos++;
    $skus[] = $row3['sku'];
    if (trim($row3['sku']) === $sku) {
        echo "   Encontrado en posición: $pos\n";
        $found = true;
    }
}
if (!$found) {
    echo "   NO está en los primeros 50 productos\n";
    echo "   Primeros 10 SKUs: " . implode(", ", array_slice($skus, 0, 10)) . "\n";
}

$db->close();
