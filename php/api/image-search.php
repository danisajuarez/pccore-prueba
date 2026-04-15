<?php
/**
 * API para buscar imágenes de productos (Multi-tenant)
 *
 * GET /api/image-search.php?sku=XXX
 *
 * Busca imágenes en:
 * 1. Base de datos SIGE (adv_pathimagen)
 * 2. Mercado Libre (por SKU, Part Number o nombre)
 */

// Capturar cualquier output/warning para evitar contaminar JSON
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/mercadolibre.php';

/**
 * Sanitizar datos recursivamente para json_encode
 * Asegura que todos los strings estén en UTF-8 válido
 */
function sanitizeForJson($data) {
    if (is_array($data)) {
        return array_map('sanitizeForJson', $data);
    } elseif (is_string($data)) {
        // Asegurar UTF-8 válido
        if (!mb_check_encoding($data, 'UTF-8')) {
            $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }
        // Limpiar caracteres de control problemáticos
        $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
        return $data;
    }
    return $data;
}

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Requiere autenticación por sesión
if (!isAuthenticated()) {
    http_response_code(401);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

// Validar API Key
$apiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getClienteId() . '-sync-2024';
if ($apiKey !== $expectedKey) {
    http_response_code(401);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'API Key inválida']);
    exit();
}

// Obtener parámetros
$sku = trim($_GET['sku'] ?? '');

if (empty($sku)) {
    http_response_code(400);
    ob_end_clean();
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
            TRIM(a.ART_CodBarraArt) as codigo_barras,
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
        ob_end_clean();
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
            'codigo_barras' => $articulo['codigo_barras'],
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
            $articulo['nombre'],
            $articulo['codigo_barras']
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

        // Validar que wcRequest devolvió un array válido
        if (is_array($wooProducts) && !empty($wooProducts)) {
            foreach ($wooProducts as $p) {
                if (is_array($p) && strcasecmp(trim($p['sku'] ?? ''), trim($sku)) === 0) {
                    $wooProduct = $p;
                    break;
                }
            }
        }

        if ($wooProduct !== null && is_array($wooProduct)) {
            $response['woocommerce'] = [
                'id' => $wooProduct['id'],
                'tiene_imagenes' => !empty($wooProduct['images']),
                'cantidad_imagenes' => count($wooProduct['images'] ?? []),
                'imagenes' => array_map(function($img) {
                    return [
                        'id' => $img['id'] ?? null,
                        'src' => $img['src'] ?? '',
                        'name' => $img['name'] ?? ''
                    ];
                }, $wooProduct['images'] ?? [])
            ];
        }
    } catch (Exception $e) {
        $response['woocommerce'] = null;
    }

    $conn->close();
    
    // Asegurar que todos los datos están en UTF-8 válido antes de json_encode
    $response = sanitizeForJson($response);
    
    ob_end_clean();
    
    // Intentar json_encode con validación
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error al serializar datos: ' . json_last_error_msg()
        ]);
    } else {
        echo $json;
    }

} catch (Exception $e) {
    http_response_code(500);
    ob_end_clean();
    $errorMsg = $e->getMessage();
    // Sanitizar mensaje de error
    if (!mb_check_encoding($errorMsg, 'UTF-8')) {
        $errorMsg = mb_convert_encoding($errorMsg, 'UTF-8', 'UTF-8');
    }
    echo json_encode([
        'success' => false,
        'error' => $errorMsg
    ], JSON_UNESCAPED_UNICODE);
}
