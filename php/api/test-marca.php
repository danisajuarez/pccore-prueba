<?php
/**
 * Test: Consultar marca directamente de SIGE
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$sku = trim($_GET['sku'] ?? '234');

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
            WHERE TRIM(a.ART_IDArticulo) = ?";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();
    $db->close();

    if ($row) {
        echo json_encode([
            'success' => true,
            'sku' => trim($row['sku']),
            'nombre' => $row['nombre'],
            'marca_id' => $row['marca_id'],
            'marca' => $row['marca']
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['success' => false, 'error' => "SKU $sku no encontrado"]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
