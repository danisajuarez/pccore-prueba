<?php
/**
 * API: Diagnosticar y arreglar visibilidad de productos
 *
 * Productos que no aparecen en categorías pero sí en búsqueda
 * tienen catalog_visibility incorrecto (hidden/search en vez de visible)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

function wcRequest($endpoint, $method = 'GET', $data = null) {
    if (!isset($_SESSION['cliente_config'])) {
        throw new Exception("No hay sesión de cliente activa");
    }

    $config = $_SESSION['cliente_config'];

    if (empty($config['wc_url']) || empty($config['wc_key']) || empty($config['wc_secret'])) {
        throw new Exception("Credenciales de WooCommerce incompletas");
    }

    $url = $config['wc_url'] . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . urlencode($config['wc_key']) . '&consumer_secret=' . urlencode($config['wc_secret']);

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
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("CURL Error: $error");
    }

    if ($httpCode >= 400) {
        throw new Exception("WooCommerce API error: $httpCode - $response");
    }

    return json_decode($response, true);
}

$action = $_GET['action'] ?? 'diagnose';

try {
    if ($action === 'diagnose') {
        // Buscar productos con visibilidad incorrecta
        $problematicos = [];
        $page = 1;
        $perPage = 100;

        do {
            $products = wcRequest("/products?status=publish&per_page=$perPage&page=$page");

            foreach ($products as $product) {
                // catalog_visibility puede ser: visible, catalog, search, hidden
                // - visible: aparece en todo (correcto)
                // - catalog: solo en categorías, no en búsqueda
                // - search: solo en búsqueda, NO en categorías (PROBLEMA)
                // - hidden: no aparece en ninguno

                if ($product['catalog_visibility'] !== 'visible') {
                    $problematicos[] = [
                        'id' => $product['id'],
                        'sku' => $product['sku'],
                        'name' => $product['name'],
                        'catalog_visibility' => $product['catalog_visibility'],
                        'categories' => array_map(fn($c) => $c['name'], $product['categories'] ?? []),
                        'permalink' => $product['permalink']
                    ];
                }
            }

            $page++;
        } while (count($products) === $perPage && $page <= 10); // Máximo 1000 productos

        echo json_encode([
            'success' => true,
            'total_problematicos' => count($problematicos),
            'productos' => $problematicos,
            'fix_url' => '?action=fix' . (isset($_GET['sku']) ? '&sku=' . $_GET['sku'] : ''),
            'fix_all_url' => '?action=fix_all'
        ], JSON_PRETTY_PRINT);

    } elseif ($action === 'fix') {
        // Arreglar un producto específico por SKU o ID
        $sku = $_GET['sku'] ?? '';
        $id = $_GET['id'] ?? '';

        if (empty($sku) && empty($id)) {
            throw new Exception("Se requiere SKU o ID del producto");
        }

        $productId = $id;

        if (!empty($sku) && empty($id)) {
            // Buscar por SKU
            $products = wcRequest('/products?sku=' . urlencode($sku) . '&status=any');
            if (empty($products)) {
                throw new Exception("Producto con SKU '$sku' no encontrado");
            }
            $productId = $products[0]['id'];
        }

        // Obtener estado actual
        $before = wcRequest('/products/' . $productId);

        // Actualizar visibilidad
        $result = wcRequest('/products/' . $productId, 'PUT', [
            'catalog_visibility' => 'visible',
            'status' => 'publish'
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Producto actualizado',
            'product' => [
                'id' => $result['id'],
                'sku' => $result['sku'],
                'name' => $result['name'],
                'before' => $before['catalog_visibility'],
                'after' => $result['catalog_visibility'],
                'permalink' => $result['permalink']
            ]
        ], JSON_PRETTY_PRINT);

    } elseif ($action === 'fix_all') {
        // Arreglar TODOS los productos con visibilidad incorrecta
        $fixed = [];
        $errors = [];
        $page = 1;
        $perPage = 100;

        do {
            $products = wcRequest("/products?status=publish&per_page=$perPage&page=$page");

            foreach ($products as $product) {
                if ($product['catalog_visibility'] !== 'visible') {
                    try {
                        wcRequest('/products/' . $product['id'], 'PUT', [
                            'catalog_visibility' => 'visible'
                        ]);
                        $fixed[] = [
                            'id' => $product['id'],
                            'sku' => $product['sku'],
                            'before' => $product['catalog_visibility']
                        ];
                    } catch (Exception $e) {
                        $errors[] = [
                            'id' => $product['id'],
                            'sku' => $product['sku'],
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }

            $page++;
        } while (count($products) === $perPage && $page <= 10);

        echo json_encode([
            'success' => true,
            'fixed_count' => count($fixed),
            'error_count' => count($errors),
            'fixed' => $fixed,
            'errors' => $errors
        ], JSON_PRETTY_PRINT);

    } else {
        throw new Exception("Acción no válida. Usa: diagnose, fix, fix_all");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
