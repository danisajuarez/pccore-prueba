<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

// REQUERIMIENTO OBLIGATORIO: Sesión activa (sin fallback a archivos .txt)
require_once __DIR__ . '/../bootstrap.php';

// Verificar que hay sesión de cliente activa
if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No hay sesión de cliente activa. Debe iniciar sesión primero.']));
}

// Obtener conexión desde DatabaseService (credenciales de sesión)
try {
    $db = getSigeConnection()->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => 'No se pudo conectar a la BD: ' . $e->getMessage()]));
}

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
