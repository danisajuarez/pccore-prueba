<?php
/**
 * REPARAR LOS ULTIMOS 68 PRODUCTOS
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$db = getDbConnection();
$deposito = SIGE_DEPOSITO;
$lista = SIGE_LISTA_PRECIO;

echo "=== REPARANDO ULTIMOS 68 PRODUCTOS ===\n\n";

// Buscar los últimos 68 actualizados
$sql = "SELECT w.art_idarticulo as sku
        FROM sige_prs_presho w
        ORDER BY w.prs_fecultactweb DESC
        LIMIT 68";

$result = $db->query($sql);
$count = 0;

while ($row = $result->fetch_assoc()) {
    $sku = trim($row['sku']);
    $skuEsc = $db->real_escape_string($sku);

    // Obtener valores correctos de SIGE
    $sqlSige = "SELECT
                    ROUND(p.PAL_PrecVtaArt * m.MON_CotizMon * (1 + (a.ART_PorcIVARI / 100))) as precio,
                    COALESCE(s.ADS_CanFisicoArt, 0) - COALESCE(s.ADS_CanReservArt, 0) as stock
                FROM sige_art_articulo a
                INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo AND p.LIS_IDListaPrecio = $lista
                INNER JOIN sige_mon_moneda m ON m.MON_IdMon = 2
                LEFT JOIN sige_ads_artdepsck s ON a.ART_IDArticulo = s.ART_IDArticulo AND s.DEP_IDDeposito = $deposito
                WHERE TRIM(a.ART_IDArticulo) = '$skuEsc'";

    $rSige = $db->query($sqlSige);
    if ($rSige && $rowSige = $rSige->fetch_assoc()) {
        $precio = $rowSige['precio'];
        $stock = $rowSige['stock'];

        // Actualizar
        $update = "UPDATE sige_prs_presho SET
                        pal_precvtaart = $precio,
                        ads_disponible = $stock,
                        prs_precvtaart = $precio,
                        prs_disponible = $stock
                   WHERE art_idarticulo = '$skuEsc'";
        $db->query($update);
        $count++;
        echo "✓ $sku: Precio=$precio, Stock=$stock\n";
    } else {
        echo "✗ $sku: No encontrado en SIGE\n";
    }
}

echo "\n=== REPARADOS: $count productos ===\n";

$db->close();
