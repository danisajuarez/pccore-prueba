<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$skus = ['214763', '770017', '770018'];

$db = getDbConnection();

foreach ($skus as $sku) {
    echo "=== $sku ===\n";

    // En sige_prs_presho
    $r1 = $db->query("SELECT art_idarticulo, pal_precvtaart, prs_precvtaart, ads_disponible, prs_disponible
                      FROM sige_prs_presho WHERE TRIM(art_idarticulo) = '$sku'");
    if ($r1 && $row = $r1->fetch_assoc()) {
        echo "sige_prs_presho:\n";
        echo "  pal_precvtaart (SIGE): " . $row['pal_precvtaart'] . "\n";
        echo "  prs_precvtaart (sync): " . $row['prs_precvtaart'] . "\n";
        echo "  ads_disponible (SIGE): " . $row['ads_disponible'] . "\n";
        echo "  prs_disponible (sync): " . $row['prs_disponible'] . "\n";
    } else {
        echo "NO está en sige_prs_presho\n";
    }

    // En WooCommerce
    try {
        $wc = wcRequest('/products?sku=' . urlencode($sku));
        if (!empty($wc)) {
            foreach ($wc as $p) {
                if (strcasecmp(trim($p['sku']), $sku) === 0) {
                    echo "WooCommerce:\n";
                    echo "  ID: " . $p['id'] . "\n";
                    echo "  Precio: " . $p['regular_price'] . "\n";
                    echo "  Stock: " . $p['stock_quantity'] . "\n";
                    break;
                }
            }
        } else {
            echo "NO está en WooCommerce\n";
        }
    } catch (Exception $e) {
        echo "Error WooCommerce: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

$db->close();
