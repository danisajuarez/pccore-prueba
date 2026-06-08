<?php
/**
 * Diagnóstico de SKU - Ver estado real en WooCommerce
 * USO: /api/diag-sku.php?sku=6971636409427
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    echo json_encode(['error' => 'Logueate primero en el panel']);
    exit;
}

$sku = trim($_GET['sku'] ?? '');
if (empty($sku)) {
    echo json_encode(['error' => 'Falta SKU. Uso: ?sku=6971636409427']);
    exit;
}

$config = $_SESSION['cliente_config'];

function wc($endpoint) {
    global $config;
    $url = $config['wc_url'] . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . urlencode($config['wc_key']) . '&consumer_secret=' . urlencode($config['wc_secret']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

$productos = wc('/products?sku=' . urlencode($sku) . '&status=any');

if (empty($productos)) {
    echo json_encode(['error' => 'Producto NO existe en WooCommerce', 'sku' => $sku]);
    exit;
}

$p = null;
foreach ($productos as $prod) {
    if (strcasecmp(trim($prod['sku']), trim($sku)) === 0) {
        $p = $prod;
        break;
    }
}

if (!$p) {
    echo json_encode(['error' => 'SKU no coincide exactamente']);
    exit;
}

// Datos clave
$resultado = [
    'sku' => $sku,
    'woo_id' => $p['id'],
    'nombre' => $p['name'],
    'estado' => [
        'status' => $p['status'],
        'catalog_visibility' => $p['catalog_visibility'],
        'stock_status' => $p['stock_status'],
        'stock_quantity' => $p['stock_quantity'],
        'manage_stock' => $p['manage_stock'],
        'in_stock' => $p['in_stock'] ?? null,
        'purchasable' => $p['purchasable'] ?? null,
    ],
    'categorias' => $p['categories'],
    'permalink' => $p['permalink'],
];

// Diagnóstico
$problemas = [];

if ($p['status'] !== 'publish') {
    $problemas[] = "❌ STATUS = '{$p['status']}' (debería ser 'publish')";
}

if ($p['catalog_visibility'] !== 'visible') {
    $problemas[] = "❌ CATALOG_VISIBILITY = '{$p['catalog_visibility']}' (debería ser 'visible')";
}

if ($p['stock_status'] !== 'instock') {
    $problemas[] = "❌ STOCK_STATUS = '{$p['stock_status']}' (debería ser 'instock')";
}

if (empty($p['categories'])) {
    $problemas[] = "❌ SIN CATEGORÍAS asignadas";
}

$resultado['problemas'] = $problemas;
$resultado['veredicto'] = empty($problemas)
    ? '✅ Todo OK - problema es caché o tema'
    : '🔴 HAY PROBLEMAS - ver lista arriba';

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
