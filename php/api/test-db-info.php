<?php
/**
 * Test: Ver a qué base de datos está conectado el sistema
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

try {
    $config = $_SESSION['cliente_config'] ?? [];

    // Info de conexión (sin mostrar passwords)
    $dbInfo = [
        'sige_host' => $config['sige_host'] ?? 'NO DEFINIDO',
        'sige_db' => $config['sige_db'] ?? 'NO DEFINIDO',
        'sige_user' => $config['sige_user'] ?? 'NO DEFINIDO',
        'wc_url' => $config['wc_url'] ?? 'NO DEFINIDO'
    ];

    // Verificar marcas 234 y 235
    $dbService = getSigeConnection();
    $db = $dbService->getConnection();

    $sql = "SELECT CAR_IdCar, CAR_DesCatArt FROM sige_car_catarticulo WHERE CAR_IdCar IN (234, 235) ORDER BY CAR_IdCar";
    $result = $db->query($sql);

    $marcas = [];
    while ($row = $result->fetch_assoc()) {
        $marcas[] = [
            'id' => $row['CAR_IdCar'],
            'marca' => $row['CAR_DesCatArt']
        ];
    }

    $db->close();

    echo json_encode([
        'success' => true,
        'conexion' => $dbInfo,
        'marcas_234_235' => $marcas
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
