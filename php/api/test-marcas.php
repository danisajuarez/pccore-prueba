<?php
/**
 * Test: Consultar marcas de SKUs 234 y 9992
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

try {
    $dbService = getSigeConnection();
    $db = $dbService->getConnection();

    $sql = "SELECT
                a.ART_IDArticulo as sku,
                a.ART_DesArticulo as nombre,
                a.CAR_IdCar as marca_id,
                car.CAR_DesCatArt as marca
            FROM sige_art_articulo a
            LEFT JOIN sige_car_catarticulo car ON a.CAR_IdCar = car.CAR_IdCar
            WHERE TRIM(a.ART_IDArticulo) IN ('234', '9992')";

    $result = $db->query($sql);

    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = [
            'sku' => trim($row['sku']),
            'nombre' => $row['nombre'],
            'marca_id' => $row['marca_id'],
            'marca' => $row['marca']
        ];
    }

    $db->close();

    echo json_encode([
        'success' => true,
        'productos' => $productos
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
