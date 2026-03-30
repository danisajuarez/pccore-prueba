<?php
/**
 * REPARAR TABLA sige_prs_presho
 * Recupera los valores correctos desde las tablas originales de SIGE
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$db = getDbConnection();
$deposito = SIGE_DEPOSITO;
$lista = SIGE_LISTA_PRECIO;

echo "=== REPARANDO TABLA sige_prs_presho ===\n\n";

// 1. Actualizar pal_precvtaart (precio BASE sin cotización) y ads_disponible desde SIGE
echo "PASO 1: Recuperando precios BASE y stock desde SIGE...\n";
echo "   pal_precvtaart = precio SIN cotización\n";
echo "   prs_precvtaart = precio CON cotización (para WooCommerce)\n\n";

$sql = "UPDATE sige_prs_presho w
        INNER JOIN sige_art_articulo a ON w.art_idarticulo = a.ART_IDArticulo
        INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo AND p.LIS_IDListaPrecio = $lista
        LEFT JOIN sige_ads_artdepsck s ON a.ART_IDArticulo = s.ART_IDArticulo AND s.DEP_IDDeposito = $deposito
        SET
            w.pal_precvtaart = ROUND(p.PAL_PrecVtaArt),
            w.ads_disponible = COALESCE(s.ADS_CanFisicoArt, 0) - COALESCE(s.ADS_CanReservArt, 0)";

$result = $db->query($sql);
if ($result) {
    echo "   Filas actualizadas: " . $db->affected_rows . "\n";
} else {
    echo "   ERROR: " . $db->error . "\n";
}

// 2. Buscar IDs de WooCommerce para productos que no lo tienen
echo "\nPASO 2: Buscando IDs de WooCommerce para productos sin ID...\n";

$sql2 = "SELECT art_idarticulo as sku FROM sige_prs_presho
         WHERE prs_idproducto IS NULL OR prs_idproducto = '' OR prs_idproducto = '0'";
$result2 = $db->query($sql2);

$sinId = [];
while ($row = $result2->fetch_assoc()) {
    $sinId[] = trim($row['sku']);
}

echo "   Productos sin ID de Woo: " . count($sinId) . "\n";

$idsEncontrados = 0;
foreach ($sinId as $sku) {
    try {
        $wc = wcRequest('/products?sku=' . urlencode($sku));
        if (!empty($wc)) {
            foreach ($wc as $p) {
                if (strcasecmp(trim($p['sku']), $sku) === 0) {
                    $wooId = $p['id'];
                    $skuEsc = $db->real_escape_string($sku);
                    $db->query("UPDATE sige_prs_presho SET prs_idproducto = '$wooId' WHERE art_idarticulo = '$skuEsc'");
                    $idsEncontrados++;
                    echo "   ✓ $sku -> ID $wooId\n";
                    break;
                }
            }
        }
    } catch (Exception $e) {
        // Ignorar errores
    }
}

echo "   IDs encontrados y actualizados: $idsEncontrados\n";

// 3. Calcular prs_precvtaart CON cotización (precio para WooCommerce)
echo "\nPASO 3: Calculando precios CON cotización para WooCommerce...\n";

$sql3 = "UPDATE sige_prs_presho w
         INNER JOIN sige_art_articulo a ON w.art_idarticulo = a.ART_IDArticulo
         INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo AND p.LIS_IDListaPrecio = $lista
         INNER JOIN sige_mon_moneda m ON m.MON_IdMon = 2
         SET
             w.prs_precvtaart = ROUND(p.PAL_PrecVtaArt * m.MON_CotizMon * (1 + (a.ART_PorcIVARI / 100))),
             w.prs_disponible = w.ads_disponible,
             w.prs_fecultactweb = NOW()
         WHERE p.PAL_PrecVtaArt > 0";

$result3 = $db->query($sql3);
if ($result3) {
    echo "   Filas sincronizadas: " . $db->affected_rows . "\n";
} else {
    echo "   ERROR: " . $db->error . "\n";
}

echo "\n=== REPARACION COMPLETADA ===\n";

// Verificar algunos productos
echo "\n=== VERIFICACION ===\n";
$verificar = ['ALT300108', 'DCPT430W', 'DCPT530DW'];
foreach ($verificar as $sku) {
    $r = $db->query("SELECT art_idarticulo, prs_idproducto, pal_precvtaart, prs_precvtaart, ads_disponible, prs_disponible
                     FROM sige_prs_presho WHERE TRIM(art_idarticulo) = '$sku'");
    if ($row = $r->fetch_assoc()) {
        echo "\n$sku:\n";
        echo "  ID Woo: " . ($row['prs_idproducto'] ?: 'VACIO') . "\n";
        echo "  Precio BASE (sin cotiz): " . $row['pal_precvtaart'] . "\n";
        echo "  Precio WOO (con cotiz):  " . $row['prs_precvtaart'] . "\n";
        echo "  Stock SIGE: " . $row['ads_disponible'] . " | Sync: " . $row['prs_disponible'] . "\n";
    }
}

$db->close();
