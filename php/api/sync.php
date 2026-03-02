<?php
require_once __DIR__ . '/../config.php';
checkAuth();

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
        $db = getDbConnection();
        $stmtIva = $db->prepare("SELECT ART_PorcIVARI FROM sige_art_articulo WHERE ART_IDArticulo = ?");
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