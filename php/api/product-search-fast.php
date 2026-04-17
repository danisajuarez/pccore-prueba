<?php
/**
 * API: Búsqueda RÁPIDA de productos (sin ML, solo BD)
 */

$startTime = microtime(true);

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}
checkAuth();

$sku = trim($_GET['sku'] ?? '');

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU requerido']);
    exit();
}

try {
    $producto = null;
    $wooProducto = null;

    // 1. Buscar en SIGE
    $db = getDbConnection();

    $listaPrecio = SIGE_LISTA_PRECIO;
    $sql = "SELECT
                a.ART_IDArticulo as sku,
                a.ART_DesArticulo as nombre,
                a.ART_PartNumber as part_number,
                a.art_artobs as descripcion_larga,
                (p.PAL_PrecVtaArt / (1 + (a.ART_PorcIVARI / 100))) AS precio_sin_iva,
                p.PAL_PrecVtaArt AS precio_final,
                a.ART_StockArt AS stock,
                d.ADV_Peso as peso,
                d.ADV_Alto as alto,
                d.ADV_Ancho as ancho,
                d.ADV_Profundidad as profundidad
            FROM sige_art_articulo a
            LEFT JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
                AND p.LIS_IDListaPrecio = $listaPrecio
            LEFT JOIN sige_adv_artdatvar d ON a.ART_IDArticulo = d.art_idarticulo
            WHERE TRIM(a.ART_IDArticulo) = ?
            LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $producto = [
            'sku' => $row['sku'],
            'nombre' => $row['nombre'],
            'part_number' => $row['part_number'],
            'descripcion_larga' => $row['descripcion_larga'],
            'precio_sin_iva' => $row['precio_sin_iva'],
            'precio' => $row['precio_final'],
            'stock' => $row['stock'],
            'peso' => $row['peso'],
            'alto' => $row['alto'],
            'ancho' => $row['ancho'],
            'profundidad' => $row['profundidad'],
            'atributos' => []
        ];
    }

    $stmt->close();
    $db->close();

    // 2. Buscar en WooCommerce
    $wcProducts = wcRequest('/products?sku=' . urlencode($sku));

    if (!empty($wcProducts)) {
        foreach ($wcProducts as $p) {
            if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                $wooProducto = [
                    'id' => $p['id'],
                    'status' => $p['status'],
                    'permalink' => $p['permalink'],
                    'regular_price' => $p['regular_price'],
                    'stock_quantity' => $p['stock_quantity'],
                    'description' => $p['description'] ?? null,
                    'weight' => $p['weight'] ?? null,
                    'dimensions' => $p['dimensions'] ?? null
                ];
                break;
            }
        }
    }

    // Si no se encontró, buscar en todos los estados
    if ($wooProducto === null) {
        $wcProductsAll = wcRequest('/products?sku=' . urlencode($sku) . '&status=any');

        if (!empty($wcProductsAll)) {
            foreach ($wcProductsAll as $p) {
                if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                    $wooProducto = [
                        'id' => $p['id'],
                        'status' => $p['status'],
                        'permalink' => $p['permalink'],
                        'regular_price' => $p['regular_price'],
                        'stock_quantity' => $p['stock_quantity'],
                        'description' => $p['description'] ?? null,
                        'weight' => $p['weight'] ?? null,
                        'dimensions' => $p['dimensions'] ?? null
                    ];
                    break;
                }
            }
        }
    }

    // Responder
    if ($producto === null && $wooProducto === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Producto '$sku' no encontrado"]);
        exit();
    }

    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2);

    echo json_encode([
        'success' => true,
        'producto' => $producto,
        'woo_producto' => $wooProducto,
        '_debug' => [
            'duration_ms' => $duration,
            'version' => 'fast'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
