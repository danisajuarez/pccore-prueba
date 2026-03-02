<?php
require_once __DIR__ . '/../config.php';
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

// Descripción corta (opcional, usa nombre si no viene)
// Descripción larga (opcional, usa nombre si no viene)

// Precio
if (!isset($input['regular_price'])) {
    $errores[] = "El campo 'regular_price' es requerido";
} elseif (!is_numeric($input['regular_price']) || floatval($input['regular_price']) <= 0) {
    $errores[] = "El campo 'regular_price' debe ser numérico y mayor a 0";
}

// Stock
if (!isset($input['stock_quantity'])) {
    $errores[] = "El campo 'stock_quantity' es requerido";
} elseif (!is_numeric($input['stock_quantity']) || intval($input['stock_quantity']) < 0) {
    $errores[] = "El campo 'stock_quantity' debe ser un entero >= 0";
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
    'short_description' => !empty($input['short_description']) ? $input['short_description'] : $nombre,
    'description' => !empty($input['description']) ? $input['description'] : $nombre,
    'regular_price' => strval($input['regular_price']),
    'stock_quantity' => intval($input['stock_quantity']),
    'manage_stock' => true,
    'status' => 'publish',
    'type' => 'simple'
];

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
