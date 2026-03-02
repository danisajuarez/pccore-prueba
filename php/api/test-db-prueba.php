<?php
// ============================================================================
// TEST: Verificar conexión y datos en la BD de prueba
// ============================================================================

header('Content-Type: application/json');

$config = [
    'host' => 'localhost',
    'user' => 'u962801258_0Ov4s',
    'pass' => 'Dona2012',
    'db'   => 'u962801258_vUylQ'
];

try {
    $conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['db']);

    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

    $resultado = [
        'conexion' => 'OK',
        'tablas' => []
    ];

    // Contar registros en sige_prs_presho
    $r1 = $conn->query("SELECT COUNT(*) as total FROM sige_prs_presho");
    $resultado['sige_prs_presho'] = $r1 ? $r1->fetch_assoc()['total'] : 'ERROR';

    // Contar diferencias (productos a sincronizar) - SIN JOIN para prueba
    $r3 = $conn->query("
        SELECT COUNT(*) as total
        FROM sige_prs_presho
        WHERE pal_precvtaart <> prs_precvtaart
           OR prs_disponible <> ads_disponible
    ");
    $resultado['productos_con_diferencias'] = $r3 ? $r3->fetch_assoc()['total'] : 'ERROR';

    // Mostrar 5 ejemplos de diferencias - SIN JOIN para prueba
    $r4 = $conn->query("
        SELECT art_idarticulo as sku,
               pal_precvtaart as precio_nuevo,
               prs_precvtaart as precio_sync,
               ads_disponible as stock_nuevo,
               prs_disponible as stock_sync
        FROM sige_prs_presho
        WHERE pal_precvtaart <> prs_precvtaart
           OR prs_disponible <> ads_disponible
        LIMIT 5
    ");

    $ejemplos = [];
    if ($r4) {
        while ($row = $r4->fetch_assoc()) {
            $ejemplos[] = $row;
        }
    }
    $resultado['ejemplos_diferencias'] = $ejemplos;

    $conn->close();

    echo json_encode($resultado, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
