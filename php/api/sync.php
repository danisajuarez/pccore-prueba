<?php
/**
 * API: Sincronizar precio/stock de producto (Multi-tenant)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

// Requiere autenticación por sesión
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Validar API Key
$headers = getallheaders();
$apiKey = $headers['X-Api-Key'] ?? $headers['x-api-key'] ?? $_GET['api_key'] ?? '';
$expectedKey = getClienteId() . '-sync-2024';

if ($apiKey !== $expectedKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'API Key inválida']);
    exit;
}

// Función wcRequest
function wcRequest($endpoint, $method = 'GET', $data = null) {
    $url = WC_BASE_URL . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . WC_CONSUMER_KEY . '&consumer_secret=' . WC_CONSUMER_SECRET;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, $method === 'GET' ? 30 : 120);

    if ($method === 'PUT' || $method === 'POST') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("WooCommerce API error: $httpCode - $response");
    }

    return json_decode($response, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$sku = $input['sku'] ?? '';

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU requerido']);
    exit();
}

// Obtener porcentaje de IVA del producto para calcular precio sin impuestos
$porcIva = 0;
if (isset($input['regular_price'])) {
    try {
        $dbService = getSigeConnection();
        $db = $dbService->getConnection();
        $stmtIva = $db->prepare("SELECT ART_PorcIVARI FROM sige_art_articulo WHERE TRIM(ART_IDArticulo) = ?");
        $stmtIva->bind_param("s", $sku);
        $stmtIva->execute();
        $resultIva = $stmtIva->get_result();
        if ($row = $resultIva->fetch_assoc()) {
            $porcIva = (float)($row['ART_PorcIVARI'] ?? 0);
        }
        $db->close();
    } catch (Exception $e) {
        // Si falla, continuar con precio original
    }
}

try {
    // 1. Buscar producto por SKU
    $products = wcRequest('/products?sku=' . urlencode($sku));

    $product = null;
    foreach ($products as $p) {
        if ($p['sku'] === $sku) {
            $product = $p;
            break;
        }
    }

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'sku' => $sku, 'error' => "Producto con SKU \"$sku\" no encontrado"]);
        exit();
    }

    // 2. Construir payload AGRESIVO
    $payload = [
        'manage_stock' => true
    ];

    // Si viene cantidad de stock
    if (isset($input['stock_quantity'])) {
        $payload['stock_quantity'] = (int) $input['stock_quantity'];
        $payload['stock_status'] = ($payload['stock_quantity'] > 0) ? 'instock' : 'outofstock';
    }

    // Si viene precio regular (Aquí está la magia)
    if (isset($input['regular_price'])) {
        $precioOriginal = (float) $input['regular_price'];

        // Siempre calcular precio sin IVA
        if ($porcIva > 0) {
            $precioSinIva = $precioOriginal / (1 + ($porcIva / 100));
        } else {
            $precioSinIva = $precioOriginal;
        }

        $new_price = number_format($precioSinIva, 2, '.', '');
        $payload['regular_price'] = $new_price;
        $payload['sale_price'] = ''; // BORRAMOS cualquier oferta para que no pise el precio nuevo

        // REFUERZO: Actualizamos también los meta_data que tu web parece usar
        $payload['meta_data'] = [
            ['key' => '_price', 'value' => $new_price],
            ['key' => '_regular_price', 'value' => $new_price],
            ['key' => '_sale_price', 'value' => ''],
            ['key' => '_price_no_taxes', 'value' => $new_price]
        ];
    }

    // 3. Actualizar producto en WooCommerce
    $response = wcRequest('/products/' . $product['id'], 'PUT', $payload);

    echo json_encode([
        'success' => true,
        'sku' => $sku,
        'product_id' => $product['id'],
        'message' => 'Producto actualizado y campos especiales limpiados',
        'debug_payload' => $payload // Para que veas qué mandamos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'sku' => $sku, 'error' => $e->getMessage()]);
}