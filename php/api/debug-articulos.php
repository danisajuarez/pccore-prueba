<?php
/**
 * Debug: Listar artículos de ejemplo
 */

require_once __DIR__ . '/../config.php';
checkAuth();

try {
    $db = getDbConnection();

    // Contar total de artículos
    $countRes = $db->query("SELECT COUNT(*) as total FROM sige_art_articulo");
    $total = $countRes->fetch_assoc()['total'];

    // Obtener primeros 20 artículos
    $sql = "SELECT
                a.ART_IDArticulo as sku,
                a.ART_DesArticulo as nombre,
                a.ART_PartNumber as part_number
            FROM sige_art_articulo a
            ORDER BY a.ART_IDArticulo
            LIMIT 20";

    $result = $db->query($sql);
    $articulos = [];
    while ($row = $result->fetch_assoc()) {
        $articulos[] = $row;
    }

    $db->close();

    echo json_encode([
        'success' => true,
        'total_articulos' => (int)$total,
        'primeros_20' => $articulos
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
