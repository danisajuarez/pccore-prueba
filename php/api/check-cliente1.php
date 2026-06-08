<?php
/**
 * Ver configuracion del cliente 1 en la BD Master
 */
require_once __DIR__ . '/../config/master.php';

header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli(MASTER_DB_HOST, MASTER_DB_USER, MASTER_DB_PASS, MASTER_DB_NAME, MASTER_DB_PORT);

if ($conn->connect_error) {
    die(json_encode(['error' => 'No se pudo conectar a BD Master: ' . $conn->connect_error]));
}

$conn->set_charset("utf8");

// Obtener cliente 1
$result = $conn->query("SELECT * FROM sige_two_terwoo WHERE TER_IdTercero = 1");

if (!$result) {
    die(json_encode(['error' => 'Error en query: ' . $conn->error]));
}

$cliente = $result->fetch_assoc();

if (!$cliente) {
    die(json_encode(['error' => 'Cliente 1 no encontrado en sige_two_terwoo']));
}

// Mostrar todos los campos
echo json_encode($cliente, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$conn->close();
