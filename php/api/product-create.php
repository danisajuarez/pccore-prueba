<?php
/**
 * API: Crear producto nuevo en WooCommerce
 * Soporta dos formatos de input:
 * 1. Formato original: name, regular_price, stock_quantity
 * 2. Formato nuevo (desde ML): nombre, precio, stock, imagenes[]
 */

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}
checkAuth();

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Normalizar campos (soportar ambos formatos)
if (isset($input['nombre']) && !isset($input['name'])) {
    $input['name'] = $input['nombre'];
}
if (isset($input['precio']) && !isset($input['regular_price'])) {
    $input['regular_price'] = $input['precio'];
}
if (isset($input['stock']) && !isset($input['stock_quantity'])) {
    $input['stock_quantity'] = $input['stock'];
}
if (isset($input['descripcion']) && !isset($input['description'])) {
    $input['description'] = $input['descripcion'];
}

// Validaciones
$errores = [];

// SKU
if (empty($input['sku'])) {
    $errores[] = "El campo 'sku' es requerido";
}

// Nombre
if (empty($input['name'])) {
    $errores[] = "El campo 'name' es requerido";
}

// Precio
if (!isset($input['regular_price'])) {
    $errores[] = "El campo 'precio' es requerido";
} elseif (!is_numeric($input['regular_price']) || floatval($input['regular_price']) <= 0) {
    $errores[] = "El campo 'precio' debe ser numérico y mayor a 0";
}

// Stock (opcional, default 0)
if (!isset($input['stock_quantity'])) {
    $input['stock_quantity'] = 0;
} elseif (!is_numeric($input['stock_quantity']) || intval($input['stock_quantity']) < 0) {
    $errores[] = "El campo 'stock' debe ser un entero >= 0";
}

// Atributos (opcional pero si viene debe ser array válido)
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

// Preparar datos para WooCommerce
$nombre = trim($input['name']);
$productData = [
    'sku' => trim($input['sku']),
    'name' => $nombre,
    'short_description' => !empty($input['short_description']) ? $input['short_description'] : '',
    'description' => !empty($input['description']) ? $input['description'] : $nombre,
    'regular_price' => strval($input['regular_price']),
    'stock_quantity' => intval($input['stock_quantity']),
    'manage_stock' => true,
    'status' => 'publish',
    'type' => 'simple'
];

// Categoría
if (!empty($input['categoria_id'])) {
    $productData['categories'] = [
        ['id' => (int) $input['categoria_id']]
    ];
}

// Dimensiones
if (!empty($input['peso'])) {
    $productData['weight'] = strval($input['peso']);
}
if (!empty($input['alto']) || !empty($input['ancho']) || !empty($input['largo'])) {
    $productData['dimensions'] = [
        'length' => strval($input['largo'] ?? ''),
        'width' => strval($input['ancho'] ?? ''),
        'height' => strval($input['alto'] ?? '')
    ];
}

// Imágenes (URLs de ML u otras fuentes)
if (!empty($input['imagenes']) && is_array($input['imagenes'])) {
    $productData['images'] = [];
    foreach ($input['imagenes'] as $index => $url) {
        if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            $productData['images'][] = [
                'src' => $url,
                'position' => $index
            ];
        }
    }
}

// Procesar atributos si vienen
if (!empty($input['atributos'])) {
    $attributes = [];
    foreach ($input['atributos'] as $attr) {
        $attributes[] = [
            'name' => $attr['nombre'],
            'options' => [strval($attr['valor'])],
            'visible' => true,
            'variation' => false
        ];
    }
    $productData['attributes'] = $attributes;
}

try {
    $response = wcRequest('/products', 'POST', $productData);

    // ========================================
    // MARCAR COMO PUBLICADO EN SIGE (art_articuloweb = 'S')
    // ========================================
    if (!empty($response['id'])) {
        try {
            $dbService = getSigeConnection();
            $db = $dbService->getConnection();
            $sku = trim($input['sku']);
            $sqlUpdate = "UPDATE sige_art_articulo SET art_articuloweb = 'S' WHERE TRIM(ART_IDArticulo) = ?";
            $stmtUpdate = $db->prepare($sqlUpdate);
            $stmtUpdate->bind_param("s", $sku);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        } catch (Exception $e) {
            // Log error pero no fallar la respuesta - el producto ya se publicó
            error_log("Error actualizando art_articuloweb para SKU {$input['sku']}: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'product' => [
            'id' => $response['id'],
            'sku' => $response['sku'],
            'name' => $response['name'],
            'status' => $response['status'],
            'permalink' => $response['permalink']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
