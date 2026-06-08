<?php
/**
 * Consulta atributos de un producto específico
 * Uso: consulta-atributos.php?cliente=5&sku=685417406883
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/master.php';

$clienteId = $_GET['cliente'] ?? '';
$sku = $_GET['sku'] ?? '';

if (!$clienteId || !$sku) {
    die(json_encode(['error' => 'Parámetros requeridos: cliente, sku']));
}

// Conectar a BD master
$master = new mysqli(MASTER_DB_HOST, MASTER_DB_USER, MASTER_DB_PASS, MASTER_DB_NAME, MASTER_DB_PORT);
if ($master->connect_error) {
    die(json_encode(['error' => 'Error BD master: ' . $master->connect_error]));
}

// Obtener config del cliente
$stmt = $master->prepare("SELECT * FROM sige_two_terwoo WHERE TER_IdTercero = ?");
$stmt->bind_param("s", $clienteId);
$stmt->execute();
$config = $stmt->get_result()->fetch_assoc();
$stmt->close();
$master->close();

if (!$config) {
    die(json_encode(['error' => "Cliente $clienteId no encontrado"]));
}

// Conectar a BD del cliente
$sige = new mysqli(
    $config['TWO_ServidorDBAnt'],
    $config['TWO_UserDBAnt'],
    $config['TWO_PassDBAnt'],
    $config['TWO_NombreDBAnt'],
    $config['TWO_PuertoDBAnt'] ?: 3306
);

if ($sige->connect_error) {
    die(json_encode(['error' => 'Error BD cliente: ' . $sige->connect_error]));
}
$sige->set_charset('utf8');

$skuEsc = $sige->real_escape_string(trim($sku));

// Buscar producto
$sql = "SELECT ART_IDArticulo as sku, ART_DesArticulo as nombre, CAR_IdCar as marca_id, LIN_IDLinea as categoria_id
        FROM sige_art_articulo
        WHERE TRIM(ART_IDArticulo) = '$skuEsc'";
$prod = $sige->query($sql)->fetch_assoc();

if (!$prod) {
    die(json_encode(['error' => "Producto SKU '$sku' no encontrado"]));
}

// Buscar atributos
$sqlAttr = "SELECT atr_descatr as nombre, aat_descripcion as valor, aat_orden as orden
            FROM sige_aat_artatrib
            WHERE TRIM(art_idarticulo) = '$skuEsc'
            ORDER BY aat_orden";
$resAttr = $sige->query($sqlAttr);

$atributos = [];
if ($resAttr) {
    while ($row = $resAttr->fetch_assoc()) {
        $atributos[] = $row;
    }
}

$sige->close();

echo json_encode([
    'cliente' => [
        'id' => $clienteId,
        'nombre' => $config['TER_RazonSocialTer']
    ],
    'producto' => [
        'sku' => trim($prod['sku']),
        'nombre' => $prod['nombre'],
        'marca_id' => $prod['marca_id'],
        'categoria_id' => $prod['categoria_id']
    ],
    'atributos' => $atributos,
    'total_atributos' => count($atributos)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
