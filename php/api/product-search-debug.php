<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Capturar cualquier output/warning
ob_start();

$response = ['success' => false, 'error' => 'Unknown'];

try {
    $result = ['test1' => 'PHP OK'];

    // Test 2: Config
    require_once __DIR__ . '/../config.php';
    $result['test2'] = 'Config OK';
    $result['cliente_id'] = $CLIENTE_ID ?? 'no definido';

    // Test 3: SKU
    $sku = $_GET['sku'] ?? '';
    $result['test3'] = 'SKU: ' . $sku;

    // Test 4: DB
    $db = getDbConnection();
    $result['test4'] = 'DB Connected';

    // Test 5: Query simple
    $sql = "SELECT ART_IDArticulo, ART_DesArticulo FROM sige_art_articulo WHERE TRIM(ART_IDArticulo) = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $queryResult = $stmt->get_result();

    if ($queryResult->num_rows > 0) {
        $row = $queryResult->fetch_assoc();
        $result['test5'] = 'Found: ' . $row['ART_DesArticulo'];
    } else {
        $result['test5'] = 'Not found in DB';
    }

    $stmt->close();
    $db->close();

    // Devolver estructura esperada por el frontend
    $producto = null;
    if (isset($row)) {
        $producto = [
            'sku' => $sku,
            'nombre' => $row['ART_DesArticulo'] ?? 'TEST PRODUCTO',
            'part_number' => null,
            'descripcion_larga' => null,
            'precio_sin_iva' => 100,
            'precio' => 121,
            'stock' => 10,
            'peso' => null,
            'alto' => null,
            'ancho' => null,
            'profundidad' => null,
            'atributos' => []
        ];
    }

    $response = [
        'success' => true,
        'producto' => $producto,
        'woo_producto' => null,
        '_debug' => $result
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
} catch (Error $e) {
    $response = [
        'success' => false,
        'error' => 'Fatal: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
}

// Capturar cualquier output previo (warnings, notices, HTML de errores)
$buffered = ob_get_clean();
if (!empty($buffered)) {
    $response['_output_buffer'] = substr($buffered, 0, 2000);
}

// Ahora sí enviar la respuesta JSON limpia
header('Content-Type: application/json');
echo json_encode($response);
