<?php
/**
 * Test: Probar API de ML con autenticación
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/mercadolibre.php';

$query = $_GET['q'] ?? 'TL-WA701ND';

$resultado = [
    'query' => $query
];

try {
    $token = getMLAccessToken();
    $resultado['token'] = 'OK: ' . substr($token, 0, 15) . '...';

    // Probar /products/search (CATÁLOGO - diferente a /sites/MLA/search)
    $resultado['products_search'] = 'Probando /products/search...';
    $resp1 = mlRequest('/products/search', [
        'q' => $query,
        'site_id' => ML_SITE,
        'limit' => 5
    ]);
    $resultado['products_search'] = [
        'http_code' => $resp1['http_code'],
        'total' => count($resp1['data']['results'] ?? []),
        'ejemplos' => array_map(function($p) {
            return [
                'id' => $p['id'],
                'name' => $p['name'] ?? 'N/A',
                'pictures' => count($p['pictures'] ?? [])
            ];
        }, array_slice($resp1['data']['results'] ?? [], 0, 3))
    ];

    // Probar /sites/MLA/search (PUBLICACIONES)
    $resultado['sites_search'] = 'Probando /sites/MLA/search...';
    $resp2 = mlRequest('/sites/' . ML_SITE . '/search', [
        'q' => $query,
        'limit' => 5
    ]);
    $resultado['sites_search'] = [
        'http_code' => $resp2['http_code'],
        'total' => count($resp2['data']['results'] ?? [])
    ];

} catch (Exception $e) {
    $resultado['error'] = $e->getMessage();
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
