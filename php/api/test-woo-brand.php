<?php
/**
 * Test: Ver y asignar marcas en WooCommerce (plugin de brands)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    echo json_encode(['error' => 'No autenticado']);
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

    if ($method === 'PUT' || $method === 'POST') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

$action = $_GET['action'] ?? 'info';
$productId = $_GET['id'] ?? '21885';

try {
    if ($action === 'info') {
        // Ver campos de marca del producto
        $result = wcRequest('/products/' . $productId);
        $product = $result['data'];

        echo json_encode([
            'success' => true,
            'product_id' => $productId,
            'name' => $product['name'] ?? null,
            'brands' => $product['brands'] ?? 'NO EXISTE',
            'attributes' => $product['attributes'] ?? [],
            'meta_data' => $product['meta_data'] ?? [],
            'categories' => $product['categories'] ?? []
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'list_brands') {
        // Listar todas las marcas disponibles (taxonomía pwb-brand)
        $result = wcRequest('/products/brands?per_page=100');

        if ($result['http_code'] >= 400) {
            // Intentar con otra ruta
            $result = wcRequest('/brands?per_page=100');
        }

        echo json_encode([
            'success' => $result['http_code'] < 400,
            'http_code' => $result['http_code'],
            'brands' => $result['data']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'search_brand') {
        // Buscar una marca específica
        $brandName = $_GET['name'] ?? 'VERY JET';
        $result = wcRequest('/products/brands?search=' . urlencode($brandName));

        if ($result['http_code'] >= 400) {
            $result = wcRequest('/brands?search=' . urlencode($brandName));
        }

        echo json_encode([
            'success' => $result['http_code'] < 400,
            'search' => $brandName,
            'brands' => $result['data']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'assign_brand') {
        // Asignar marca al producto
        $brandId = intval($_GET['brand_id'] ?? 0);

        if ($brandId <= 0) {
            echo json_encode(['error' => 'Falta brand_id']);
            exit;
        }

        $updateData = [
            'brands' => [['id' => $brandId]]
        ];

        $result = wcRequest('/products/' . $productId, 'PUT', $updateData);

        echo json_encode([
            'success' => $result['http_code'] < 400,
            'http_code' => $result['http_code'],
            'sent' => $updateData,
            'response_brands' => $result['data']['brands'] ?? 'NO DEVUELVE'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'full') {
        // Ver respuesta completa
        $result = wcRequest('/products/' . $productId);
        echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
