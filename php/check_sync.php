<?php
/**
 * Script para diagnosticar el sync
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$db = getDbConnection();

// 1. Contar productos con art_articuloweb = 'S'
echo "=== PRODUCTOS CON art_articuloweb = 'S' EN SIGE ===\n";
$sql1 = "SELECT COUNT(*) as total FROM sige_art_articulo WHERE UPPER(TRIM(COALESCE(art_articuloweb, ''))) = 'S'";
$r1 = $db->query($sql1);
$row1 = $r1->fetch_assoc();
echo "Total con 'S': " . $row1['total'] . "\n\n";

// 2. Listar los que tienen S
echo "Listado de productos con 'S':\n";
$sql2 = "SELECT TRIM(ART_IDArticulo) as sku, art_articuloweb
         FROM sige_art_articulo
         WHERE UPPER(TRIM(COALESCE(art_articuloweb, ''))) = 'S'
         LIMIT 30";
$r2 = $db->query($sql2);
while ($row = $r2->fetch_assoc()) {
    echo "- " . $row['sku'] . " [" . $row['art_articuloweb'] . "]\n";
}

// 3. Verificar DCPT530DW específicamente
echo "\n=== VERIFICACION DCPT530DW ===\n";
$sql3 = "SELECT TRIM(ART_IDArticulo) as sku, art_articuloweb FROM sige_art_articulo WHERE TRIM(ART_IDArticulo) = 'DCPT530DW'";
$r3 = $db->query($sql3);
if ($row = $r3->fetch_assoc()) {
    echo "SKU: " . $row['sku'] . "\n";
    echo "art_articuloweb: [" . $row['art_articuloweb'] . "]\n";
    $isS = (strtoupper(trim($row['art_articuloweb'] ?? '')) === 'S');
    echo "¿Es 'S'?: " . ($isS ? "SI" : "NO") . "\n";
} else {
    echo "No encontrado\n";
}

// 4. Contar productos en WooCommerce
echo "\n=== PRODUCTOS EN WOOCOMMERCE ===\n";
try {
    $page = 1;
    $total = 0;
    $wcSkus = [];

    while (true) {
        $products = wcRequest('/products?per_page=100&page=' . $page);
        if (empty($products)) break;

        foreach ($products as $p) {
            if (!empty($p['sku'])) {
                $wcSkus[] = trim($p['sku']);
                $total++;
            }
        }

        if (count($products) < 100) break;
        $page++;
        if ($page > 10) break; // Límite de seguridad
    }

    echo "Total productos en WooCommerce: $total\n\n";
    echo "SKUs en WooCommerce:\n";
    foreach ($wcSkus as $sku) {
        echo "- $sku\n";
    }

    // Comparar
    echo "\n=== COMPARACION ===\n";
    $sql4 = "SELECT TRIM(ART_IDArticulo) as sku FROM sige_art_articulo WHERE UPPER(TRIM(COALESCE(art_articuloweb, ''))) = 'S'";
    $r4 = $db->query($sql4);
    $sigeSkus = [];
    while ($row = $r4->fetch_assoc()) {
        $sigeSkus[] = $row['sku'];
    }

    $enSigeNoEnWoo = array_diff($sigeSkus, $wcSkus);
    $enWooNoEnSige = array_diff($wcSkus, $sigeSkus);

    echo "En SIGE con 'S' pero NO en WooCommerce: " . count($enSigeNoEnWoo) . "\n";
    foreach ($enSigeNoEnWoo as $sku) {
        echo "  - $sku\n";
    }

    echo "\nEn WooCommerce pero SIN 'S' en SIGE: " . count($enWooNoEnSige) . "\n";
    foreach ($enWooNoEnSige as $sku) {
        echo "  - $sku\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$db->close();
