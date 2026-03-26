<?php
/**
 * API para buscar imágenes de productos
 *
 * GET /api/image-search.php?sku=XXX
 *
 * Busca imágenes en:
 * 1. Base de datos SIGE (adv_pathimagen)
 * 2. Mercado Libre (por SKU, Part Number o nombre)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/mercadolibre.php';

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Verificar API key o sesión
$apiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== API_KEY) {
    // Verificar sesión si no hay API key
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit();
    }
}

// Obtener parámetros
$sku = trim($_GET['sku'] ?? '');

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU es requerido']);
    exit();
}

try {
    $conn = getDbConnection();

    // 1. Buscar datos del artículo en SIGE
    $stmt = $conn->prepare("
        SELECT
            TRIM(a.ART_IDArticulo) as sku,
            a.ART_DesArticulo as nombre,
            TRIM(a.ART_PartNumber) as part_number,
            a.ART_IdML as id_ml,
            d.adv_pathimagen as imagen_sige
        FROM sige_art_articulo a
        LEFT JOIN sige_adv_artdatvar d ON a.ART_IDArticulo = d.art_idarticulo
        WHERE TRIM(a.ART_IDArticulo) = ?
    ");
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $result = $stmt->get_result();
    $articulo = $result->fetch_assoc();
    $stmt->close();

    if (!$articulo) {
        echo json_encode([
            'success' => false,
            'error' => 'Artículo no encontrado en SIGE'
        ]);
        exit();
    }

    $response = [
        'success' => true,
        'articulo' => [
            'sku' => $articulo['sku'],
            'nombre' => $articulo['nombre'],
            'part_number' => $articulo['part_number'],
            'id_ml' => $articulo['id_ml']
        ],
        'imagenes' => [
            'sige' => null,
            'mercadolibre' => null
        ]
    ];

    // 2. Verificar si tiene imagen en SIGE
    if (!empty($articulo['imagen_sige']) && $articulo['imagen_sige'] !== '0') {
        $response['imagenes']['sige'] = [
            'path' => $articulo['imagen_sige'],
            'fuente' => 'SIGE'
        ];
    }

    // 3. Buscar en Mercado Libre si no tiene imagen en SIGE
    if (empty($response['imagenes']['sige'])) {
        $resultadoML = buscarImagenesConFallback(
            $articulo['sku'],
            $articulo['part_number'],
            $articulo['nombre']
        );

        if (!empty($resultadoML['imagenes'])) {
            $response['imagenes']['mercadolibre'] = [
                'producto' => $resultadoML['producto_ml'] ?? null,
                'encontrado_por' => $resultadoML['encontrado_por'],
                'imagenes' => $resultadoML['imagenes'],
                'fuente' => 'Mercado Libre'
            ];
        }
    }

    // 4. Verificar imágenes actuales en WooCommerce
    try {
        $wooProducts = wcRequest('/products?sku=' . urlencode($sku));
        $wooProduct = null;

        // Buscar el producto con SKU exacto
        if (!empty($wooProducts)) {
            foreach ($wooProducts as $p) {
                if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                    $wooProduct = $p;
                    break;
                }
            }
        }

        if ($wooProduct !== null) {
            $response['woocommerce'] = [
                'id' => $wooProduct['id'],
                'tiene_imagenes' => !empty($wooProduct['images']),
                'cantidad_imagenes' => count($wooProduct['images'] ?? []),
                'imagenes' => array_map(function($img) {
                    return [
                        'id' => $img['id'],
                        'src' => $img['src'],
                        'name' => $img['name'] ?? ''
                    ];
                }, $wooProduct['images'] ?? [])
            ];
        }
    } catch (Exception $e) {
        $response['woocommerce'] = null;
    }

    $conn->close();
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
