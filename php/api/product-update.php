<?php
require_once __DIR__ . '/../config.php';
checkAuth();

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
    if (!is_numeric($input['regular_price']) || floatval($input['regular_price']) <= 0) {
        $errores[] = "El campo 'regular_price' debe ser numérico y mayor a 0";
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

if (isset($input['stock_quantity'])) {
    $productData['stock_quantity'] = intval($input['stock_quantity']);
    $productData['manage_stock'] = true;
}

if (isset($input['status'])) {
    $productData['status'] = $input['status'];
}

// Procesar atributos si vienen
if (isset($input['atributos'])) {
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

// Verificar que hay algo que actualizar
if (empty($productData)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No hay campos para actualizar']);
    exit();
}

$productId = intval($input['id']);

try {
    $response = wcRequest('/products/' . $productId, 'PUT', $productData);

    echo json_encode([
        'success' => true,
        'product' => [
            'id' => $response['id'],
            'sku' => $response['sku'],
            'name' => $response['name'],
            'status' => $response['status'],
            'regular_price' => $response['regular_price'],
            'stock_quantity' => $response['stock_quantity']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
