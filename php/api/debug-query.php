<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

// Conexión directa sin pasar por config.php
$clienteId = 'pccore';
$configFile = __DIR__ . '/../config/' . $clienteId . '.txt';

if (!file_exists($configFile)) {
    die(json_encode(['error' => 'Config no encontrado']));
}

$lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$config = [];
foreach ($lines as $line) {
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $config[trim($key)] = trim($value);
    }
}

$db = new mysqli(
    $config['db_host'] ?? 'localhost',
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port'] ?? 3306
);

if ($db->connect_error) {
    die(json_encode(['error' => 'DB: ' . $db->connect_error]));
}

$db->set_charset('utf8');

$sku = $_GET['sku'] ?? 'TL-WA701ND';

// Test 1: Buscar por PartNumber y IDArticulo
$sql1 = "SELECT ART_IDArticulo, ART_PartNumber, ART_DesArticulo
         FROM sige_art_articulo
         WHERE TRIM(ART_PartNumber) = ? OR TRIM(ART_IDArticulo) = ?
         LIMIT 5";
$stmt = $db->prepare($sql1);
$stmt->bind_param("ss", $sku, $sku);
$stmt->execute();
$result1 = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Test 2: Ver columnas de moneda en preartlis
$sql2 = "SHOW COLUMNS FROM sige_pal_preartlis LIKE 'MON%'";
$result2 = $db->query($sql2)->fetch_all(MYSQLI_ASSOC);

// Test 3: Ver tabla moneda
$sql3 = "SELECT * FROM sige_mon_moneda LIMIT 5";
$res3 = $db->query($sql3);
$result3 = $res3 ? $res3->fetch_all(MYSQLI_ASSOC) : ['error' => 'Tabla no existe'];

$db->close();

echo json_encode([
    'sku_buscado' => $sku,
    'productos_encontrados' => $result1,
    'columnas_moneda_en_preartlis' => $result2,
    'tabla_moneda' => $result3
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
