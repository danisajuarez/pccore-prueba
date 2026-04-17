<?php
/**
 * API: Gestión de categorías de WooCommerce (Multi-tenant)
 *
 * Permite listar, buscar y asignar categorías
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

// Función wcRequest - LEE DE SESIÓN (no de constantes vacías)
function wcRequest($endpoint, $method = 'GET', $data = null) {
    // Obtener credenciales directamente de la sesión
    if (!isset($_SESSION['cliente_config'])) {
        throw new Exception("No hay sesión de cliente activa");
    }
    
    $config = $_SESSION['cliente_config'];
    
    // Validar que tenemos credenciales de WooCommerce
    if (empty($config['wc_url']) || empty($config['wc_key']) || empty($config['wc_secret'])) {
        throw new Exception("Credenciales de WooCommerce incompletas en la sesión");
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

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Listar todas las categorías disponibles en WooCommerce
            $categories = wcRequest('/products/categories?per_page=100&orderby=name&order=asc');

            $categoriesFormatted = array_map(function($cat) {
                return [
                    'id' => $cat['id'],
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'parent' => $cat['parent'],
                    'count' => $cat['count']
                ];
            }, $categories);

            echo json_encode([
                'success' => true,
                'categories' => $categoriesFormatted,
                'total' => count($categoriesFormatted)
            ]);
            break;

        case 'list_tags':
            // Listar todas las etiquetas disponibles en WooCommerce
            $tags = wcRequest('/products/tags?per_page=100&orderby=name&order=asc');

            $tagsFormatted = array_map(function($tag) {
                return [
                    'id' => $tag['id'],
                    'name' => $tag['name'],
                    'slug' => $tag['slug'],
                    'count' => $tag['count']
                ];
            }, $tags);

            echo json_encode([
                'success' => true,
                'tags' => $tagsFormatted,
                'total' => count($tagsFormatted)
            ]);
            break;

        case 'get_product_categories':
            // Obtener categorías de un producto específico
            $productId = $_GET['product_id'] ?? null;

            if (!$productId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'product_id requerido']);
                exit();
            }

            $product = wcRequest("/products/{$productId}");

            if (!empty($product['categories'])) {
                echo json_encode([
                    'success' => true,
                    'categories' => $product['categories']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'categories' => []
                ]);
            }
            break;

        case 'get_product_tags':
            // Obtener tags de un producto específico
            $productId = $_GET['product_id'] ?? null;

            if (!$productId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'product_id requerido']);
                exit();
            }

            $product = wcRequest("/products/{$productId}");

            if (!empty($product['tags'])) {
                echo json_encode([
                    'success' => true,
                    'tags' => $product['tags']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'tags' => []
                ]);
            }
            break;

        case 'assign':
            // Asignar categorías a un producto (POST)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                exit();
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $productId = $input['product_id'] ?? null;
            $categoryIds = $input['category_ids'] ?? [];

            if (!$productId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'product_id requerido']);
                exit();
            }

            if (!is_array($categoryIds)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'category_ids debe ser un array']);
                exit();
            }

            // Formatear categorías para WooCommerce
            $categories = array_map(function($id) {
                return ['id' => (int)$id];
            }, $categoryIds);

            // Actualizar producto
            $response = wcRequest("/products/{$productId}", 'PUT', [
                'categories' => $categories
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Categorías actualizadas',
                'categories' => $response['categories'] ?? []
            ]);
            break;

        case 'assign_tags':
            // Asignar tags a un producto (POST)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                exit();
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $productId = $input['product_id'] ?? null;
            $tagIds = $input['tag_ids'] ?? [];

            if (!$productId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'product_id requerido']);
                exit();
            }

            if (!is_array($tagIds)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'tag_ids debe ser un array']);
                exit();
            }

            // Formatear tags para WooCommerce
            $tags = array_map(function($id) {
                return ['id' => (int)$id];
            }, $tagIds);

            // Actualizar producto
            $response = wcRequest("/products/{$productId}", 'PUT', [
                'tags' => $tags
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Etiquetas actualizadas',
                'tags' => $response['tags'] ?? []
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
