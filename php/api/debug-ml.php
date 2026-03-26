<?php
/**
 * Debug: Probar conexión a Mercado Libre
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/mercadolibre.php';
checkAuth();

$resultado = [];

// 1. Verificar token existente
$resultado['token_file_exists'] = file_exists(ML_TOKEN_FILE);
if (file_exists(ML_TOKEN_FILE)) {
    $tokenData = json_decode(file_get_contents(ML_TOKEN_FILE), true);
    $resultado['token_expires_at'] = date('Y-m-d H:i:s', $tokenData['expires_at'] ?? 0);
    $resultado['token_valid'] = time() < ($tokenData['expires_at'] ?? 0);
}

// 2. Intentar obtener token
try {
    $start = microtime(true);
    $token = getMLAccessToken();
    $resultado['token_time'] = round((microtime(true) - $start) * 1000) . 'ms';
    $resultado['token_obtained'] = !empty($token);
    $resultado['token_preview'] = substr($token, 0, 20) . '...';
} catch (Exception $e) {
    $resultado['token_error'] = $e->getMessage();
}

// 3. Probar búsqueda simple
try {
    $start = microtime(true);
    $searchResult = mlRequest('/products/search', [
        'q' => 'notebook',
        'site_id' => ML_SITE,
        'limit' => 1
    ]);
    $resultado['search_time'] = round((microtime(true) - $start) * 1000) . 'ms';
    $resultado['search_http_code'] = $searchResult['http_code'];
    $resultado['search_results'] = count($searchResult['data']['results'] ?? []);
} catch (Exception $e) {
    $resultado['search_error'] = $e->getMessage();
}

echo json_encode([
    'success' => true,
    'resultado' => $resultado
], JSON_PRETTY_PRINT);
