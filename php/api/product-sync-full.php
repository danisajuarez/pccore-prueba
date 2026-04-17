<?php
/**
 * API: Sincronización completa de producto
 *
 * Busca datos faltantes en ML y actualiza en WooCommerce
 */

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}
require_once __DIR__ . '/../config/mercadolibre.php';
checkAuth();

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$sku = trim($input['sku'] ?? '');
$wooId = $input['woo_id'] ?? null;

if (empty($sku) || empty($wooId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU y woo_id requeridos']);
    exit();
}

try {
    // 1. Buscar producto en SIGE
    $db = getDbConnection();

    // Usar sige_pal_preartlis para precios
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
            WHERE TRIM(a.ART_IDArticulo) = ?";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$producto) {
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado en SIGE']);
        exit();
    }

    // 2. Buscar datos faltantes en ML
    $datosML = null;
    $faltaDescripcion = empty($producto['descripcion_larga']);
    $faltaDimensiones = empty($producto['peso']) && empty($producto['alto']) && empty($producto['ancho']);

    if ($faltaDescripcion || $faltaDimensiones) {
        $datosML = buscarDatosProductoML(
            $producto['sku'],
            $producto['part_number'],
            $producto['nombre']
        );

        if (!empty($datosML['encontrado'])) {
            // Completar descripción
            if ($faltaDescripcion && !empty($datosML['descripcion'])) {
                $producto['descripcion_larga'] = $datosML['descripcion'];
            }

            // Completar dimensiones
            if (empty($producto['peso']) && !empty($datosML['peso'])) {
                $producto['peso'] = $datosML['peso'];
            }
            if (empty($producto['alto']) && !empty($datosML['alto'])) {
                $producto['alto'] = $datosML['alto'];
            }
            if (empty($producto['ancho']) && !empty($datosML['ancho'])) {
                $producto['ancho'] = $datosML['ancho'];
            }
            if (empty($producto['profundidad']) && !empty($datosML['profundidad'])) {
                $producto['profundidad'] = $datosML['profundidad'];
            }
        }
    }

    // 3. Preparar datos para WooCommerce
    $updateData = [
        'name' => $producto['nombre'],
        'regular_price' => number_format((float)($producto['precio_final'] ?? 0), 2, '.', ''),
        'stock_quantity' => (int)($producto['stock'] ?? 0),
        'manage_stock' => true,
        'short_description' => $producto['nombre'],
        'description' => !empty($producto['descripcion_larga']) ? $producto['descripcion_larga'] : $producto['nombre']
    ];

    // Peso y dimensiones
    if (!empty($producto['peso']) && $producto['peso'] > 0) {
        $updateData['weight'] = strval($producto['peso']);
    }

    $dimensions = [];
    if (!empty($producto['alto']) && $producto['alto'] > 0) {
        $dimensions['height'] = strval($producto['alto']);
    }
    if (!empty($producto['ancho']) && $producto['ancho'] > 0) {
        $dimensions['width'] = strval($producto['ancho']);
    }
    if (!empty($producto['profundidad']) && $producto['profundidad'] > 0) {
        $dimensions['length'] = strval($producto['profundidad']);
    }
    if (!empty($dimensions)) {
        $updateData['dimensions'] = $dimensions;
    }

    // Atributos de ML
    if (!empty($datosML['atributos'])) {
        $wcAttributes = [];
        foreach (array_slice($datosML['atributos'], 0, 10) as $attr) {
            $wcAttributes[] = [
                'name' => trim($attr['nombre']),
                'options' => [trim($attr['valor'])],
                'visible' => true,
                'variation' => false
            ];
        }
        if (!empty($wcAttributes)) {
            $updateData['attributes'] = $wcAttributes;
        }
    }

    // 4. Actualizar en WooCommerce
    $wcResult = wcRequest('/products/' . $wooId, 'PUT', $updateData);

    if (isset($wcResult['id'])) {
        $response = [
            'success' => true,
            'message' => 'Producto sincronizado correctamente',
            'ml_usado' => !empty($datosML['encontrado']),
            'ml_encontrado_por' => $datosML['encontrado_por'] ?? null,
            'datos_actualizados' => [
                'precio' => $updateData['regular_price'],
                'stock' => $updateData['stock_quantity'],
                'descripcion' => !empty($producto['descripcion_larga']) ? 'Sí' : 'No',
                'peso' => $updateData['weight'] ?? 'No',
                'dimensiones' => !empty($dimensions) ? 'Sí' : 'No',
                'atributos' => count($updateData['attributes'] ?? [])
            ]
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('Error al actualizar en WooCommerce: ' . json_encode($wcResult));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
