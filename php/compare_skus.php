<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$skus = ['ALT300108', 'DCPT430W'];
$db = getDbConnection();
$deposito = SIGE_DEPOSITO;
$lista = SIGE_LISTA_PRECIO;

foreach ($skus as $sku) {
    echo "========================================\n";
    echo "SKU: $sku\n";
    echo "========================================\n\n";

    // 1. Datos en sige_prs_presho
    echo "1. TABLA sige_prs_presho:\n";
    $r1 = $db->query("SELECT * FROM sige_prs_presho WHERE TRIM(art_idarticulo) = '$sku'");
    if ($r1 && $row = $r1->fetch_assoc()) {
        echo "   prs_idproducto (ID Woo): " . ($row['prs_idproducto'] ?: 'VACIO') . "\n";
        echo "   pal_precvtaart (precio SIGE): " . $row['pal_precvtaart'] . "\n";
        echo "   prs_precvtaart (precio sync): " . $row['prs_precvtaart'] . "\n";
        echo "   ads_disponible (stock SIGE): " . $row['ads_disponible'] . "\n";
        echo "   prs_disponible (stock sync): " . $row['prs_disponible'] . "\n";
    } else {
        echo "   NO EXISTE en sige_prs_presho\n";
    }

    // 2. Datos reales de SIGE (calculados)
    echo "\n2. DATOS REALES DE SIGE (calculados):\n";
    $sql = "SELECT
                (p.PAL_PrecVtaArt * m.MON_CotizMon * (1 + (a.ART_PorcIVARI / 100))) as precio_con_iva,
                (COALESCE(s.ADS_CanFisicoArt, 0) - COALESCE(s.ADS_CanReservArt, 0)) as stock
            FROM sige_art_articulo a
            INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
            INNER JOIN sige_mon_moneda m ON m.MON_IdMon = 2
            LEFT JOIN sige_ads_artdepsck s ON a.ART_IDArticulo = s.ART_IDArticulo AND s.DEP_IDDeposito = $deposito
            WHERE p.LIS_IDListaPrecio = $lista
              AND TRIM(a.ART_IDArticulo) = '$sku'";
    $r2 = $db->query($sql);
    if ($r2 && $row = $r2->fetch_assoc()) {
        echo "   Precio calculado: " . round($row['precio_con_iva']) . "\n";
        echo "   Stock calculado: " . $row['stock'] . "\n";
    } else {
        echo "   NO EXISTE en SIGE (o sin precio en lista $lista)\n";
    }

    // 3. Datos en WooCommerce
    echo "\n3. DATOS EN WOOCOMMERCE:\n";
    try {
        $wc = wcRequest('/products?sku=' . urlencode($sku));
        if (!empty($wc)) {
            foreach ($wc as $p) {
                if (strcasecmp(trim($p['sku']), $sku) === 0) {
                    echo "   ID: " . $p['id'] . "\n";
                    echo "   Precio: " . $p['regular_price'] . "\n";
                    echo "   Stock: " . $p['stock_quantity'] . "\n";
                    break;
                }
            }
        } else {
            echo "   NO EXISTE en WooCommerce\n";
        }
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

$db->close();
