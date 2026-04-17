<?php
/**
 * Debug: Ver datos completos de producto en ML
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/mercadolibre.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}

$query = trim($_GET['q'] ?? 'AT902');

try {
    // Búsqueda
    $searchResult = mlRequest('/products/search', [
        'q' => $query,
        'site_id' => ML_SITE,
        'limit' => 3
    ]);

    $resultado = [
        'query' => $query,
        'search_http_code' => $searchResult['http_code'],
        'productos' => []
    ];

    if (!empty($searchResult['data']['results'])) {
        foreach (array_slice($searchResult['data']['results'], 0, 2) as $prod) {
            $prodData = [
                'id' => $prod['id'],
                'nombre' => $prod['name'],
                'pictures_en_search' => $prod['pictures'] ?? 'NO VIENE',
                'tiene_pictures_key' => isset($prod['pictures']),
                'keys_disponibles' => array_keys($prod)
            ];

            // Obtener detalle del producto
            try {
                $detalle = mlRequest('/products/' . $prod['id']);
                $prodData['detalle_http_code'] = $detalle['http_code'];
                if ($detalle['http_code'] === 200 && isset($detalle['data']['pictures'])) {
                    $prodData['pictures_en_detalle'] = count($detalle['data']['pictures']);
                    $prodData['primera_imagen'] = $detalle['data']['pictures'][0]['url'] ?? null;
                } else {
                    $prodData['pictures_en_detalle'] = 'NO DISPONIBLE';
                }
            } catch (Exception $e) {
                $prodData['detalle_error'] = $e->getMessage();
            }

            $resultado['productos'][] = $prodData;
        }
    }

    echo json_encode(['success' => true, 'resultado' => $resultado], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
