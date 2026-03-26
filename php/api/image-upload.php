<?php
/**
 * API para subir imágenes a productos de WooCommerce
 *
 * POST /api/image-upload.php
 * Body: {
 *   "sku": "XXX",
 *   "imagenes": ["url1", "url2", ...]  // URLs de las imágenes a subir
 *   "reemplazar": false  // true para reemplazar imágenes existentes, false para agregar
 * }
 */

require_once __DIR__ . '/../config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Verificar API key o sesión
$apiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== API_KEY) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit();
    }
}

// Obtener body
$input = json_decode(file_get_contents('php://input'), true);

$sku = trim($input['sku'] ?? '');
$imagenes = $input['imagenes'] ?? [];
$reemplazar = $input['reemplazar'] ?? false;

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU es requerido']);
    exit();
}

if (empty($imagenes) || !is_array($imagenes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Se requiere al menos una URL de imagen']);
    exit();
}

try {
    // 1. Buscar producto en WooCommerce (SKU exacto)
    $wooProducts = wcRequest('/products?sku=' . urlencode($sku));
    $wooProduct = null;

    if (!empty($wooProducts)) {
        foreach ($wooProducts as $p) {
            if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                $wooProduct = $p;
                break;
            }
        }
    }

    if ($wooProduct === null) {
        echo json_encode([
            'success' => false,
            'error' => 'Producto no encontrado en WooCommerce. Debe publicarlo primero.'
        ]);
        exit();
    }

    $productId = $wooProduct['id'];
    $imagenesActuales = $wooProduct['images'] ?? [];

    // 2. Preparar array de imágenes
    $nuevasImagenes = [];

    // Si no reemplazamos, mantener las existentes
    if (!$reemplazar && !empty($imagenesActuales)) {
        foreach ($imagenesActuales as $img) {
            $nuevasImagenes[] = ['id' => $img['id']];
        }
    }

    // Agregar nuevas imágenes por URL
    // Usamos URLs de alta calidad de ML (reemplazar -I por -O para original)
    foreach ($imagenes as $index => $url) {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            // Convertir a URL de máxima calidad de ML
            $urlHQ = preg_replace('/-[A-Z]\./', '-O.', $url);
            $nuevasImagenes[] = [
                'src' => $urlHQ,
                'name' => $sku . '_' . ($index + 1),
                'position' => $index
            ];
        }
    }

    if (empty($nuevasImagenes)) {
        echo json_encode([
            'success' => false,
            'error' => 'No hay imágenes válidas para subir'
        ]);
        exit();
    }

    // 3. Actualizar producto en WooCommerce
    $updateData = ['images' => $nuevasImagenes];

    // Debug mode
    if (isset($_GET['debug'])) {
        echo json_encode([
            'debug' => true,
            'product_id' => $productId,
            'update_data' => $updateData,
            'imagenes_a_subir' => $nuevasImagenes
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    $result = wcRequest('/products/' . $productId, 'PUT', $updateData);

    // Log para debug
    error_log('WooCommerce image upload response: ' . json_encode($result));

    // Verificar si WooCommerce devolvió un error
    if (isset($result['code']) && isset($result['message'])) {
        throw new Exception('WooCommerce error: ' . $result['message']);
    }

    // Verificar errores en las imágenes
    if (isset($result['images'])) {
        foreach ($result['images'] as $img) {
            if (isset($img['error'])) {
                error_log('Error en imagen: ' . json_encode($img));
            }
        }
    }

    if (isset($result['id'])) {
        // Verificar cuántas imágenes tiene ahora
        $imagenesResultado = $result['images'] ?? [];

        echo json_encode([
            'success' => true,
            'message' => 'Imágenes actualizadas correctamente',
            'producto' => [
                'id' => $result['id'],
                'sku' => $result['sku'],
                'nombre' => $result['name'],
                'imagenes' => array_map(function($img) {
                    return [
                        'id' => $img['id'],
                        'src' => $img['src'],
                        'name' => $img['name'] ?? ''
                    ];
                }, $imagenesResultado)
            ],
            'total_imagenes' => count($imagenesResultado),
            'urls_enviadas' => count($nuevasImagenes),
            'debug_urls' => array_map(function($img) { return $img['src'] ?? 'N/A'; }, array_slice($nuevasImagenes, 0, 2))
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        throw new Exception('Error al actualizar producto: ' . json_encode($result));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
