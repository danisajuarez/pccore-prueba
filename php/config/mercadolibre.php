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
 * @param string $nombreOriginal - Nombre original para validar relevancia
 * @param bool $validarEstricto - Si true, usa score. Si false (GTIN), acepta primer resultado
 * @return array - Array de URLs de imágenes o array vacío si no encuentra
 */
function buscarImagenesML($query, $nombreOriginal = null, $validarEstricto = true) {
    try {
        // Usar endpoint de CATÁLOGO (funciona, no está bloqueado)
        $result = mlRequest('/products/search', [
            'q' => $query,
            'site_id' => ML_SITE,
            'limit' => 15,
            'status' => 'active'
        ]);

        if ($result['http_code'] !== 200 || empty($result['data']['results'])) {
            return [];
        }

        $queryLower = mb_strtolower($query, 'UTF-8');
        $mejorCandidato = null;
        $mejorScore = 0;

        foreach ($result['data']['results'] as $producto) {
            // Debe tener imágenes
            if (empty($producto['pictures'])) {
                continue;
            }

            // Si NO es validación estricta (GTIN/EAN), aceptar primer resultado con imágenes
            if (!$validarEstricto) {
                $mejorCandidato = $producto;
                break;
            }

            $nombreProductoML = $producto['name'] ?? '';
            $nombreLower = mb_strtolower($nombreProductoML, 'UTF-8');

            // Verificar que no sea accesorio
            if ($nombreOriginal && !esProductoRelevante($nombreProductoML, $nombreOriginal)) {
                continue; // Es un accesorio → RECHAZAR
            }

            // Calcular score de similitud
            $score = 0;

            // Bonus si el query aparece exacto en el nombre ML
            if (strpos($nombreLower, $queryLower) !== false) {
                $score += 50;
            }

            // Calcular score por similitud con nombre original
            if ($nombreOriginal) {
                $score += calcularScore($nombreProductoML, $nombreOriginal);
            }

            // Actualizar mejor candidato
            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejorCandidato = $producto;
            }
        }

        // Requerir score mínimo de 30 para aceptar (relajado vs 40 anterior)
        if (!$mejorCandidato || ($validarEstricto && $mejorScore < 30)) {
            return [];
        }

        // Extraer imágenes del catálogo (ya vienen en el resultado)
        $imagenes = [];
        foreach ($mejorCandidato['pictures'] as $pic) {
            $url = $pic['url'] ?? null;
            if ($url) {
                // Asegurar que la URL está en UTF-8 válido
                if (!mb_check_encoding($url, 'UTF-8')) {
                    $url = mb_convert_encoding($url, 'UTF-8', 'UTF-8');
                }
                // Cambiar a máxima calidad
                $url = preg_replace('/-[A-Z]\./', '-O.', $url);
                $imagenes[] = [
                    'url' => $url,
                    'id' => $pic['id'] ?? null,
                    'width' => $pic['max_width'] ?? null,
                    'height' => $pic['max_height'] ?? null
                ];
            }
        }

        if (empty($imagenes)) {
            return [];
        }

        return [
            'producto_ml' => [
                'id' => $mejorCandidato['id'],
                'nombre' => $mejorCandidato['name'],
                'atributos' => array_map(function($attr) {
                    $nombre = $attr['name'] ?? '';
                    $valor = $attr['value_name'] ?? '';
                    // Asegurar UTF-8 válido en atributos
                    if (!mb_check_encoding($nombre, 'UTF-8')) {
                        $nombre = mb_convert_encoding($nombre, 'UTF-8', 'UTF-8');
                    }
                    if (!mb_check_encoding($valor, 'UTF-8')) {
                        $valor = mb_convert_encoding($valor, 'UTF-8', 'UTF-8');
                    }
                    return ['nombre' => $nombre, 'valor' => $valor];
                }, $mejorCandidato['attributes'] ?? [])
            ],
            'imagenes' => $imagenes
        ];

    } catch (Exception $e) {
        error_log("Error buscando imágenes en ML: " . $e->getMessage());
        return [];
    }
}

/**
 * Buscar imágenes con estrategia de fallback mejorada:
 * 1. GTIN/EAN (código de barras) - más preciso
 * 2. Part Number
 * 3. Marca + Modelo (extraído del nombre)
 * 4. SKU
 * 5. Nombre limpio (primeras palabras significativas)
 */
function buscarImagenesConFallback($sku, $partNumber = null, $nombre = null, $codigoBarras = null) {
    $resultado = null;

    // 1. Intentar por GTIN/EAN (código de barras)
    // NOTA: Validamos relevancia porque ML puede tener EANs incorrectos
    if ($codigoBarras && strlen($codigoBarras) >= 8 && is_numeric($codigoBarras)) {
        $resultado = buscarImagenesML($codigoBarras, $nombre, true);
        if (!empty($resultado['imagenes'])) {
            $resultado['encontrado_por'] = 'GTIN/EAN';
            return $resultado;
        }
    }

    // 2. Intentar por Part Number
    if ($partNumber && strlen($partNumber) >= 3) {
        $resultado = buscarImagenesML($partNumber, $nombre, true);
        if (!empty($resultado['imagenes'])) {
            $resultado['encontrado_por'] = 'Part Number';
            return $resultado;
        }
    }

    // 3. Intentar por Marca + Modelo (extraído del nombre)
    if ($nombre) {
        $marcaModelo = extraerMarcaModelo($nombre);
        if ($marcaModelo) {
            $resultado = buscarImagenesML($marcaModelo, $nombre, true);
            if (!empty($resultado['imagenes'])) {
                $resultado['encontrado_por'] = 'Marca+Modelo';
                return $resultado;
            }
        }
    }

    // 4. Intentar por SKU
    if ($sku && (strlen($sku) >= 5 || !is_numeric($sku))) {
        $resultado = buscarImagenesML($sku, $nombre, true);
        if (!empty($resultado['imagenes'])) {
            $resultado['encontrado_por'] = 'SKU';
            return $resultado;
        }
    }

    // 5. Intentar por nombre limpio (primeras palabras significativas)
    if ($nombre) {
        $nombreLimpio = extraerPalabrasClaveNombre($nombre);
        if ($nombreLimpio && strlen($nombreLimpio) >= 8) {
            $resultado = buscarImagenesML($nombreLimpio, $nombre, true);
            if (!empty($resultado['imagenes'])) {
                $resultado['encontrado_por'] = 'Nombre';
                return $resultado;
            }
        }
    }

    return ['imagenes' => [], 'encontrado_por' => null];
}

/**
 * Extraer Marca + Modelo del nombre del producto
 * Ej: "Monitor Samsung LS19D300 19 Pulgadas" → "Samsung LS19D300"
 */
function extraerMarcaModelo($nombre) {
    $marcas = [
        'samsung', 'lg', 'hp', 'dell', 'lenovo', 'asus', 'acer', 'msi',
        'gigabyte', 'intel', 'amd', 'nvidia', 'logitech', 'razer', 'corsair',
        'kingston', 'seagate', 'western digital', 'wd', 'sandisk', 'crucial',
        'epson', 'canon', 'brother', 'xerox', 'lexmark', 'ricoh',
        'tp-link', 'tplink', 'd-link', 'dlink', 'netgear', 'ubiquiti',
        'microsoft', 'apple', 'sony', 'philips', 'viewsonic', 'benq', 'aoc',
        'thermaltake', 'cooler master', 'nzxt', 'evga', 'zotac', 'pny',
        'hyperx', 'patriot', 'gskill', 'g.skill', 'team', 'adata', 'xpg',
        'redragon', 'genius', 'trust', 'anker', 'ugreen', 'orico',
        'asrock', 'biostar', 'ecs', 'foxconn', 'supermicro',
        'toshiba', 'hitachi', 'maxtor', 'transcend', 'silicon power',
        'netbook', 'pcbox', 'bangho', 'noblex', 'positivo', 'exo', 'kelyx'
    ];

    $nombreLower = mb_strtolower($nombre, 'UTF-8');
    $marcaEncontrada = null;

    // Buscar marca en el nombre
    foreach ($marcas as $marca) {
        if (strpos($nombreLower, $marca) !== false) {
            $marcaEncontrada = $marca;
            break;
        }
    }

    if (!$marcaEncontrada) {
        return null;
    }

    // Buscar modelo alfanumérico (ej: LS19D300, GTX1650, RX580, A400, K120)
    // Patrones: letras+números, números+letras, o combinaciones (mínimo 1 letra + 2 números)
    preg_match_all('/\b([A-Z]{1,5}[\-]?[0-9]{2,}[A-Z0-9]*|[0-9]{2,}[A-Z]{1,5}[A-Z0-9]*)\b/i', $nombre, $matches);

    if (!empty($matches[1])) {
        // Tomar el modelo más largo/específico
        $modelo = '';
        foreach ($matches[1] as $m) {
            if (strlen($m) > strlen($modelo)) {
                $modelo = $m;
            }
        }
        if ($modelo) {
            return ucfirst($marcaEncontrada) . ' ' . strtoupper($modelo);
        }
    }

    return null;
}

/**
 * Extraer palabras clave significativas del nombre
 * Elimina palabras genéricas y devuelve las más relevantes
 */
function extraerPalabrasClaveNombre($nombre) {
    // Palabras a ignorar (stopwords + medidas + genéricas)
    $ignorar = [
        'de', 'para', 'con', 'sin', 'the', 'and', 'or', 'in', 'on', 'at',
        'pulgadas', 'pulg', 'inch', 'mm', 'cm', 'mts', 'metros',
        'gb', 'tb', 'mb', 'mhz', 'ghz', 'watts', 'watt',
        'negro', 'blanco', 'gris', 'rojo', 'azul', 'verde', 'black', 'white',
        'nuevo', 'new', 'original', 'oem', 'bulk', 'box', 'retail',
        'unidad', 'unidades', 'pack', 'kit', 'set',
        'garantia', 'warranty', 'años', 'year', 'years',
        'compatible', 'universal', 'generico', 'generica'
    ];

    // Limpiar nombre
    $nombre = preg_replace('/[^\w\s\-]/u', ' ', $nombre);
    $nombre = preg_replace('/\s+/', ' ', trim($nombre));

    $palabras = explode(' ', mb_strtolower($nombre, 'UTF-8'));
    $palabrasClave = [];

    foreach ($palabras as $palabra) {
        // Ignorar palabras cortas, números solos, y stopwords
        if (strlen($palabra) < 3) continue;
        if (is_numeric($palabra)) continue;
        if (in_array($palabra, $ignorar)) continue;

        $palabrasClave[] = $palabra;

        // Máximo 4 palabras clave
        if (count($palabrasClave) >= 4) break;
    }

    return implode(' ', $palabrasClave);
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
 * Buscar datos completos de producto en ML (descripción, dimensiones, peso)
 * Cuando encuentra datos, los guarda directamente en SIGE para no buscar de nuevo
 * @param string $sku
 * @param string $partNumber
 * @param string $nombre
 * @return array - Datos del producto o array vacío
 */

function buscarDatosProductoML($sku, $partNumber = null, $nombre = null) {
    try {
        $queries = [];

        if ($partNumber && strlen($partNumber) >= 3) {
            $queries[] = ['query' => $partNumber, 'tipo' => 'Part Number'];
        }

        if ($sku && (strlen($sku) >= 5 || !is_numeric($sku))) {
            $queries[] = ['query' => $sku, 'tipo' => 'SKU'];
        }

        foreach ($queries as $q) {

            // =========================
            // 1. BUSCAR EN ITEMS (BIEN)
            // =========================
            $result = mlRequest('/sites/' . ML_SITE . '/search', [
                'q' => $q['query'],
                'limit' => 5
            ]);

            if ($result['http_code'] === 200 && !empty($result['data']['results'])) {

                $mejor = null;
                $mejorScore = 0;

                foreach ($result['data']['results'] as $item) {

                    if (!esProductoRelevante($item['title'], $nombre)) continue;

                    $score = calcularScore($item['title'], $nombre);
                    if (stripos($item['title'], $q['query']) !== false) $score += 20;

                    if ($score > $mejorScore) {
                        $mejorScore = $score;
                        $mejor = $item;
                    }
                }

                if ($mejor && $mejorScore > 40) {

                    $itemId = $mejor['id'];
                    

                    $datos = [
                        'encontrado' => true,
                        'encontrado_por' => $q['tipo'],
                        'ml_id' => $itemId,
                        'nombre_ml' => $mejor['title'],
                        'descripcion' => null,
                        'peso' => null,
                        'alto' => null,
                        'ancho' => null,
                        'profundidad' => null,
                        'atributos' => []
                    ];

                    $itemFull = mlRequest("/items/{$itemId}");

                    if ($itemFull['http_code'] === 200 && !empty($itemFull['data'])) {
                        $itemData = $itemFull['data'];

                        // Extraer dimensiones del shipping
                        if (!empty($itemData['shipping']['dimensions'])) {
                            $dims = $itemData['shipping']['dimensions'];
                            // Formato: "30x20x10,500" (alto x ancho x largo, peso en gramos)
                            if (is_string($dims)) {
                                if (preg_match('/(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?),?(\d+)?/', $dims, $m)) {
                                    $datos['alto'] = floatval($m[1]);
                                    $datos['ancho'] = floatval($m[2]);
                                    $datos['profundidad'] = floatval($m[3]);
                                    if (!empty($m[4])) {
                                        $datos['peso'] = floatval($m[4]) / 1000; // gramos a kg
                                    }
                                }
                            }
                        }

                        // Extraer peso del shipping si no lo tenemos
                        if (empty($datos['peso']) && !empty($itemData['shipping']['free_shipping'])) {
                            // A veces el peso viene en shipping.weight
                            if (!empty($itemData['shipping']['weight'])) {
                                $datos['peso'] = floatval($itemData['shipping']['weight']) / 1000;
                            }
                        }

                        // Extraer atributos y buscar dimensiones en ellos
                        if (!empty($itemData['attributes'])) {
                            foreach ($itemData['attributes'] as $attr) {
                                $name = $attr['name'] ?? '';
                                $value = $attr['value_name'] ?? '';
                                $attrId = $attr['id'] ?? '';

                                if ($value) {
                                    $datos['atributos'][] = [
                                        'nombre' => $name,
                                        'valor' => $value
                                    ];

                                    // Extraer dimensiones de atributos si no las tenemos
                                    $nameLower = mb_strtolower($name, 'UTF-8');
                                    $attrIdLower = mb_strtolower($attrId, 'UTF-8');

                                    if (empty($datos['peso']) && (strpos($nameLower, 'peso') !== false || $attrIdLower === 'weight')) {
                                        $datos['peso'] = extraerNumero($value);
                                    }
                                    if (empty($datos['alto']) && (strpos($nameLower, 'alto') !== false || strpos($nameLower, 'altura') !== false || $attrIdLower === 'height')) {
                                        $datos['alto'] = extraerNumero($value);
                                    }
                                    if (empty($datos['ancho']) && (strpos($nameLower, 'ancho') !== false || $attrIdLower === 'width')) {
                                        $datos['ancho'] = extraerNumero($value);
                                    }
                                    if (empty($datos['profundidad']) && (strpos($nameLower, 'profundidad') !== false || strpos($nameLower, 'largo') !== false || $attrIdLower === 'depth' || $attrIdLower === 'length')) {
                                        $datos['profundidad'] = extraerNumero($value);
                                    }
                                }
                            }
                        }
                    }

                    // =========================
                    // 2. DESCRIPCIÓN (REAL)
                    // =========================
                    $descRes = mlRequest("/items/{$itemId}/description");

                    if ($descRes['http_code'] === 200 && !empty($descRes['data'])) {
                        $datos['descripcion'] =
                            $descRes['data']['plain_text']
                            ?? $descRes['data']['text']
                            ?? '';
                    }

                   

                    // =========================
                    // 4. FALLBACK DESCRIPCIÓN
                    // =========================
                    if (empty($datos['descripcion']) && !empty($datos['atributos'])) {
                        $lines = [];

                        foreach (array_slice($datos['atributos'], 0, 8) as $a) {
                            $lines[] = "• {$a['nombre']}: {$a['valor']}";
                        }

                        $datos['descripcion'] = "Especificaciones técnicas:\n" . implode("\n", $lines);
                    }

                    // =========================
                    // 5. GUARDAR
                    // =========================
                    if ($q['tipo'] === 'Part Number' || $mejorScore >= 80) {
                        guardarDatosMLEnSige($sku, $datos);
                    }

                    return $datos;
                }
            }

            // =========================
            // 6. FALLBACK: PRODUCTS
            // =========================
            $prodRes = mlRequest('/products/search', [
                'q' => $q['query'],
                'site_id' => ML_SITE,
                'limit' => 3
            ]);

            if ($prodRes['http_code'] === 200 && !empty($prodRes['data']['results'])) {

                $producto = $prodRes['data']['results'][0];

                $datos = [
                    'encontrado' => true,
                    'encontrado_por' => 'catalogo',
                    'ml_id' => $producto['id'],
                    'nombre_ml' => $producto['name'],
                    'descripcion' => $producto['short_description']['content'] ?? '',
                    'atributos' => $producto['attributes'] ?? []
                ];

                return $datos;
            }
        }

        return ['encontrado' => false];

    } catch (Exception $e) {
        error_log("Error ML: " . $e->getMessage());
        return ['encontrado' => false];
    }
}
/**
 * Extraer número de un string (ej: "1.5 kg" -> 1.5)
 */
function extraerNumero($string) {
    if (preg_match('/[\d.,]+/', $string, $matches)) {
        return floatval(str_replace(',', '.', $matches[0]));
    }
    return null;
}

/**
 * Calcular un puntaje de similitud entre dos nombres (0 a 100+)
 */
function calcularScore($nombreEncontrado, $nombreOriginal) {
    $nombreEncontrado = mb_strtolower($nombreEncontrado, 'UTF-8');
    $nombreOriginal = mb_strtolower($nombreOriginal, 'UTF-8');
    
    $score = 0;

    // Tokenizar (palabras > 2 letras)
    $palabrasOriginal = array_filter(preg_split('/[\s\-_]+/', $nombreOriginal), function($p){ return strlen($p)>2; });
    $palabrasEncontrado = array_filter(preg_split('/[\s\-_]+/', $nombreEncontrado), function($p){ return strlen($p)>2; });

    // Puntos por coincidencia de palabras
    foreach ($palabrasOriginal as $po) {
        foreach ($palabrasEncontrado as $pe) {
            if ($po === $pe) {
                $score += 15; // Coincidencia exacta
                break; // Solo contar una vez por palabra original
            } elseif (strpos($pe, $po) !== false) {
                $score += 5; // Coincidencia parcial
                break;
            }
        }
    }

    // Puntos por coincidencia de MODELO (alfanumérico complejo)
    // Busca strings como "LS19D300" o "21DJ00QLAR" (pueden empezar con letras o números)
    preg_match_all('/(?:[a-z]+[0-9]+[a-z0-9]*|[0-9]+[a-z]+[a-z0-9]*)/i', $nombreOriginal, $matchesOrig);
    if (!empty($matchesOrig[0])) {
        foreach ($matchesOrig[0] as $modelo) {
            $modeloLower = mb_strtolower($modelo, 'UTF-8');
            if (strlen($modelo) >= 4 && strpos($nombreEncontrado, $modeloLower) !== false) {
                $score += 40; // ¡Gran coincidencia!
            }
        }
    }

    return $score;
}

/**
 * Verificar si el producto encontrado es relevante para lo que buscamos
 * Compara palabras clave entre el producto encontrado y el original
 */
function esProductoRelevante($nombreEncontrado, $nombreOriginal) {
    if (empty($nombreOriginal)) return true;

    $nombreEncontrado = mb_strtolower($nombreEncontrado, 'UTF-8');
    $nombreOriginal = mb_strtolower($nombreOriginal, 'UTF-8');

    // =================================================================================
    // FILTRO DE CATEGORÍAS INCOMPATIBLES (NUEVO)
    // =================================================================================
    // Si el producto original es de tecnología, rechazar categorías completamente diferentes
    $categoriasTecnologia = ['impresora', 'multifuncion', 'monitor', 'notebook', 'laptop', 'pc', 'computadora',
        'teclado', 'mouse', 'memoria', 'disco', 'ssd', 'procesador', 'placa', 'fuente', 'gabinete',
        'router', 'switch', 'cable', 'cartucho', 'toner', 'tinta'];

    $categoriasNoTecnologia = ['guitarra', 'bajo', 'piano', 'violin', 'musica', 'instrumento',
        'ropa', 'camisa', 'pantalon', 'zapato', 'zapatilla', 'remera', 'vestido',
        'mueble', 'silla', 'mesa', 'escritorio', 'cama', 'sofa',
        'juguete', 'muñeco', 'pelota', 'auto', 'moto', 'bicicleta',
        'perfume', 'maquillaje', 'crema', 'shampoo',
        'comida', 'alimento', 'bebida', 'vino', 'cerveza'];

    $esProductoTecnologia = false;
    foreach ($categoriasTecnologia as $cat) {
        if (strpos($nombreOriginal, $cat) !== false) {
            $esProductoTecnologia = true;
            break;
        }
    }

    if ($esProductoTecnologia) {
        foreach ($categoriasNoTecnologia as $cat) {
            if (strpos($nombreEncontrado, $cat) !== false) {
                return false; // RECHAZAR - categoría incompatible
            }
        }
    }

    // =================================================================================
    // FILTRO ANTI-ACCESORIOS v2 (EL MÁS IMPORTANTE)
    // =================================================================================
    // Si se busca un producto principal (monitor, notebook) y el resultado de ML
    // contiene una palabra inequívoca de accesorio ('adaptador', 'soporte', 'para', 'fuente'),
    // se descarta inmediatamente. Esto evita que el "ruido" de palabras comunes
    // (como 'vesa' en 'sin vesa') confunda a los filtros posteriores.
    $esProductoPrincipal = false;
    $productosPrincipales = ['monitor', 'notebook', 'laptop', 'impresora', 'multifuncion', 'teclado', 'placa de video', 'procesador'];
    foreach ($productosPrincipales as $p) {
        if (strpos($nombreOriginal, $p) !== false) {
            $esProductoPrincipal = true;
            break;
        }
    }

    if ($esProductoPrincipal) {
        $palabrasDeAccesorio = ['adaptador', 'soporte', 'base', 'funda', 'cargador', 'fuente', 'cable', 'repuesto', 'kit', 'para', 'montaje', 'carcasa'];
        foreach ($palabrasDeAccesorio as $acc) {
            // Si la palabra de accesorio está en el resultado pero NO en el original, es un accesorio.
            if (strpos($nombreEncontrado, $acc) !== false && strpos($nombreOriginal, $acc) === false) {
                return false; // RECHAZADO INMEDIATAMENTE
            }
        }
    }

    // 0. VALIDACIÓN DE MODELO (Flexible)
    // Si el original tiene un código alfanumérico largo (>=6 chars), verificar coincidencia
    // Permite variaciones como T530W vs DCPT530DW (comparten "530")
    preg_match_all('/\b([a-z]{1,3}[0-9]{3,}[a-z0-9]*|[0-9]{3,}[a-z]{1,3}[a-z0-9]*)\b/i', $nombreOriginal, $matchesOrig);

    if (!empty($matchesOrig[0])) {
        foreach ($matchesOrig[0] as $modeloOrg) {
            if (strlen($modeloOrg) >= 6) { // Solo modelos largos y específicos
                $modeloOrgLower = mb_strtolower($modeloOrg, 'UTF-8');

                // Extraer la parte numérica significativa (ej: T530W -> 530, DCPT530DW -> 530)
                preg_match('/[0-9]{3,}/', $modeloOrg, $numeros);
                $parteNumerica = $numeros[0] ?? '';

                // Debe coincidir: modelo exacto O parte numérica de 3+ dígitos
                $coincideExacto = strpos($nombreEncontrado, $modeloOrgLower) !== false;
                $coincideNumerico = strlen($parteNumerica) >= 3 && strpos($nombreEncontrado, $parteNumerica) !== false;

                if (!$coincideExacto && !$coincideNumerico) {
                    return false; // Modelo no coincide
                }
            }
        }
    }

    // 1. FILTRO ANTI-ACCESORIOS (Detectar "Para...", "Adaptador...", "Soporte...")
    // Si el título encontrado empieza declarando que es un accesorio, y el original NO, rechazar inmediatamente.
    // Esto soluciona el caso: Busco "Monitor Samsung" -> ML trae "Adaptador Vesa para Monitor Samsung"
    $patronesAccesorio = [
        '/^para\s+/u',          // Ej: "Para Monitor..."
        '/^for\s+/u',
        '/^compatible con\s+/u',
        '/^adaptador\s+/u',     // Ej: "Adaptador Vesa..."
        '/^soporte\s+/u',
        '/^base\s+/u',
        '/^funda\s+/u',
        '/^cargador\s+/u',
        '/^fuente\s+/u',
        '/^repuesto\s+/u',
        '/^kit\s+/u'
    ];

    foreach ($patronesAccesorio as $patron) {
        if (preg_match($patron, $nombreEncontrado) && !preg_match($patron, $nombreOriginal)) {
            return false;
        }
    }

    // Palabras que indican categorías diferentes (rechazar automáticamente)
    $palabrasIncompatibles = [
        'cartucho' => ['impresora', 'multifuncion', 'scanner', 'fotocopiadora'],
        'toner' => ['impresora', 'multifuncion', 'scanner', 'fotocopiadora'],
        'tinta' => ['impresora', 'multifuncion'],
        'impresora' => ['cartucho', 'toner', 'tinta', 'botella', 'cable', 'fuente', 'transformador', 'cabezal', 'repuesto', 'kit', 'limpieza', 'chip', 'sistema continuo'],
        'multifuncion' => ['cartucho', 'toner', 'tinta', 'botella', 'cable', 'fuente', 'transformador', 'cabezal', 'chip'],
        'notebook' => ['cargador', 'bateria', 'funda', 'mochila', 'teclado', 'pantalla', 'display', 'flex', 'bisagra', 'cooler', 'sticker', 'skin', 'repuesto', 'placa', 'mother', 'disco', 'memoria', 'soporte', 'base'],
        'laptop' => ['cargador', 'bateria', 'funda', 'mochila', 'teclado', 'pantalla', 'display', 'flex', 'bisagra', 'cooler', 'sticker', 'skin', 'repuesto', 'soporte', 'base'],
        'netbook' => ['cargador', 'bateria', 'funda', 'teclado', 'pantalla', 'flex'],
        'monitor' => ['cable', 'fuente', 'cargador', 'trafo', 'transformador', 'adaptador', 'soporte', 'brazo', 'base', 'pie', 'anclaje', 'montaje', 'vesa', 'repuesto', 'pantalla', 'display', 'panel', 'flex', 'tira', 'led', 'backlight', 'botonera', 'placa', 'main', 't-con', 'tcom', 'inverter', 'carcasa', 'marco', 'kit'],
        'teclado' => ['funda', 'cover', 'silicona', 'notebook', 'laptop', 'netbook'], // Si busco teclado, no quiero una notebook
        'bateria' => ['notebook', 'laptop', 'netbook'],
        'cargador' => ['notebook', 'laptop', 'netbook', 'monitor'],
        'placa' => ['caja'], // Evitar "Caja para placa"
        'video' => ['caja'],
    ];

    // Verificar incompatibilidades
    foreach ($palabrasIncompatibles as $palabra => $incompatibles) {
        if (strpos($nombreOriginal, $palabra) !== false) {
            foreach ($incompatibles as $incompatible) {
                // Solo rechazar si la palabra incompatible NO está en el original
                // (Ej: si el producto original es "Fuente para Monitor", no rechazamos por tener "Fuente")
                if (strpos($nombreOriginal, $incompatible) === false && strpos($nombreEncontrado, $incompatible) !== false) {
                    return false;
                }
            }
        }
    }

    // Marcas conocidas - si está la marca en el original, DEBE estar en el encontrado
    $marcas = ['hp', 'epson', 'canon', 'brother', 'samsung', 'lg', 'lenovo', 'dell', 'logitech', 'kingston', 'intel', 'amd', 'nvidia'];
    foreach ($marcas as $marca) {
        // Si el nombre original tiene la marca...
        if (strpos($nombreOriginal, $marca) !== false) {
            // Si el original tiene la marca, el encontrado DEBE tenerla
            if (strpos($nombreEncontrado, $marca) === false) {
                return false;
            }
        }
    }

    // =================================================================================
    // VALIDACIÓN ESTRICTA PARA CONSUMIBLES (cartuchos, toner, tinta)
    // =================================================================================
    // Si es un consumible, el número de modelo DEBE coincidir exactamente
    // Ej: "HP 57 XL" no puede traer "HP 662 XL"
    $esConsumible = preg_match('/\b(cartucho|toner|tinta|botella)\b/i', $nombreOriginal);
    if ($esConsumible) {
        // Extraer números de modelo del original (ej: 57, 662, T133, 60XL)
        preg_match_all('/\b([0-9]{2,4}(?:XL)?|T[0-9]{2,3})\b/i', $nombreOriginal, $modelosOrig);
        if (!empty($modelosOrig[0])) {
            foreach ($modelosOrig[0] as $modelo) {
                $modeloLower = mb_strtolower($modelo, 'UTF-8');
                // El modelo DEBE aparecer en el producto encontrado
                if (strpos($nombreEncontrado, $modeloLower) === false) {
                    return false; // Modelo no coincide
                }
            }
        }
    }

    // Extraer palabras clave significativas (más de 3 caracteres, no números solos)
    $palabrasOriginal = array_filter(
        preg_split('/[\s\-_]+/', $nombreOriginal),
        function($p) { return strlen($p) > 3 && !is_numeric($p); }
    );

    // Contar coincidencias
    $coincidencias = 0;
    foreach ($palabrasOriginal as $palabra) {
        if (strpos($nombreEncontrado, $palabra) !== false) {
            $coincidencias++;
        }
    }

    // Requerir al menos 2 palabras en común para productos sin marca
    return $coincidencias >= 2;
}

/**
 * Guardar datos de ML en las tablas de SIGE
 * - Descripción va en sige_art_articulo.art_artobs
 * - Dimensiones van en sige_adv_artdatvar (ADV_Peso, ADV_Alto, ADV_Ancho, ADV_Profundidad)
 *
 * @param string $sku
 * @param array $datos - Datos obtenidos de ML
 * @return bool
 */
function guardarDatosMLEnSige($sku, $datos) {
    try {
        $db = getDbConnection();

        // 1. Guardar descripción si existe y el campo está vacío
        if (!empty($datos['descripcion'])) {
            $stmt = $db->prepare("UPDATE sige_art_articulo SET art_artobs = ? WHERE TRIM(ART_IDArticulo) = ? AND (art_artobs IS NULL OR art_artobs = '')");
            $stmt->bind_param("ss", $datos['descripcion'], $sku);
            $stmt->execute();
            $stmt->close();
        }

        // 2. Guardar dimensiones si existen
        $hasDimensiones = !empty($datos['peso']) || !empty($datos['alto']) || !empty($datos['ancho']) || !empty($datos['profundidad']);

        if ($hasDimensiones) {
            // Primero verificar si existe el registro
            $stmt = $db->prepare("SELECT art_idarticulo FROM sige_adv_artdatvar WHERE TRIM(art_idarticulo) = ?");
            $stmt->bind_param("s", $sku);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->num_rows > 0;
            $stmt->close();

            if ($exists) {
                // Actualizar solo campos vacíos/nulos
                $updates = [];
                $params = [];
                $types = "";

                if (!empty($datos['peso'])) {
                    $updates[] = "ADV_Peso = COALESCE(NULLIF(ADV_Peso, 0), ?)";
                    $params[] = $datos['peso'];
                    $types .= "d";
                }
                if (!empty($datos['alto'])) {
                    $updates[] = "ADV_Alto = COALESCE(NULLIF(ADV_Alto, 0), ?)";
                    $params[] = $datos['alto'];
                    $types .= "d";
                }
                if (!empty($datos['ancho'])) {
                    $updates[] = "ADV_Ancho = COALESCE(NULLIF(ADV_Ancho, 0), ?)";
                    $params[] = $datos['ancho'];
                    $types .= "d";
                }
                if (!empty($datos['profundidad'])) {
                    $updates[] = "ADV_Profundidad = COALESCE(NULLIF(ADV_Profundidad, 0), ?)";
                    $params[] = $datos['profundidad'];
                    $types .= "d";
                }

                if (!empty($updates)) {
                    $sql = "UPDATE sige_adv_artdatvar SET " . implode(", ", $updates) . " WHERE TRIM(art_idarticulo) = ?";
                    $params[] = $sku;
                    $types .= "s";

                    $stmt = $db->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                // Insertar nuevo registro
                $stmt = $db->prepare("INSERT INTO sige_adv_artdatvar (art_idarticulo, ADV_Peso, ADV_Alto, ADV_Ancho, ADV_Profundidad) VALUES (?, ?, ?, ?, ?)");
                $peso = $datos['peso'] ?? 0;
                $alto = $datos['alto'] ?? 0;
                $ancho = $datos['ancho'] ?? 0;
                $prof = $datos['profundidad'] ?? 0;
                $stmt->bind_param("sdddd", $sku, $peso, $alto, $ancho, $prof);
                $stmt->execute();
                $stmt->close();
            }
        }

        $db->close();
        return true;
    } catch (Exception $e) {
        error_log("Error guardando datos ML en SIGE: " . $e->getMessage());
        return false;
    }
}
