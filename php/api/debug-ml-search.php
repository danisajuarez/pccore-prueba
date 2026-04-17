<?php
/**
 * Debug: Probar búsqueda de producto en ML
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/mercadolibre.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}

$sku = trim($_GET['sku'] ?? 'AT902');

try {
    $db = getDbConnection();

    // Buscar datos del artículo
    $stmt = $db->prepare("SELECT ART_IDArticulo, ART_DesArticulo, ART_PartNumber
                          FROM sige_art_articulo WHERE TRIM(ART_IDArticulo) = ?");
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $articulo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$articulo) {
        echo json_encode(['success' => false, 'error' => 'Artículo no encontrado']);
        exit;
    }

    $resultado = [
        'articulo' => [
            'sku' => trim($articulo['ART_IDArticulo']),
            'nombre' => $articulo['ART_DesArticulo'],
            'part_number' => trim($articulo['ART_PartNumber'] ?? '')
        ],
        'busquedas' => []
    ];

    // Probar búsqueda por Part Number
    $pn = trim($articulo['ART_PartNumber'] ?? '');
    if ($pn && strlen($pn) >= 3) {
        $start = microtime(true);
        $res = mlRequest('/products/search', ['q' => $pn, 'site_id' => ML_SITE, 'limit' => 3]);
        $resultado['busquedas']['part_number'] = [
            'query' => $pn,
            'time' => round((microtime(true) - $start) * 1000) . 'ms',
            'http_code' => $res['http_code'],
            'total_results' => count($res['data']['results'] ?? []),
            'resultados' => array_map(function($p) {
                return [
                    'id' => $p['id'],
                    'nombre' => $p['name'],
                    'tiene_imagenes' => !empty($p['pictures'])
                ];
            }, array_slice($res['data']['results'] ?? [], 0, 3))
        ];
    }

    // Probar búsqueda por SKU
    if (strlen($sku) >= 3) {
        $start = microtime(true);
        $res = mlRequest('/products/search', ['q' => $sku, 'site_id' => ML_SITE, 'limit' => 3]);
        $resultado['busquedas']['sku'] = [
            'query' => $sku,
            'time' => round((microtime(true) - $start) * 1000) . 'ms',
            'http_code' => $res['http_code'],
            'total_results' => count($res['data']['results'] ?? []),
            'resultados' => array_map(function($p) {
                return [
                    'id' => $p['id'],
                    'nombre' => $p['name'],
                    'tiene_imagenes' => !empty($p['pictures'])
                ];
            }, array_slice($res['data']['results'] ?? [], 0, 3))
        ];
    }

    // Probar búsqueda por nombre (primeras palabras)
    $nombre = $articulo['ART_DesArticulo'];
    $nombreLimpio = preg_replace('/[^\w\s\-]/u', ' ', $nombre);
    $nombreLimpio = preg_replace('/\s+/', ' ', trim($nombreLimpio));
    $palabras = explode(' ', $nombreLimpio);
    $queryNombre = implode(' ', array_slice($palabras, 0, 4)); // Primeras 4 palabras

    if ($queryNombre) {
        $start = microtime(true);
        $res = mlRequest('/products/search', ['q' => $queryNombre, 'site_id' => ML_SITE, 'limit' => 3]);
        $resultado['busquedas']['nombre'] = [
            'query' => $queryNombre,
            'time' => round((microtime(true) - $start) * 1000) . 'ms',
            'http_code' => $res['http_code'],
            'total_results' => count($res['data']['results'] ?? []),
            'resultados' => array_map(function($p) {
                return [
                    'id' => $p['id'],
                    'nombre' => $p['name'],
                    'tiene_imagenes' => !empty($p['pictures'])
                ];
            }, array_slice($res['data']['results'] ?? [], 0, 3))
        ];
    }

    echo json_encode(['success' => true, 'resultado' => $resultado], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
