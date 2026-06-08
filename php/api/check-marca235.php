<?php
/**
 * Consultar marca 235 directamente desde la BD SIGE
 */
session_start();
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['cliente_config'])) {
    die(json_encode(['error' => 'No hay sesion activa']));
}

$config = $_SESSION['cliente_config'];

// Conectar a la BD SIGE del cliente
$conn = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);

if ($conn->connect_error) {
    die(json_encode([
        'error' => 'No se pudo conectar',
        'detalle' => $conn->connect_error,
        'conexion' => [
            'host' => $config['db_host'],
            'puerto' => $config['db_port'],
            'bd' => $config['db_name']
        ]
    ]));
}

$conn->set_charset("utf8");

// Buscar marca 235 en sige_mar_marca
$result = $conn->query("SELECT * FROM sige_mar_marca WHERE MAR_IdMarca = 235");

$marca = $result ? $result->fetch_assoc() : null;

// Buscar productos con esa marca en sige_car_catarticulo
$result2 = $conn->query("SELECT CAR_IdArticulo, CAR_Descripcion, CAR_Marca FROM sige_car_catarticulo WHERE CAR_Marca = 235 LIMIT 5");

$productos = [];
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        $productos[] = $row;
    }
}

echo json_encode([
    'conexion' => [
        'host' => $config['db_host'],
        'puerto' => $config['db_port'],
        'bd' => $config['db_name']
    ],
    'marca_235' => $marca,
    'productos_con_marca_235' => $productos,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$conn->close();
