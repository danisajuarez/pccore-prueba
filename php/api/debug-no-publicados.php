<?php
/**
 * Debug: Buscar productos de SIGE que NO están en WooCommerce
 */
require_once __DIR__ . '/../config.php';
checkAuth();

try {
    $db = getDbConnection();

    // Obtener 20 SKUs de SIGE con precio
    $listaPrecio = SIGE_LISTA_PRECIO;
    $sql = "SELECT
                TRIM(a.ART_IDArticulo) as sku,
                a.ART_DesArticulo as nombre,
                p.PAL_PrecVtaArt as precio,
                a.ART_StockArt as stock
            FROM sige_art_articulo a
            INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
                AND p.LIS_IDListaPrecio = $listaPrecio
            WHERE p.PAL_PrecVtaArt > 0
            ORDER BY RAND()
            LIMIT 30";

    $result = $db->query($sql);
    $sigeProducts = [];
    while ($row = $result->fetch_assoc()) {
        $sigeProducts[$row['sku']] = $row;
    }
    $db->close();

    // Verificar cuáles NO están en WooCommerce
    $noPublicados = [];

    foreach ($sigeProducts as $sku => $prod) {
        $wcProducts = wcRequest('/products?sku=' . urlencode($sku) . '&status=any');

        $existe = false;
        if (!empty($wcProducts)) {
            foreach ($wcProducts as $p) {
                if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                    $existe = true;
                    break;
                }
            }
        }

        if (!$existe) {
            $noPublicados[] = $prod;
            if (count($noPublicados) >= 5) break; // Solo necesitamos 5
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Productos en SIGE que NO están en WooCommerce',
        'productos' => $noPublicados
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
