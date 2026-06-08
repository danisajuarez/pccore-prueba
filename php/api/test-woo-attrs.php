<?php
/**
 * Test: Ver atributos de WooCommerce
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

$action = $_GET['action'] ?? 'product';
$productId = $_GET['id'] ?? '21885';

try {
    if ($action === 'product') {
        // Ver atributos actuales del producto
        $result = wcRequest('/products/' . $productId);

        echo json_encode([
            'success' => true,
            'product_id' => $productId,
            'name' => $result['data']['name'] ?? null,
            'attributes' => $result['data']['attributes'] ?? [],
            'raw_response' => $result
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'global_attrs') {
        // Ver atributos globales de WooCommerce
        $result = wcRequest('/products/attributes');

        echo json_encode([
            'success' => true,
            'global_attributes' => $result['data']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'update_marca') {
        // Intentar actualizar marca
        $marca = $_GET['marca'] ?? 'TEST MARCA';

        $updateData = [
            'attributes' => [
                [
                    'name' => 'Marca',
                    'options' => [$marca],
                    'visible' => true,
                    'variation' => false
                ]
            ]
        ];

        $result = wcRequest('/products/' . $productId, 'PUT', $updateData);

        echo json_encode([
            'success' => $result['http_code'] < 400,
            'http_code' => $result['http_code'],
            'sent_data' => $updateData,
            'response_attributes' => $result['data']['attributes'] ?? [],
            'full_response' => $result['data']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
