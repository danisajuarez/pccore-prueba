<?php
/**
 * Debug: Verificar SKU específico
 */

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}

$sku = trim($_GET['sku'] ?? '00910');

try {
    $db = getDbConnection();

    $resultado = [];

    // 1. Verificar si existe en sige_art_articulo
    $stmt = $db->prepare("SELECT * FROM sige_art_articulo WHERE TRIM(ART_IDArticulo) = ? LIMIT 1");
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $res = $stmt->get_result();
    $resultado['articulo'] = $res->fetch_assoc();
    $stmt->close();

    // 2. Verificar precios en sige_prs_presho
    $stmt = $db->prepare("SELECT * FROM sige_prs_presho WHERE TRIM(art_idarticulo) = ? LIMIT 5");
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $res = $stmt->get_result();
    $precios = [];
    while ($row = $res->fetch_assoc()) {
        $precios[] = $row;
    }
    $resultado['precios'] = $precios;
    $stmt->close();

    // 3. Verificar config actual
    $resultado['config'] = [
        'lista_precio' => SIGE_LISTA_PRECIO,
        'deposito' => SIGE_DEPOSITO
    ];

    // 4. Buscar con la query exacta del product-search
    $sql = "SELECT
                a.ART_IDArticulo as sku,
                a.ART_DesArticulo as nombre,
                p.pal_precvtaart AS precio_final,
                p.ads_disponible AS stock
            FROM sige_art_articulo a
            INNER JOIN sige_prs_presho p ON a.ART_IDArticulo = p.art_idarticulo
            WHERE TRIM(a.ART_IDArticulo) = ?
            LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $res = $stmt->get_result();
    $resultado['query_completa'] = $res->fetch_assoc();
    $stmt->close();

    $db->close();

    echo json_encode([
        'success' => true,
        'sku_buscado' => $sku,
        'resultado' => $resultado
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
