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
function buscarImagenesML($query, $nombreOriginal = null) {
    try {
        // Usar endpoint de CATÁLOGO (funciona, no está bloqueado)
        $result = mlRequest('/products/search', [
            'q' => $query,
            'site_id' => ML_SITE,
            'limit' => 10,
            'status' => 'active'
        ]);

        if ($result['http_code'] !== 200 || empty($result['data']['results'])) {
            return [];
        }

        // VALIDACIÓN ESTRICTA: Solo aceptar si el SKU/código aparece EXACTO en el nombre
        $queryLower = mb_strtolower($query, 'UTF-8');
        $mejorCandidato = null;

        foreach ($result['data']['results'] as $producto) {
            // Debe tener imágenes
            if (empty($producto['pictures'])) {
                continue;
            }

            $nombreLower = mb_strtolower($producto['name'] ?? '', 'UTF-8');

            // REGLA PRINCIPAL: El código debe aparecer textualmente en el nombre
            if (strpos($nombreLower, $queryLower) === false) {
                continue; // NO contiene el código exacto → RECHAZAR
            }

            // Verificar que no sea accesorio
            if ($nombreOriginal && !esProductoRelevante($producto['name'], $nombreOriginal)) {
                continue; // Es un accesorio → RECHAZAR
            }

            $mejorCandidato = $producto;
            break; // Tomar el primero que pase los filtros
        }

        if (!$mejorCandidato) {
            return [];
        }

        // Extraer imágenes del catálogo (ya vienen en el resultado)
        $imagenes = [];
        foreach ($mejorCandidato['pictures'] as $pic) {
            $url = $pic['url'] ?? null;
            if ($url) {
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
                    return ['nombre' => $attr['name'] ?? '', 'valor' => $attr['value_name'] ?? ''];
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
 * Buscar imágenes con estrategia de fallback:
 * 1. Buscar por Part Number (más específico)
 * 2. Si no encuentra, buscar por SKU (solo si parece un código de producto)
 * Nota: Búsqueda por nombre deshabilitada para evitar falsos positivos
 */
function buscarImagenesConFallback($sku, $partNumber = null, $nombre = null) {
    $resultado = null;

    // 1. Intentar por Part Number primero
    if ($partNumber && strlen($partNumber) >= 3) {
        $resultado = buscarImagenesML($partNumber, $nombre);
        if (!empty($resultado['imagenes'])) {
            $resultado['encontrado_por'] = 'Part Number';
        }
    }

    // 2. Intentar por SKU
    if (empty($resultado['imagenes']) && $sku && (strlen($sku) >= 5 || !is_numeric($sku))) {
        $resultado = buscarImagenesML($sku, $nombre);
        if (!empty($resultado['imagenes'])) {
            $resultado['encontrado_por'] = 'SKU';
        }
    }

    // 3. Búsqueda por nombre deshabilitada - traía demasiados falsos positivos

    if ($resultado && !empty($resultado['imagenes'])) {
        return $resultado;
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
 * Buscar datos completos de producto en ML (descripción, dimensiones, peso)
 * Cuando encuentra datos, los guarda directamente en SIGE para no buscar de nuevo
 * @param string $sku
 * @param string $partNumber
 * @param string $nombre
 * @return array - Datos del producto o array vacío
 */
function buscarDatosProductoML($sku, $partNumber = null, $nombre = null) {
    // Buscar en ML y guardar en SIGE si encuentra
    try {
        $queries = [];
        // Solo buscar por Part Number y SKU (más precisos)
        // NO buscar por nombre para evitar falsos positivos
        if ($partNumber && strlen($partNumber) >= 3) {
            $queries[] = ['query' => $partNumber, 'tipo' => 'Part Number'];
        }
        if ($sku && (strlen($sku) >= 5 || !is_numeric($sku))) {
            $queries[] = ['query' => $sku, 'tipo' => 'SKU'];
        }
        // Búsqueda por nombre deshabilitada - traía demasiados falsos positivos
        // if ($nombre) {
        //     $queries[] = ['query' => limpiarNombreProducto($nombre), 'tipo' => 'Nombre'];
        // }

        foreach ($queries as $q) {
            $result = mlRequest('/products/search', [
                'q' => $q['query'],
                'site_id' => ML_SITE,
                'limit' => 5,
                'status' => 'active'
            ]);

            if ($result['http_code'] !== 200 || empty($result['data']['results'])) {
                continue;
            }

            // ---------------------------------------------------------
            // ESTRATEGIA DE MEJOR CANDIDATO (SCORE)
            // ---------------------------------------------------------
            $mejorCandidato = null;
            $mejorScore = 0;

            foreach ($result['data']['results'] as $p) {
                // 1. Filtro duro de relevancia (palabras prohibidas, etc)
                if (!esProductoRelevante($p['name'], $nombre)) {
                    continue;
                }

                // 2. Calcular Score de similitud
                $score = calcularScore($p['name'], $nombre);

                // Bonificación si coincide el ID de producto buscado (Part Number o SKU)
                if (stripos($p['name'], $q['query']) !== false) {
                    $score += 20;
                }

                if ($score > $mejorScore) {
                    $mejorScore = $score;
                    $mejorCandidato = $p;
                }
            }

            // Solo procedemos si encontramos un candidato decente (Score > 40)
            if ($mejorCandidato && $mejorScore > 40) {
                $producto = $mejorCandidato;
                
                $datos = [
                    'encontrado' => true,
                    'encontrado_por' => $q['tipo'],
                    'ml_id' => $producto['id'], // ID real de ML
                    'nombre_ml' => $producto['name'],
                    'descripcion' => null,
                    'peso' => null,
                    'alto' => null,
                    'ancho' => null,
                    'profundidad' => null,
                    'atributos' => []
                ];

                if (!empty($producto['attributes'])) {
                    foreach ($producto['attributes'] as $attr) {
                        $attrId = strtoupper($attr['id'] ?? '');
                        $attrName = $attr['name'] ?? '';
                        $attrValue = $attr['value_name'] ?? '';

                        if (!empty($attrValue)) {
                            $datos['atributos'][] = [
                                'nombre' => $attrName,
                                'valor' => $attrValue
                            ];
                        }

                        if (strpos($attrId, 'WEIGHT') !== false || strpos($attrId, 'PESO') !== false) {
                            $datos['peso'] = extraerNumero($attrValue);
                        }
                        if (strpos($attrId, 'HEIGHT') !== false || strpos($attrId, 'ALTO') !== false) {
                            $datos['alto'] = extraerNumero($attrValue);
                        }
                        if (strpos($attrId, 'WIDTH') !== false || strpos($attrId, 'ANCHO') !== false) {
                            $datos['ancho'] = extraerNumero($attrValue);
                        }
                        if (strpos($attrId, 'DEPTH') !== false || strpos($attrId, 'LENGTH') !== false ||
                            strpos($attrId, 'LARGO') !== false || strpos($attrId, 'PROF') !== false) {
                            $datos['profundidad'] = extraerNumero($attrValue);
                        }
                    }
                }

                try {
                    $descResult = mlRequest('/products/' . $producto['id']);
                    if ($descResult['http_code'] === 200 && !empty($descResult['data'])) {
                        if (!empty($descResult['data']['short_description'])) {
                            $datos['descripcion'] = $descResult['data']['short_description']['content'] ?? null;
                        }
                        if (empty($datos['descripcion']) && !empty($datos['atributos'])) {
                            $attrTexts = [];
                            foreach (array_slice($datos['atributos'], 0, 5) as $attr) {
                                $attrTexts[] = $attr['nombre'] . ': ' . $attr['valor'];
                            }
                            $datos['descripcion'] = $producto['name'] . "\n\n" . implode("\n", $attrTexts);
                        }
                    }
                } catch (Exception $e) {
                    // Ignorar error de descripción
                }

                // 3. GUARDAR EN SIGE (SOLO SI ES MUY CONFIABLE)
                // Guardamos si vino por Part Number (muy preciso) o si el score de nombre es muy alto (>80)
                if ($q['tipo'] === 'Part Number' || $mejorScore >= 80) {
                    guardarDatosMLEnSige($sku, $datos);
                }
                
                return $datos;
            }
        }

        return ['encontrado' => false];
    } catch (Exception $e) {
        error_log("Error buscando datos en ML: " . $e->getMessage());
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

    // 0. VALIDACIÓN DE MODELO EXACTO (Blindaje)
    // Si el original tiene un código tipo "LS19D300" o "21DJ00QLAR" (letras y números mezclados, >4 chars)
    // El encontrado DEBE tenerlo. Si no, es basura.
    preg_match_all('/(?:[a-z]+[0-9]+[a-z0-9]*|[0-9]+[a-z]+[a-z0-9]*)/i', $nombreOriginal, $matchesOrig);
    preg_match_all('/(?:[a-z]+[0-9]+[a-z0-9]*|[0-9]+[a-z]+[a-z0-9]*)/i', $nombreEncontrado, $matchesEnc);

    if (!empty($matchesOrig[0])) {
        foreach ($matchesOrig[0] as $modeloOrg) {
            $modeloOrgLower = mb_strtolower($modeloOrg, 'UTF-8');
            if (strlen($modeloOrg) >= 5) { // Solo modelos largos y específicos
                // Si el modelo original NO está en el texto encontrado -> Descartar
                if (strpos($nombreEncontrado, $modeloOrgLower) === false) {
                    return false;
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
            // 🚨 FIX: NO retornar true aquí. 
            // Que tenga la marca no significa que sea el producto correcto (Ej: Samsung Monitor vs Samsung Soporte)
            // Dejamos que siga corriendo la validación de palabras clave.
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
