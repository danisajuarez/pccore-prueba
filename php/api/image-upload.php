<?php
/**
 * API para subir imágenes a productos de WooCommerce (Multi-tenant)
 *
 * POST /api/image-upload.php
 * Body: {
 *   "sku": "XXX",
 *   "imagenes": ["url1", "url2", ...]  // URLs de las imágenes a subir
 *   "reemplazar": false  // true para reemplazar imágenes existentes, false para agregar
 * }
 */

// Capturar cualquier output/warning para evitar contaminar JSON
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Función wcRequest
function wcRequest($endpoint, $method = 'GET', $data = null) {
    $url = WC_BASE_URL . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . WC_CONSUMER_KEY . '&consumer_secret=' . WC_CONSUMER_SECRET;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, $method === 'GET' ? 30 : 120);

    if ($method === 'PUT' || $method === 'POST') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("WooCommerce API error: $httpCode - $response");
    }

    return json_decode($response, true);
}

// Obtener body
$input = json_decode(file_get_contents('php://input'), true);

$sku = trim($input['sku'] ?? '');
$imagenes = $input['imagenes'] ?? [];
$reemplazar = $input['reemplazar'] ?? false;

if (empty($sku)) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'SKU es requerido']);
    exit();
}

if (empty($imagenes) || !is_array($imagenes)) {
    http_response_code(400);
    ob_end_clean();
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
        ob_end_clean();
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
        ob_end_clean();
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
        ob_end_clean();
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

        ob_end_clean();
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
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
