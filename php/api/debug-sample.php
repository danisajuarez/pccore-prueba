<?php
require_once __DIR__ . '/../config.php';
checkAuth();

try {
    $db = getDbConnection();

    // Productos con precio/stock pero que NO están marcados en web todavía
    $sql = "SELECT
                a.ART_IDArticulo as sku,
                a.ART_DesArticulo as nombre,
                a.art_articuloweb as en_web,
                p.pal_precvtaart as precio,
                p.ads_disponible as stock
            FROM sige_art_articulo a
            INNER JOIN sige_prs_presho p ON a.ART_IDArticulo = p.art_idarticulo
            WHERE p.pal_precvtaart > 0
              AND p.ads_disponible > 0
              AND (a.art_articuloweb = 'N' OR a.art_articuloweb IS NULL)
            LIMIT 10";

    $result = $db->query($sql);

    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }

    $db->close();

    echo json_encode([
        'success' => true,
        'count' => count($productos),
        'productos' => $productos
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
