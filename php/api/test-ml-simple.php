<?php
/**
 * Test simple de qué endpoints funcionan con el token actual
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/mercadolibre.php';

$token = getMLAccessToken();

$resultado = [
    'token' => substr($token, 0, 30) . '...',
    'tests' => []
];

// Test 1: Obtener info de un item público conocido (MLA)
$itemId = 'MLA1384339498'; // Un item random de ML Argentina

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/items/$itemId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$resultado['tests']['get_item_sin_auth'] = [
    'endpoint' => "/items/$itemId",
    'http_code' => $httpCode,
    'funciona' => $httpCode === 200,
    'titulo' => $data['title'] ?? null,
    'thumbnail' => $data['thumbnail'] ?? null
];

// Test 2: Mismo pero con token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/items/$itemId?access_token=$token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$resultado['tests']['get_item_con_auth'] = [
    'endpoint' => "/items/$itemId?access_token=...",
    'http_code' => $httpCode,
    'funciona' => $httpCode === 200,
];

// Test 3: Buscar sin auth
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLA/search?q=mouse&limit=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$resultado['tests']['search_sin_auth'] = [
    'endpoint' => "/sites/MLA/search?q=mouse",
    'http_code' => $httpCode,
    'funciona' => $httpCode === 200,
    'total_resultados' => $data['paging']['total'] ?? 0,
    'error' => $data['message'] ?? null
];

// Test 4: Buscar con auth
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLA/search?q=mouse&limit=1&access_token=$token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$resultado['tests']['search_con_auth'] = [
    'endpoint' => "/sites/MLA/search?q=mouse&access_token=...",
    'http_code' => $httpCode,
    'funciona' => $httpCode === 200,
    'total_resultados' => $data['paging']['total'] ?? 0,
    'primer_resultado' => isset($data['results'][0]) ? [
        'id' => $data['results'][0]['id'],
        'title' => $data['results'][0]['title'],
        'thumbnail' => $data['results'][0]['thumbnail']
    ] : null,
    'error' => $data['message'] ?? null
];

// Test 5: Info del usuario del token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/users/me?access_token=$token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$resultado['tests']['user_me'] = [
    'endpoint' => "/users/me",
    'http_code' => $httpCode,
    'funciona' => $httpCode === 200,
    'user_id' => $data['id'] ?? null,
    'nickname' => $data['nickname'] ?? null,
    'error' => $data['message'] ?? null
];

// Resumen
$funcionan = array_filter($resultado['tests'], fn($t) => $t['funciona'] === true);
$resultado['resumen'] = [
    'endpoints_funcionando' => count($funcionan),
    'total_tests' => count($resultado['tests']),
    'conclusion' => count($funcionan) > 0
        ? 'Algunos endpoints funcionan - el token es válido'
        : 'Ningún endpoint funciona - posible bloqueo de IP o token inválido'
];

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
