<?php
/**
 * Debug: Ver tablas de precios disponibles
 */

require_once __DIR__ . '/../config.php';
checkAuth();

try {
    $db = getDbConnection();

    $resultado = [];

    // Buscar tablas que contengan "pre" o "precio"
    $res = $db->query("SHOW TABLES LIKE '%pre%'");
    $tablas = [];
    while ($row = $res->fetch_array()) {
        $tabla = $row[0];
        $count = $db->query("SELECT COUNT(*) as c FROM `$tabla`")->fetch_assoc()['c'];
        $tablas[] = ['tabla' => $tabla, 'registros' => (int)$count];
    }
    $resultado['tablas_pre'] = $tablas;

    // Ver estructura de sige_prs_presho
    $res = $db->query("DESCRIBE sige_prs_presho");
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    $resultado['columnas_presho'] = $cols;

    // Contar registros en sige_prs_presho
    $count = $db->query("SELECT COUNT(*) as c FROM sige_prs_presho")->fetch_assoc()['c'];
    $resultado['total_presho'] = (int)$count;

    // Ver algunos precios de ejemplo si hay
    if ($count > 0) {
        $res = $db->query("SELECT * FROM sige_prs_presho LIMIT 3");
        $ejemplos = [];
        while ($row = $res->fetch_assoc()) {
            $ejemplos[] = $row;
        }
        $resultado['ejemplos_presho'] = $ejemplos;
    }

    // Buscar tabla de listas de precio
    $res = $db->query("SHOW TABLES LIKE '%lis%'");
    $tablas = [];
    while ($row = $res->fetch_array()) {
        $tabla = $row[0];
        $count = $db->query("SELECT COUNT(*) as c FROM `$tabla`")->fetch_assoc()['c'];
        $tablas[] = ['tabla' => $tabla, 'registros' => (int)$count];
    }
    $resultado['tablas_lis'] = $tablas;

    $db->close();

    echo json_encode([
        'success' => true,
        'resultado' => $resultado
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
