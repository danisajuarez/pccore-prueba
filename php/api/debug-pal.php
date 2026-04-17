<?php
/**
 * Debug: Ver estructura de sige_pal_preartlis
 */

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}
checkAuth();

$sku = trim($_GET['sku'] ?? 'AT902');

try {
    $db = getDbConnection();

    $resultado = [];

    // Estructura de la tabla
    $res = $db->query("DESCRIBE sige_pal_preartlis");
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[] = ['campo' => $row['Field'], 'tipo' => $row['Type']];
    }
    $resultado['columnas'] = $cols;

    // Buscar precio del SKU
    $stmt = $db->prepare("SELECT * FROM sige_pal_preartlis WHERE TRIM(art_idarticulo) = ? LIMIT 5");
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $res = $stmt->get_result();
    $precios = [];
    while ($row = $res->fetch_assoc()) {
        $precios[] = $row;
    }
    $resultado['precios_sku'] = $precios;
    $stmt->close();

    // Ejemplo de 3 registros cualesquiera
    $res = $db->query("SELECT * FROM sige_pal_preartlis LIMIT 3");
    $ejemplos = [];
    while ($row = $res->fetch_assoc()) {
        $ejemplos[] = $row;
    }
    $resultado['ejemplos'] = $ejemplos;

    $db->close();

    echo json_encode([
        'success' => true,
        'sku' => $sku,
        'resultado' => $resultado
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
