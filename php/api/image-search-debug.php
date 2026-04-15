<?php
/**
 * DEBUG: Buscar imágenes con logging completo
 * GET /api/image-search-debug.php?sku=XXX
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/mercadolibre.php';

$debug = [];
$debug['php_version'] = phpversion();
$debug['memory_usage'] = memory_get_usage(true);

try {
    // 1. Verificar autenticación
    if (!isAuthenticated()) {
        $debug['error'] = 'No autenticado';
        $debug['session'] = $_SESSION;
        ob_end_clean();
        echo json_encode($debug);
        exit();
    }
    $debug['authenticated'] = true;

    // 2. Obtener parámetros
    $sku = trim($_GET['sku'] ?? '');
    if (empty($sku)) {
        $debug['error'] = 'SKU requerido';
        ob_end_clean();
        echo json_encode($debug);
        exit();
    }
    $debug['sku'] = $sku;

    // 3. Conectar a SIGE
    try {
        $conn = getDbConnection();
        $debug['sige_connected'] = true;
    } catch (Exception $e) {
        $debug['sige_error'] = $e->getMessage();
        ob_end_clean();
        echo json_encode($debug);
        exit();
    }

    // 4. Buscar articulo en SIGE
    try {
        $stmt = $conn->prepare("
            SELECT
                TRIM(a.ART_IDArticulo) as sku,
                a.ART_DesArticulo as nombre,
                TRIM(a.ART_PartNumber) as part_number,
                TRIM(a.ART_CodBarraArt) as codigo_barras,
                a.ART_IdML as id_ml,
                d.adv_pathimagen as imagen_sige
            FROM sige_art_articulo a
            LEFT JOIN sige_adv_artdatvar d ON a.ART_IDArticulo = d.art_idarticulo
            WHERE TRIM(a.ART_IDArticulo) = ?
        ");
        $stmt->bind_param("s", $sku);
        $stmt->execute();
        $result = $stmt->get_result();
        $articulo = $result->fetch_assoc();
        $stmt->close();

        if (!$articulo) {
            $debug['error'] = 'Artículo no encontrado';
            ob_end_clean();
            echo json_encode($debug);
            exit();
        }

        $debug['articulo_encontrado'] = [
            'sku' => $articulo['sku'],
            'nombre' => $articulo['nombre'],
            'encoding' => mb_detect_encoding($articulo['nombre'])
        ];

    } catch (Exception $e) {
        $debug['sige_query_error'] = $e->getMessage();
        ob_end_clean();
        echo json_encode($debug);
        exit();
    }

    // 5. Buscar en ML
    try {
        $debug['buscando_ml'] = true;
        $resultadoML = buscarImagenesConFallback(
            $articulo['sku'],
            $articulo['part_number'],
            $articulo['nombre'],
            $articulo['codigo_barras']
        );
        $debug['resultado_ml'] = [
            'encontrado' => !empty($resultadoML['imagenes']),
            'encontrado_por' => $resultadoML['encontrado_por'],
            'imagenes_count' => count($resultadoML['imagenes'] ?? [])
        ];
    } catch (Exception $e) {
        $debug['ml_error'] = $e->getMessage();
        $debug['ml_trace'] = $e->getTraceAsString();
    }

    // 6. Intentar codificar a JSON
    $testData = [
        'success' => true,
        'debug' => $debug
    ];

    $json = json_encode($testData, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $debug['json_encode_error'] = json_last_error_msg();
        ob_end_clean();
        echo json_encode(['error' => $debug]);
        exit();
    }

    $debug['json_valid'] = true;
    ob_end_clean();
    echo $json;

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'debug' => $debug ?? []
    ]);
}
