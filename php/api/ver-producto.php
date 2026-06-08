<?php
/**
 * Ver producto con todas sus relaciones
 */
session_start();
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$sku = $_GET['sku'] ?? '9992';

if (!isset($_SESSION['cliente_config'])) {
    die(json_encode(['error' => 'No hay sesion activa']));
}

$config = $_SESSION['cliente_config'];
$listaPrecio = $config['lista_precio'] ?? 1;
$deposito = $config['deposito'] ?? 1;

$conn = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);

if ($conn->connect_error) {
    die(json_encode(['error' => 'No se pudo conectar: ' . $conn->connect_error]));
}

$conn->set_charset("utf8");

$sku = $conn->real_escape_string(trim($sku));

// Consulta completa con todas las relaciones
$sql = "SELECT
    -- PRODUCTO
    a.ART_IDArticulo as sku,
    a.ART_DesArticulo as nombre,
    a.ART_PartNumber as part_number,
    a.ART_PorcIVARI as iva_porcentaje,
    a.CAR_IdCar as marca_id,
    a.LIN_IDLinea as categoria_id,
    a.MON_IdMon as moneda_id,

    -- MARCA
    car.CAR_IdCar as marca_id_tabla,
    car.CAR_DesCatArt as marca_nombre,

    -- CATEGORIA
    lin.LIN_IDLinea as categoria_id_tabla,
    lin.LIN_DesLinea as categoria_nombre,
    lin.GLI_IdGli as supracategoria_id,

    -- SUPRA-CATEGORIA
    gli.gli_idgli as supracategoria_id_tabla,
    gli.gli_descripcion as supracategoria_nombre,

    -- PRECIO
    p.LIS_IDListaPrecio as lista_precio_id,
    p.PAL_PrecVtaArt as precio_sin_iva,
    (p.PAL_PrecVtaArt * (1 + (a.ART_PorcIVARI / 100))) as precio_con_iva,

    -- MONEDA
    m.MON_IdMon as moneda_id_tabla,
    m.MON_CotizMon as cotizacion,

    -- STOCK
    s.DEP_IDDeposito as deposito_id,
    s.ADS_CanFisicoArt as stock_fisico,
    s.ADS_CanReservArt as stock_reservado,
    (s.ADS_CanFisicoArt - COALESCE(s.ADS_CanReservArt, 0)) as stock_disponible,

    -- DIMENSIONES
    d.ADV_Peso as peso,
    d.ADV_Alto as alto,
    d.ADV_Ancho as ancho,
    d.ADV_Profundidad as profundidad,

    -- SYNC WEB
    prs.pal_precvtaart as sync_precio_sige,
    prs.prs_precvtaart as sync_precio_web,
    prs.ads_disponible as sync_stock_sige,
    prs.prs_disponible as sync_stock_web,
    prs.prs_fecultactweb as sync_ultima_fecha

FROM sige_art_articulo a
LEFT JOIN sige_car_catarticulo car ON a.CAR_IdCar = car.CAR_IdCar
LEFT JOIN sige_lin_linea lin ON a.LIN_IDLinea = lin.LIN_IDLinea
LEFT JOIN sige_gli_gruplin gli ON lin.GLI_IdGli = gli.gli_idgli
LEFT JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo AND p.LIS_IDListaPrecio = $listaPrecio
LEFT JOIN sige_mon_moneda m ON a.MON_IdMon = m.MON_IdMon
LEFT JOIN sige_ads_artdepsck s ON a.ART_IDArticulo = s.ART_IDArticulo AND s.DEP_IDDeposito = $deposito
LEFT JOIN sige_adv_artdatvar d ON a.ART_IDArticulo = d.art_idarticulo
LEFT JOIN sige_prs_presho prs ON a.ART_IDArticulo = prs.art_idarticulo

WHERE TRIM(a.ART_IDArticulo) = '$sku'";

$result = $conn->query($sql);

if (!$result) {
    die(json_encode(['error' => 'Error en query: ' . $conn->error]));
}

$producto = $result->fetch_assoc();

if (!$producto) {
    die(json_encode(['error' => "Producto SKU '$sku' no encontrado"]));
}

// Buscar atributos
$sqlAttr = "SELECT atr_descatr as nombre, aat_descripcion as valor, aat_orden as orden
            FROM sige_aat_artatrib
            WHERE TRIM(art_idarticulo) = '$sku'
            ORDER BY aat_orden";
$resultAttr = $conn->query($sqlAttr);
$atributos = [];
if ($resultAttr) {
    while ($row = $resultAttr->fetch_assoc()) {
        $atributos[] = $row;
    }
}

// Organizar respuesta
$respuesta = [
    'conexion' => [
        'host' => $config['db_host'],
        'bd' => $config['db_name'],
        'puerto' => $config['db_port']
    ],
    'producto' => [
        'sku' => trim($producto['sku']),
        'nombre' => $producto['nombre'],
        'part_number' => $producto['part_number'],
        'iva' => $producto['iva_porcentaje'] . '%'
    ],
    'marca' => [
        'id' => $producto['marca_id'],
        'nombre' => $producto['marca_nombre']
    ],
    'categoria' => [
        'id' => $producto['categoria_id'],
        'nombre' => $producto['categoria_nombre']
    ],
    'supracategoria' => [
        'id' => $producto['supracategoria_id'],
        'nombre' => $producto['supracategoria_nombre']
    ],
    'precio' => [
        'lista_id' => $producto['lista_precio_id'],
        'sin_iva' => $producto['precio_sin_iva'],
        'con_iva' => $producto['precio_con_iva'],
        'moneda_cotizacion' => $producto['cotizacion']
    ],
    'stock' => [
        'deposito_id' => $producto['deposito_id'],
        'fisico' => $producto['stock_fisico'],
        'reservado' => $producto['stock_reservado'],
        'disponible' => $producto['stock_disponible']
    ],
    'dimensiones' => [
        'peso' => $producto['peso'],
        'alto' => $producto['alto'],
        'ancho' => $producto['ancho'],
        'profundidad' => $producto['profundidad']
    ],
    'sync_web' => [
        'precio_sige' => $producto['sync_precio_sige'],
        'precio_web' => $producto['sync_precio_web'],
        'stock_sige' => $producto['sync_stock_sige'],
        'stock_web' => $producto['sync_stock_web'],
        'ultima_sync' => $producto['sync_ultima_fecha']
    ],
    'atributos' => $atributos,
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$conn->close();
