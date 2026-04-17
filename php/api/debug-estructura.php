<?php
require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}

try {
    $db = getDbConnection();
    $resultado = [];

    // Ver estructura de sige_lin_linea
    $res = $db->query("DESCRIBE sige_lin_linea");
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    $resultado['sige_lin_linea'] = $cols;

    // Ver estructura de sige_gli_gruplin
    $res = $db->query("DESCRIBE sige_gli_gruplin");
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    $resultado['sige_gli_gruplin'] = $cols;

    // Ver estructura de sige_car_catarticulo
    $res = $db->query("DESCRIBE sige_car_catarticulo");
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    $resultado['sige_car_catarticulo'] = $cols;

    $db->close();
    echo json_encode(['success' => true, 'tablas' => $resultado], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
