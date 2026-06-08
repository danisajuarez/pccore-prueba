<?php
/**
 * API: Actualizar producto en WooCommerce (Multi-tenant)
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

// Función wcRequest - lee credenciales de sesión (igual que product-search.php)
function wcRequest($endpoint, $method = 'GET', $data = null) {
    if (!isset($_SESSION['cliente_config'])) {
        throw new Exception("No hay sesión de cliente activa");
    }

    $config = $_SESSION['cliente_config'];

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
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!empty($curlError)) {
        throw new Exception("CURL Error: " . $curlError);
    }

    if ($httpCode >= 400) {
        throw new Exception("WooCommerce API error: $httpCode - $response");
    }

    return json_decode($response, true);
}

// Solo POST (o PUT)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Leer JSON del body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit();
}

// Validaciones
$errores = [];

// ID (obligatorio)
if (!isset($input['id'])) {
    $errores[] = "El campo 'id' es requerido";
} elseif (!is_numeric($input['id']) || intval($input['id']) <= 0) {
    $errores[] = "El campo 'id' debe ser un entero mayor a 0";
}

// Nombre (si viene)
if (isset($input['name']) && trim($input['name']) === '') {
    $errores[] = "El campo 'name' no puede estar vacío";
}

// SKU (si viene)
if (isset($input['sku']) && trim($input['sku']) === '') {
    $errores[] = "El campo 'sku' no puede estar vacío";
}

// Precio (si viene)
if (isset($input['regular_price'])) {
    if (!is_numeric($input['regular_price']) || floatval($input['regular_price']) < 0) {
        $errores[] = "El campo 'regular_price' debe ser numérico y >= 0";
    }
}

// Stock (si viene)
if (isset($input['stock_quantity'])) {
    if (!is_numeric($input['stock_quantity']) || intval($input['stock_quantity']) < 0) {
        $errores[] = "El campo 'stock_quantity' debe ser un entero >= 0";
    }
}

// Status (si viene)
if (isset($input['status'])) {
    if (!in_array($input['status'], ['publish', 'draft'])) {
        $errores[] = "El campo 'status' solo puede ser 'publish' o 'draft'";
    }
}

// Atributos (si viene)
if (isset($input['atributos'])) {
    if (!is_array($input['atributos'])) {
        $errores[] = "El campo 'atributos' debe ser un array";
    } else {
        foreach ($input['atributos'] as $i => $attr) {
            if (empty($attr['nombre']) || !isset($attr['valor'])) {
                $errores[] = "Atributo en posición $i: debe tener 'nombre' y 'valor'";
            }
        }
    }
}

// Si hay errores, retornar
if (!empty($errores)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errores]);
    exit();
}

// Preparar datos para WooCommerce (solo los campos que vienen)
$productData = [];

if (isset($input['sku'])) {
    $productData['sku'] = trim($input['sku']);
}

if (isset($input['name'])) {
    $productData['name'] = trim($input['name']);
}

if (isset($input['short_description'])) {
    $productData['short_description'] = $input['short_description'];
}

if (isset($input['description'])) {
    $productData['description'] = $input['description'];
}

if (isset($input['regular_price'])) {
    $productData['regular_price'] = strval($input['regular_price']);
}

// Precio sin IVA (meta field)
if (isset($input['precio_sin_iva'])) {
    if (!isset($productData['meta_data'])) {
        $productData['meta_data'] = [];
    }
    $productData['meta_data'][] = [
        'key' => '_precio_sin_iva',
        'value' => strval($input['precio_sin_iva'])
    ];
}

if (isset($input['stock_quantity'])) {
    $stockQty = intval($input['stock_quantity']);
    $productData['stock_quantity'] = $stockQty;
    $productData['stock_status'] = $stockQty > 0 ? 'instock' : 'outofstock';
    $productData['manage_stock'] = true;
}

if (isset($input['status'])) {
    $productData['status'] = $input['status'];
}

// Siempre asegurar que el producto sea visible en el catálogo
// Esto corrige productos que quedaron con catalog_visibility = 'search'
$productData['catalog_visibility'] = 'visible';

// Procesar atributos si vienen (como atributos personalizados, no globales)
$brandToSync = null;
if (isset($input['atributos'])) {
    $attributes = [];
    foreach ($input['atributos'] as $attr) {
        if (strtolower($attr['nombre']) === 'marca') {
            $brandToSync = trim($attr['valor']);
            continue;
        }
        $attributes[] = [
            'name' => trim($attr['nombre']),
            'options' => [strval($attr['valor'])],
            'visible' => true,
            'variation' => false
        ];
    }
    $productData['attributes'] = $attributes;
    // Forzar que el tema Electro muestre la pestaña Specification
    if (!isset($productData['meta_data'])) {
        $productData['meta_data'] = [];
    }
    $productData['meta_data'][] = ['key' => '_specifications_display_attributes', 'value' => 'yes'];
}

// Peso (si viene)
if (isset($input['weight']) && $input['weight'] > 0) {
    $productData['weight'] = strval($input['weight']);
}

// Dimensiones (si vienen)
$dimensions = [];
if (isset($input['alto']) && $input['alto'] > 0) {
    $dimensions['height'] = strval($input['alto']);
}
if (isset($input['ancho']) && $input['ancho'] > 0) {
    $dimensions['width'] = strval($input['ancho']);
}
if (isset($input['profundidad']) && $input['profundidad'] > 0) {
    $dimensions['length'] = strval($input['profundidad']);
}
if (!empty($dimensions)) {
    $productData['dimensions'] = $dimensions;
}

// Procesar categorías si vienen
$categoryIds = [];
if (isset($input['categoria']) || isset($input['supracategoria'])) {
    // Buscar o crear supracategoría
    $supracategoriaId = null;
    if (!empty($input['supracategoria'])) {
        $supracategoriaId = buscarOCrearCategoria(trim($input['supracategoria']), 0);
    }

    // Buscar o crear categoría (como hija de supracategoría)
    if (!empty($input['categoria'])) {
        $parentId = $supracategoriaId ?? 0;
        $categoriaId = buscarOCrearCategoria(trim($input['categoria']), $parentId);
        if ($categoriaId) {
            $categoryIds[] = ['id' => $categoriaId];
        }
    } elseif ($supracategoriaId) {
        // Si no hay categoría pero sí supracategoría, usar la supra
        $categoryIds[] = ['id' => $supracategoriaId];
    }

    if (!empty($categoryIds)) {
        $productData['categories'] = $categoryIds;
    }
}

// Verificar que hay algo que actualizar
if (empty($productData)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No hay campos para actualizar']);
    exit();
}

$productId = intval($input['id']);

try {
    $response = wcRequest('/products/' . $productId, 'PUT', $productData);

    $brandSynced = null;

    // Si hay marca para sincronizar con el plugin de brands
    if ($brandToSync) {
        $brandSynced = syncBrandToWooCommerce($productId, $brandToSync);
    }

    $brandFailed = $brandSynced && $brandSynced['success'] === false;

    echo json_encode([
        'success' => true,
        'brand_sync_warning' => $brandFailed ? $brandSynced['error'] : null,
        'debug_attributes_sent' => $productData['attributes'] ?? [],
        'debug_attributes_woo' => $response['attributes'] ?? [],
        'debug_categories_sent' => $productData['categories'] ?? [],
        'debug_categories_woo' => $response['categories'] ?? [],
        'product' => [
            'id' => $response['id'],
            'sku' => $response['sku'],
            'name' => $response['name'],
            'status' => $response['status'],
            'regular_price' => $response['regular_price'],
            'stock_quantity' => $response['stock_quantity'],
            'categories' => $response['categories'] ?? []
        ],
        'brand_sync' => $brandSynced
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Buscar o crear una categoría en WooCommerce
 * @param string $nombre Nombre de la categoría
 * @param int $parentId ID de la categoría padre (0 = raíz)
 * @return int|null ID de la categoría
 */
function buscarOCrearCategoria($nombre, $parentId = 0) {
    if (empty($nombre)) return null;

    $nombre = trim($nombre);

    // Buscar categoría existente por nombre
    $categorias = wcRequest('/products/categories?search=' . urlencode($nombre) . '&per_page=100');

    // 1. Primero buscar coincidencia exacta con mismo padre
    foreach ($categorias as $cat) {
        if (strcasecmp($cat['name'], $nombre) === 0 && $cat['parent'] == $parentId) {
            return $cat['id'];
        }
    }

    // 2. Reusar cualquier categoría con ese nombre exacto para evitar duplicados
    foreach ($categorias as $cat) {
        if (strcasecmp($cat['name'], $nombre) === 0) {
            return $cat['id'];
        }
    }

    // 3. No existe — crear nueva
    $newCat = wcRequest('/products/categories', 'POST', [
        'name' => $nombre,
        'parent' => $parentId
    ]);

    return $newCat['id'] ?? null;
}

/**
 * Buscar o crear un atributo global usando cache ya cargado
 * Si no está en el cache, lo crea y lo agrega al cache
 */
function buscarOCrearAtributoGlobalConCache(string $nombre, array &$cache): ?int {
    try {
        foreach ($cache as $atr) {
            if (strcasecmp($atr['name'], $nombre) === 0) {
                return (int) $atr['id'];
            }
        }
        // No existe — crear
        $nuevo = wcRequest('/products/attributes', 'POST', [
            'name' => $nombre,
            'type' => 'select',
            'has_archives' => false
        ]);
        if (isset($nuevo['id'])) {
            $cache[] = $nuevo; // agregar al cache para evitar duplicados
            return (int) $nuevo['id'];
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Sincronizar marca con el plugin de brands de WooCommerce
 * 1. Busca si la marca existe
 * 2. Si no existe, la crea
 * 3. Asigna la marca al producto
 */
function syncBrandToWooCommerce($productId, $brandName) {
    try {
        // 1. Buscar si la marca ya existe
        $brands = wcRequest('/products/brands?search=' . urlencode($brandName));

        $brandId = null;
        $wasCreated = false;

        // Buscar coincidencia exacta (case insensitive)
        if (!empty($brands)) {
            foreach ($brands as $brand) {
                if (strtolower($brand['name']) === strtolower($brandName)) {
                    $brandId = $brand['id'];
                    break;
                }
            }
        }

        // 2. Si no existe, crear la marca
        if (!$brandId) {
            $newBrand = wcRequest('/products/brands', 'POST', [
                'name' => $brandName
            ]);

            if (isset($newBrand['id'])) {
                $brandId = $newBrand['id'];
                $wasCreated = true;
            }
        }

        // 3. Asignar la marca al producto
        if ($brandId) {
            wcRequest('/products/' . $productId, 'PUT', [
                'brands' => [['id' => $brandId]]
            ]);

            return [
                'success' => true,
                'brand_id' => $brandId,
                'brand_name' => $brandName,
                'action' => $wasCreated ? 'created_and_assigned' : 'assigned'
            ];
        }

        return ['success' => false, 'error' => 'No se pudo crear/encontrar la marca'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
