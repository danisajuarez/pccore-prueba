<?php
/**
 * Test de búsqueda en Mercado Libre - GTIN y Keywords
 */

header('Content-Type: application/json; charset=utf-8');

// Cargar configuración de ML
require_once __DIR__ . '/../config/mercadolibre.php';

// Productos de prueba
$testProducts = [
    '5099206062344' => 'Logitech M170',
    '0740617246155' => 'Kingston DataTraveler 32GB',
    '4549292090574' => 'Canon Pixma G3110',
];

$ean = $_GET['ean'] ?? null;
$keywords = $_GET['q'] ?? null;

// Si no hay parámetros, usar ejemplo
if (!$ean && !$keywords) {
    $keywords = 'Logitech M170';
}

// Debug: verificar token
$token = getMLAccessToken();
$tokenInfo = [
    'tiene_token' => !empty($token),
    'longitud' => strlen($token ?? ''),
    'primeros_chars' => substr($token ?? '', 0, 20) . '...'
];

$resultado = [
    'busqueda' => $ean ? "EAN: $ean" : "Keywords: $keywords",
    'token_debug' => $tokenInfo,
    'pruebas' => []
];

// Prueba 1: Buscar en catálogo por product_identifier (solo si hay EAN)
if ($ean) {
    try {
        $response1 = mlRequest('/products/search', [
            'site_id' => 'MLA',
            'product_identifier' => $ean
        ]);

        $resultado['pruebas']['products_by_ean'] = [
            'endpoint' => '/products/search?product_identifier=' . $ean,
            'exito' => !empty($response1['results']),
            'cantidad' => count($response1['results'] ?? []),
        ];
    } catch (Exception $e) {
        $resultado['pruebas']['products_by_ean'] = ['error' => $e->getMessage()];
    }
}

// Prueba 2: Buscar en catálogo por keywords
$searchTerm = $keywords ?? $ean;

// DEBUG: Construir URL manualmente para ver qué pasa
$testParams = [
    'site_id' => 'MLA',
    'keywords' => $searchTerm,
    'limit' => 5
];
$resultado['debug_url'] = 'https://api.mercadolibre.com/products/search?' . http_build_query($testParams);

try {
    $response2 = mlRequest('/products/search', $testParams);

    $resultado['pruebas']['products_by_keywords'] = [
        'endpoint' => '/products/search?keywords=' . urlencode($searchTerm),
        'exito' => !empty($response2['results']),
        'cantidad' => count($response2['results'] ?? []),
        'raw_response' => $response2, // DEBUG: ver respuesta completa
        'productos' => array_map(function($p) {
            return [
                'id' => $p['id'] ?? null,
                'name' => $p['name'] ?? null,
                'pictures' => count($p['pictures'] ?? []),
            ];
        }, $response2['results'] ?? [])
    ];

    // Si encontramos producto, traer detalles del primero
    if (!empty($response2['results'][0]['id'])) {
        $productId = $response2['results'][0]['id'];
        $detalle = mlRequest('/products/' . $productId, []);

        $resultado['producto_encontrado'] = [
            'id' => $detalle['id'] ?? null,
            'name' => $detalle['name'] ?? null,
            'pictures' => array_slice(array_map(function($pic) {
                return $pic['url'] ?? null;
            }, $detalle['pictures'] ?? []), 0, 3),
            'description' => $detalle['short_description']['content'] ?? null,
            'attributes_sample' => array_slice(array_map(function($attr) {
                return $attr['name'] . ': ' . ($attr['value_name'] ?? $attr['values'][0]['name'] ?? 'N/A');
            }, $detalle['attributes'] ?? []), 0, 5),
        ];
    }
} catch (Exception $e) {
    $resultado['pruebas']['products_by_keywords'] = ['error' => $e->getMessage()];
}

// Prueba 3: Buscar con token en query param (alternativa)
try {
    $url = 'https://api.mercadolibre.com/sites/MLA/search?q=' . urlencode($searchTerm) . '&limit=3&access_token=' . $token;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $response3 = json_decode($response, true);

    $resultado['pruebas']['items_search_public'] = [
        'endpoint' => $url,
        'http_code' => $httpCode,
        'exito' => !empty($response3['results']),
        'cantidad' => $response3['paging']['total'] ?? 0,
        'items' => array_map(function($item) {
            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'price' => $item['price'],
                'thumbnail' => $item['thumbnail'],
                'catalog_product_id' => $item['catalog_product_id'] ?? null,
            ];
        }, array_slice($response3['results'] ?? [], 0, 3))
    ];

    // Si encontramos un producto con catalog_product_id, traer datos del catálogo
    if (!empty($response3['results'][0]['catalog_product_id'])) {
        $catalogId = $response3['results'][0]['catalog_product_id'];

        // Endpoint de producto del catálogo (también público)
        $urlProduct = 'https://api.mercadolibre.com/products/' . $catalogId;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlProduct);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $responseProduct = curl_exec($ch);
        curl_close($ch);

        $productData = json_decode($responseProduct, true);

        if (!empty($productData['id'])) {
            $resultado['producto_catalogo'] = [
                'id' => $productData['id'],
                'name' => $productData['name'] ?? null,
                'pictures' => array_slice(array_map(function($pic) {
                    return $pic['url'] ?? null;
                }, $productData['pictures'] ?? []), 0, 3),
                'short_description' => $productData['short_description']['content'] ?? null,
            ];
        }
    }
} catch (Exception $e) {
    $resultado['pruebas']['items_search_public'] = ['error' => $e->getMessage()];
}

// Resumen
$resultado['resumen'] = [
    'funciona' => !empty($resultado['producto_encontrado']),
    'tiene_imagenes' => !empty($resultado['producto_encontrado']['pictures']),
    'tiene_descripcion' => !empty($resultado['producto_encontrado']['description']),
    'nota' => 'Usar ?q=nombre+producto para buscar por keywords, o ?ean=codigo para buscar por EAN'
];

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
