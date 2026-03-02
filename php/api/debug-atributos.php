<?php
require_once __DIR__ . '/../config.php';
checkAuth();

try {
    $db = getDbConnection();

    // Buscar productos que tengan atributos
    $sql = "SELECT DISTINCT
                a.ART_IDArticulo as sku,
                a.ART_DesArticulo as nombre,
                COUNT(t.aat_descripcion) as cant_atributos
            FROM sige_art_articulo a
            INNER JOIN sige_aat_artatrib t ON a.ART_IDArticulo = t.art_idarticulo
            GROUP BY a.ART_IDArticulo
            HAVING cant_atributos > 0
            LIMIT 30";

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
