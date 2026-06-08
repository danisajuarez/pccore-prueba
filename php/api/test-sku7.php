<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    die(json_encode(['error' => 'No autenticado']));
}

$sku = '7';

// Datos de SIGE
$db = getSigeConnection()->getConnection();
$stmt = $db->prepare("
    SELECT
        TRIM(a.ART_IDArticulo) as sku,
        a.ART_DesArticulo as nombre,
        lin.LIN_DesLinea as categoria_sige,
        gli.gli_descripcion as supracategoria_sige,
        car.CAR_DesCatArt as marca_sige
    FROM sige_art_articulo a
    LEFT JOIN sige_lin_linea lin ON a.LIN_IDLinea = lin.LIN_IDLinea
    LEFT JOIN sige_gli_gruplin gli ON lin.GLI_IdGli = gli.gli_idgli
    LEFT JOIN sige_car_catarticulo car ON a.CAR_IdCar = car.CAR_IdCar
    WHERE TRIM(a.ART_IDArticulo) = ?
");
$stmt->bind_param("s", $sku);
$stmt->execute();
$sige = $stmt->get_result()->fetch_assoc();

// Datos de WooCommerce
$config = $_SESSION['cliente_config'];
$url = $config['wc_url'] . "/products?sku=" . urlencode($sku);
$url .= "&consumer_key=" . $config['wc_key'] . "&consumer_secret=" . $config['wc_secret'];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$wooProducts = json_decode($response, true);
$woo = !empty($wooProducts) ? $wooProducts[0] : null;

echo json_encode([
    'sige' => $sige,
    'woocommerce' => $woo ? [
        'id' => $woo['id'],
        'name' => $woo['name'],
        'categories' => $woo['categories'],
        'attributes' => $woo['attributes']
    ] : null
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
