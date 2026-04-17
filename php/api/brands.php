<?php
/**
 * API: Gestión de marcas de productos
 *
 * Detecta si hay un sistema de marcas configurado (plugin o atributo)
 */

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}

$action = $_GET['action'] ?? 'detect';

try {
    switch ($action) {
        case 'detect':
            // Intentar detectar sistema de marcas
            $brandsSystem = null;
            $brands = [];

            // Método 1: Buscar taxonomía de marcas (plugins comunes)
            try {
                // Algunos plugins usan /products/brands
                $brandsResponse = wcRequest('/products/brands?per_page=10');
                if (!empty($brandsResponse) && !isset($brandsResponse['code'])) {
                    $brandsSystem = 'brands_taxonomy';
                    $brands = $brandsResponse;
                }
            } catch (Exception $e) {
                // No es un error, solo significa que no hay taxonomía de brands
            }

            // Método 2: Buscar atributo "Marca" o "Brand"
            if (!$brandsSystem) {
                try {
                    $attributes = wcRequest('/products/attributes');
                    foreach ($attributes as $attr) {
                        $attrName = strtolower($attr['name']);
                        if ($attrName === 'marca' || $attrName === 'brand' || $attrName === 'marcas') {
                            $brandsSystem = 'attribute';

                            // Obtener términos del atributo
                            $terms = wcRequest("/products/attributes/{$attr['id']}/terms?per_page=100");
                            $brands = array_map(function($term) {
                                return [
                                    'id' => $term['id'],
                                    'name' => $term['name'],
                                    'slug' => $term['slug'],
                                    'count' => $term['count']
                                ];
                            }, $terms);

                            $brands['attribute_id'] = $attr['id'];
                            $brands['attribute_name'] = $attr['name'];
                            break;
                        }
                    }
                } catch (Exception $e) {
                    // Ignorar error
                }
            }

            echo json_encode([
                'success' => true,
                'has_brands' => $brandsSystem !== null,
                'system' => $brandsSystem,
                'brands' => $brands,
                'message' => $brandsSystem ?
                    "Sistema de marcas detectado: $brandsSystem" :
                    'No se detectó sistema de marcas. Podés crear un atributo "Marca" en WooCommerce.'
            ]);
            break;

        case 'list':
            // Listar marcas (intentar todos los métodos)
            $brands = [];

            // Intentar como taxonomía
            try {
                $brandsResponse = wcRequest('/products/brands?per_page=100&orderby=name&order=asc');
                if (!empty($brandsResponse) && !isset($brandsResponse['code'])) {
                    $brands = array_map(function($brand) {
                        return [
                            'id' => $brand['id'],
                            'name' => $brand['name'],
                            'slug' => $brand['slug'],
                            'count' => $brand['count'] ?? 0
                        ];
                    }, $brandsResponse);
                }
            } catch (Exception $e) {
                // Intentar como atributo
                $attributes = wcRequest('/products/attributes');
                foreach ($attributes as $attr) {
                    $attrName = strtolower($attr['name']);
                    if ($attrName === 'marca' || $attrName === 'brand') {
                        $terms = wcRequest("/products/attributes/{$attr['id']}/terms?per_page=100");
                        $brands = array_map(function($term) {
                            return [
                                'id' => $term['id'],
                                'name' => $term['name'],
                                'slug' => $term['slug'],
                                'count' => $term['count'] ?? 0
                            ];
                        }, $terms);
                        break;
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'brands' => $brands,
                'total' => count($brands)
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
