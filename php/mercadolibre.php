<?php
/**
 * Configuración y funciones para Mercado Libre
 */

// Credenciales de Mercado Libre
define('ML_APP_ID', '828139284413193');
define('ML_CLIENT_SECRET', 'zkXFOW1IOODosHBEkeJmjBKLCzG9AFq2');
define('ML_SITE', 'MLA'); // Argentina

// Archivo para guardar el token
define('ML_TOKEN_FILE', __DIR__ . '/ml_token.json');

/**
 * Obtener access token de Mercado Libre
 * Usa client_credentials grant type
 */
function getMLAccessToken() {
    // Verificar si hay token guardado y vigente
    if (file_exists(ML_TOKEN_FILE)) {
        $tokenData = json_decode(file_get_contents(ML_TOKEN_FILE), true);
        if ($tokenData && isset($tokenData['expires_at']) && time() < $tokenData['expires_at']) {
            return $tokenData['access_token'];
        }
    }

    // Obtener nuevo token
    $tokenUrl = 'https://api.mercadolibre.com/oauth/token';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => ML_APP_ID,
        'client_secret' => ML_CLIENT_SECRET
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Error obteniendo token de ML: $response");
    }

    $tokenData = json_decode($response, true);

    // Guardar token con timestamp de expiración
    $tokenData['expires_at'] = time() + $tokenData['expires_in'] - 300; // 5 min antes de expirar
    file_put_contents(ML_TOKEN_FILE, json_encode($tokenData));

    return $tokenData['access_token'];
}

/**
 * Hacer request a la API de Mercado Libre
 */
function mlRequest($endpoint, $params = []) {
    $accessToken = getMLAccessToken();

    $url = 'https://api.mercadolibre.com' . $endpoint;

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("CURL Error: $error");
    }

    return [
        'http_code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

/**
 * Buscar imágenes de producto en el catálogo de Mercado Libre
 * @param string $query - SKU, Part Number o nombre del producto
 * @return array - Array de URLs de imágenes o array vacío si no encuentra
 */
function buscarImagenesML($query) {
    try {
        $result = mlRequest('/products/search', [
            'q' => $query,
            'site_id' => ML_SITE,
            'limit' => 5
        ]);

        if ($result['http_code'] !== 200 || empty($result['data']['results'])) {
            return [];
        }

        // Tomar el primer resultado que tenga imágenes
        foreach ($result['data']['results'] as $producto) {
            if (!empty($producto['pictures'])) {
                $imagenes = [];
                foreach ($producto['pictures'] as $pic) {
                    $imagenes[] = [
                        'url' => $pic['url'],
                        'id' => $pic['id'],
                        'width' => $pic['max_width'] ?? null,
                        'height' => $pic['max_height'] ?? null
                    ];
                }
                return [
                    'producto_ml' => [
                        'id' => $producto['id'],
                        'nombre' => $producto['name'],
                        'atributos' => array_map(function($attr) {
                            return ['nombre' => $attr['name'], 'valor' => $attr['value_name']];
                        }, $producto['attributes'] ?? [])
                    ],
                    'imagenes' => $imagenes
                ];
            }
        }

        return [];
    } catch (Exception $e) {
        error_log("Error buscando imágenes en ML: " . $e->getMessage());
        return [];
    }
}

/**
 * Buscar imágenes con estrategia de fallback:
 * 1. Buscar por Part Number (más específico)
 * 2. Si no encuentra, buscar por SKU (solo si parece un código de producto)
 * 3. Si no encuentra, buscar por nombre
 */
function buscarImagenesConFallback($sku, $partNumber = null, $nombre = null) {
    // 1. Intentar por Part Number primero (más específico)
    if ($partNumber && strlen($partNumber) >= 3) {
        $resultado = buscarImagenesML($partNumber);
        if (!empty($resultado['imagenes'])) {
            // Verificar que el producto encontrado sea relevante
            if (esProductoRelevante($resultado['producto_ml']['nombre'], $nombre)) {
                $resultado['encontrado_por'] = 'Part Number';
                return $resultado;
            }
        }
    }

    // 2. Intentar por SKU solo si parece un código real (no solo números)
    if ($sku && (strlen($sku) >= 5 || !is_numeric($sku))) {
        $resultado = buscarImagenesML($sku);
        if (!empty($resultado['imagenes'])) {
            if (esProductoRelevante($resultado['producto_ml']['nombre'], $nombre)) {
                $resultado['encontrado_por'] = 'SKU';
                return $resultado;
            }
        }
    }

    // 3. Intentar por nombre del producto (más amplio pero menos preciso)
    if ($nombre) {
        // Limpiar nombre para mejor búsqueda
        $nombreLimpio = limpiarNombreProducto($nombre);
        if ($nombreLimpio) {
            $resultado = buscarImagenesML($nombreLimpio);
            if (!empty($resultado['imagenes'])) {
                $resultado['encontrado_por'] = 'Nombre';
                return $resultado;
            }
        }
    }

    return ['imagenes' => [], 'encontrado_por' => null];
}

/**
 * Limpiar nombre de producto para mejor búsqueda
 * Elimina caracteres especiales y palabras innecesarias
 */
function limpiarNombreProducto($nombre) {
    // Eliminar caracteres especiales
    $nombre = preg_replace('/[^\w\s\-]/u', ' ', $nombre);
    // Eliminar espacios múltiples
    $nombre = preg_replace('/\s+/', ' ', $nombre);
    // Eliminar guiones solos
    $nombre = str_replace(' - ', ' ', $nombre);
    return trim($nombre);
}

/**
 * Verificar si el producto encontrado es relevante para lo que buscamos
 * Compara palabras clave entre el producto encontrado y el original
 */
function esProductoRelevante($nombreEncontrado, $nombreOriginal) {
    if (empty($nombreOriginal)) return true;

    $nombreEncontrado = strtolower($nombreEncontrado);
    $nombreOriginal = strtolower($nombreOriginal);

    // Extraer palabras clave (más de 2 caracteres)
    $palabrasOriginal = array_filter(
        preg_split('/[\s\-_]+/', $nombreOriginal),
        function($p) { return strlen($p) > 2; }
    );

    // Verificar si al menos una palabra clave coincide
    foreach ($palabrasOriginal as $palabra) {
        if (strpos($nombreEncontrado, $palabra) !== false) {
            return true;
        }
    }

    // Buscar coincidencias de marca común
    $marcas = ['hp', 'epson', 'canon', 'brother', 'samsung', 'lg', 'lenovo', 'dell', 'logitech', 'kingston'];
    foreach ($marcas as $marca) {
        if (strpos($nombreOriginal, $marca) !== false && strpos($nombreEncontrado, $marca) !== false) {
            return true;
        }
    }

    return false;
}
