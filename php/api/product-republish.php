<?php
/**
 * API: Republicar producto en WooCommerce
 *
 * Borra el producto existente y lo vuelve a crear desde cero.
 * Útil para arreglar productos con datos corruptos en las tablas internas de WooCommerce.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Validar API Key
$headers = getallheaders();
$apiKey = $headers['X-Api-Key'] ?? $headers['x-api-key'] ?? $_GET['api_key'] ?? '';
$expectedKey = getClienteId() . '-sync-2024';

if ($apiKey !== $expectedKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'API Key inválida']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$sku = trim($input['sku'] ?? '');

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU requerido']);
    exit;
}

// Función wcRequest
function wcRequest($endpoint, $method = 'GET', $data = null) {
    if (!isset($_SESSION['cliente_config'])) {
        throw new Exception("No hay sesión de cliente activa");
    }

    $config = $_SESSION['cliente_config'];

    if (empty($config['wc_url']) || empty($config['wc_key']) || empty($config['wc_secret'])) {
        throw new Exception("Credenciales de WooCommerce incompletas");
    }

    $url = $config['wc_url'] . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . urlencode($config['wc_key']) . '&consumer_secret=' . urlencode($config['wc_secret']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    if ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    } elseif ($method === 'PUT' || $method === 'POST') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("CURL Error: $error");
    }

    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

try {
    $resultado = [
        'sku' => $sku,
        'paso1_borrar' => null,
        'paso2_publicar' => null
    ];

    // PASO 1: Buscar y borrar producto existente
    $busqueda = wcRequest('/products?sku=' . urlencode($sku) . '&status=any');

    if (!empty($busqueda['data'])) {
        foreach ($busqueda['data'] as $p) {
            if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                // Borrar con force=true para eliminar permanentemente
                $borrado = wcRequest('/products/' . $p['id'] . '?force=true', 'DELETE');
                $resultado['paso1_borrar'] = [
                    'producto_id' => $p['id'],
                    'status' => $borrado['code'] === 200 ? 'eliminado' : 'error',
                    'http_code' => $borrado['code']
                ];
                break;
            }
        }
    } else {
        $resultado['paso1_borrar'] = 'No existía en WooCommerce';
    }

    // PASO 2: Publicar de nuevo usando product-publish.php
    // Simulamos la llamada internamente
    $_POST_backup = $_POST;
    $_SERVER_backup = $_SERVER['REQUEST_METHOD'];

    // Preparar el input para product-publish
    $publishInput = json_encode(['sku' => $sku]);

    // Hacer request interno al endpoint de publicación
    $ch = curl_init();
    $publishUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                  . "://" . $_SERVER['HTTP_HOST']
                  . dirname($_SERVER['REQUEST_URI']) . '/product-publish.php?api_key=' . $apiKey;

    curl_setopt($ch, CURLOPT_URL, $publishUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $publishInput);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cookie: ' . ($_SERVER['HTTP_COOKIE'] ?? '')
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $publishResponse = curl_exec($ch);
    $publishCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $publishData = json_decode($publishResponse, true);

    if ($publishData && isset($publishData['success']) && $publishData['success']) {
        $resultado['paso2_publicar'] = [
            'status' => 'publicado',
            'producto_id' => $publishData['product']['id'] ?? null,
            'permalink' => $publishData['product']['permalink'] ?? null
        ];
        $resultado['success'] = true;
        $resultado['message'] = 'Producto republicado correctamente';
    } else {
        $resultado['paso2_publicar'] = [
            'status' => 'error',
            'error' => $publishData['error'] ?? 'Error desconocido',
            'http_code' => $publishCode
        ];
        $resultado['success'] = false;
        $resultado['error'] = 'Error al republicar: ' . ($publishData['error'] ?? 'Error desconocido');
    }

    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'sku' => $sku
    ]);
}
