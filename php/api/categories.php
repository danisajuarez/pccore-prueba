<?php
/**
 * API: Gestión de categorías de WooCommerce
 *
 * Permite listar, buscar y asignar categorías
 */

require_once __DIR__ . '/../config.php';
checkAuth();

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
