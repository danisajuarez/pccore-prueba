<?php
/**
 * Debug: Ver qué devuelve ML para imágenes de un producto
 * Usa el endpoint de publicaciones con validación exacta de código
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/mercadolibre.php';

$sku = $_GET['sku'] ?? '';
$partNumber = $_GET['pn'] ?? $sku;
$nombre = $_GET['nombre'] ?? '';

if (empty($sku)) {
    die(json_encode(['error' => 'Falta parámetro sku']));
}

$debug = [
    'parametros' => [
        'sku' => $sku,
        'partNumber' => $partNumber,
        'nombre' => $nombre
    ],
    'busquedas' => []
];

// Búsqueda raw para debug
function busquedaDebug($query, $nombre) {
    $queryLower = mb_strtolower($query, 'UTF-8');

    // Endpoint de publicaciones
    $result = mlRequest('/sites/' . ML_SITE . '/search', [
        'q' => $query,
        'limit' => 20,
        'condition' => 'new'
    ]);

    $data = [
        'query' => $query,
        'http_code' => $result['http_code'],
        'total_resultados' => count($result['data']['results'] ?? []),
        'productos' => []
    ];

    if (!empty($result['data']['results'])) {
        foreach ($result['data']['results'] as $item) {
            $tituloLower = mb_strtolower($item['title'] ?? '', 'UTF-8');
            $contieneCodigoExacto = strpos($tituloLower, $queryLower) !== false;
            $esRelevante = empty($nombre) ? 'N/A' : (esProductoRelevante($item['title'], $nombre) ? 'SI' : 'NO');

            $aceptado = 'NO';
            $motivo = '';
            if (!$contieneCodigoExacto) {
                $motivo = 'No contiene código exacto';
            } elseif ($esRelevante === 'NO') {
                $motivo = 'Filtro anti-accesorios';
            } else {
                $aceptado = 'SI';
                $motivo = 'Código exacto encontrado';
            }

            $data['productos'][] = [
                'id' => $item['id'],
                'titulo' => $item['title'],
                'precio' => $item['price'] ?? null,
                'thumbnail' => $item['thumbnail'] ?? null,
                'contiene_codigo_exacto' => $contieneCodigoExacto ? 'SI' : 'NO',
                'es_relevante' => $esRelevante,
                'aceptado' => $aceptado,
                'motivo' => $motivo
            ];
        }
    }

    return $data;
}

// Buscar por SKU
$debug['busquedas']['por_sku'] = busquedaDebug($sku, $nombre);

// Buscar por Part Number si es diferente
if ($partNumber && $partNumber !== $sku) {
    $debug['busquedas']['por_partNumber'] = busquedaDebug($partNumber, $nombre);
}

// Resultado final de la función real
$debug['resultado_funcion_real'] = buscarImagenesConFallback($sku, $partNumber, $nombre);

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
