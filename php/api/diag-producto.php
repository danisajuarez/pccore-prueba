<?php
/**
 * Diagnóstico detallado de un producto en WooCommerce
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$sku = trim($_GET['sku'] ?? '');
if (empty($sku)) {
    echo json_encode(['error' => 'SKU requerido. Uso: ?sku=097855163561']);
    exit;
}

function wcRequest($endpoint, $method = 'GET', $data = null) {
    $config = $_SESSION['cliente_config'];
    $url = $config['wc_url'] . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . urlencode($config['wc_key']) . '&consumer_secret=' . urlencode($config['wc_secret']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

try {
    // Buscar producto en WooCommerce con TODOS los campos
    $wcProducts = wcRequest('/products?sku=' . urlencode($sku) . '&status=any');

    if (empty($wcProducts)) {
        echo json_encode(['error' => 'Producto no encontrado en WooCommerce']);
        exit;
    }

    $producto = null;
    foreach ($wcProducts as $p) {
        if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
            $producto = $p;
            break;
        }
    }

    if (!$producto) {
        echo json_encode(['error' => 'SKU no coincide exactamente']);
        exit;
    }

    $resultado = [
        'sku' => $sku,
        'id' => $producto['id'],
        'nombre' => $producto['name'],
        'status' => $producto['status'],
        'catalog_visibility' => $producto['catalog_visibility'],
        'stock_status' => $producto['stock_status'],
        'stock_quantity' => $producto['stock_quantity'],
        'manage_stock' => $producto['manage_stock'],
        'in_stock' => $producto['in_stock'] ?? null,
        'purchasable' => $producto['purchasable'],
        'featured' => $producto['featured'],
        'type' => $producto['type'],
        'virtual' => $producto['virtual'],
        'downloadable' => $producto['downloadable'],
        'categories' => $producto['categories'],
        'tags' => $producto['tags'],
        'meta_data' => $producto['meta_data'],
        'date_created' => $producto['date_created'],
        'date_modified' => $producto['date_modified'],
    ];

    // Diagnóstico
    $problemas = [];

    if ($producto['status'] !== 'publish') {
        $problemas[] = "Estado no es 'publish': " . $producto['status'];
    }

    if ($producto['catalog_visibility'] === 'hidden') {
        $problemas[] = "Visibilidad del catálogo es 'hidden'";
    }

    if ($producto['catalog_visibility'] === 'search') {
        $problemas[] = "Solo visible en búsqueda, no en catálogo";
    }

    if ($producto['stock_status'] !== 'instock') {
        $problemas[] = "Stock status NO es 'instock': " . $producto['stock_status'];
    }

    if (empty($producto['categories'])) {
        $problemas[] = "No tiene categorías asignadas";
    }

    $resultado['problemas_detectados'] = $problemas;
    $resultado['veredicto'] = empty($problemas) ? 'Todo parece correcto - problema puede ser caché o tema' : 'HAY PROBLEMAS - ver lista arriba';

    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
