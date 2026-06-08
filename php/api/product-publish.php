<?php
/**
 * API: Publicar producto en WooCommerce (Multi-tenant)
 *
 * Crea o actualiza un producto en WooCommerce desde SIGE
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/mercadolibre.php';

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

// Función para obtener conexión a BD SIGE
function getDbConnection() {
    $dbService = getSigeConnection();
    return $dbService->getConnection();
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

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Leer JSON del body
$input = json_decode(file_get_contents('php://input'), true);
$sku = trim($input['sku'] ?? '');
$inputImages = $input['images'] ?? []; // Imágenes opcionales
$inputDescripcionML = $input['descripcion_ml'] ?? null; // Descripción de ML opcional

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU requerido']);
    exit();
}

try {
    // Usar servicios si están disponibles
    if (class_exists('\App\Container') && \App\Container::isBooted()) {
        $syncService = \App\Container::get(\App\Sige\SyncService::class);
        $result = $syncService->publishProduct($sku, $inputImages, $inputDescripcionML);
        echo json_encode($result);
        exit();
    }

    // Fallback al código original
    $db = getDbConnection();

    // Obtener cotización del dólar
    $cotizacion = 1;
    $resCotiz = $db->query("SELECT MON_CotizMon FROM sige_mon_moneda WHERE MON_IdMon = 2");
    if ($resCotiz && $rowCotiz = $resCotiz->fetch_assoc()) {
        $cotizacion = (float)$rowCotiz['MON_CotizMon'];
    }

    // Usar sige_pal_preartlis para precios y sige_ads_artdepsck para stock por depósito
    $listaPrecio = SIGE_LISTA_PRECIO;
    $deposito = SIGE_DEPOSITO;
    // Query 1: datos del producto (con GROUP BY para sumar stock correctamente)
    $sql = "SELECT a.ART_IDArticulo as sku,
      a.ART_DesArticulo as nombre,
      a.ART_PartNumber as part_number,
      a.art_artobs as descripcion_larga,
      (p.PAL_PrecVtaArt * m.MON_CotizMon) AS precio_sin_iva,
      (p.PAL_PrecVtaArt * m.MON_CotizMon * (1 + (a.ART_PorcIVARI / 100))) AS precio_final,
      SUM(s.ADS_CanFisicoArt - s.ADS_CanReservArt) AS stock,
      d.ADV_Peso as peso,
      d.ADV_Alto as alto,
      d.ADV_Ancho as ancho,
      d.ADV_Profundidad as profundidad,
      lin.LIN_DesLinea as categoria,
      gli.gli_descripcion as supracategoria,
      car.CAR_DesCatArt as marca
  FROM sige_art_articulo a
  INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
  INNER JOIN sige_ads_artdepsck s ON a.ART_IDArticulo = s.ART_IDArticulo
  INNER JOIN sige_lin_linea lin ON a.LIN_IDLinea = lin.LIN_IDLinea
  INNER JOIN sige_gli_gruplin gli ON lin.GLI_IdGli = gli.gli_idgli
  INNER JOIN sige_car_catarticulo car ON a.CAR_IdCar = car.CAR_IdCar
  INNER JOIN sige_mon_moneda m ON m.MON_IdMon = a.MON_IdMon
  LEFT JOIN sige_adv_artdatvar d ON a.ART_IDArticulo = d.art_idarticulo
 WHERE a.ART_IDArticulo = ?
  AND s.DEP_IDDeposito IN ( $deposito )
  AND p.LIS_IDListaPrecio = $listaPrecio
  GROUP BY a.ART_IDArticulo";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $db->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Producto con SKU '$sku' no encontrado en la base de datos"]);
        exit();
    }

    $row = $result->fetch_assoc();
    $producto = [
        'sku' => trim($row['sku']),
        'nombre' => $row['nombre'],
        'part_number' => trim($row['part_number'] ?? ''),
        'descripcion_larga' => $row['descripcion_larga'],
        'precio_sin_iva' => $row['precio_sin_iva'],
        'precio_final' => $row['precio_final'],
        'stock' => $row['stock'],
        'peso' => $row['peso'],
        'alto' => $row['alto'],
        'ancho' => $row['ancho'],
        'profundidad' => $row['profundidad'],
        'categoria' => $row['categoria'],
        'supracategoria' => $row['supracategoria'],
        'marca' => $row['marca']
    ];

    // Query 2: atributos del producto (query separada para traerlos todos)
    $atributos = [];
    $sqlAttr = "SELECT atr_descatr as nombre, aat_descripcion as valor
                FROM sige_aat_artatrib
                WHERE TRIM(art_idarticulo) = ?
                ORDER BY aat_orden";
    $stmtAttr = $db->prepare($sqlAttr);
    $stmtAttr->bind_param("s", $sku);
    $stmtAttr->execute();
    $resultAttr = $stmtAttr->get_result();
    while ($attrRow = $resultAttr->fetch_assoc()) {
        if (!empty($attrRow['nombre']) && !empty($attrRow['valor'])) {
            $atributos[] = [
                'nombre' => $attrRow['nombre'],
                'valor' => $attrRow['valor']
            ];
        }
    }
    $stmtAttr->close();

    $db->close();

    // Buscar datos faltantes en Mercado Libre
    $faltaDescripcion = empty($producto['descripcion_larga']);
    $faltaDimensiones = empty($producto['peso']) && empty($producto['alto']) && empty($producto['ancho']);

    if ($faltaDescripcion || $faltaDimensiones) {
        $datosML = buscarDatosProductoML(
            $producto['sku'],
            $producto['part_number'],
            $producto['nombre']
        );

        if (!empty($datosML['encontrado'])) {
            // Completar descripción si falta
            if ($faltaDescripcion && !empty($datosML['descripcion'])) {
                $producto['descripcion_larga'] = $datosML['descripcion'];
            }

            // Completar dimensiones si faltan
            if (empty($producto['peso']) && !empty($datosML['peso'])) {
                $producto['peso'] = $datosML['peso'];
            }
            if (empty($producto['alto']) && !empty($datosML['alto'])) {
                $producto['alto'] = $datosML['alto'];
            }
            if (empty($producto['ancho']) && !empty($datosML['ancho'])) {
                $producto['ancho'] = $datosML['ancho'];
            }
            if (empty($producto['profundidad']) && !empty($datosML['profundidad'])) {
                $producto['profundidad'] = $datosML['profundidad'];
            }

            // Agregar atributos de ML si no tiene
            if (empty($atributos) && !empty($datosML['atributos'])) {
                $atributos = array_slice($datosML['atributos'], 0, 10); // Máximo 10 atributos
            }
        }
    }

    // Validaciones
    if (empty($producto['nombre'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El producto no tiene nombre']);
        exit();
    }

    // ========================================
    // CATEGORÍAS AUTOMÁTICAS DESDE SIGE
    // ========================================
    $categoryIds = [];

    // Buscar o crear supracategoría
    $supracategoriaId = null;
    if (!empty($producto['supracategoria'])) {
        $supracategoriaId = buscarOCrearCategoria($producto['supracategoria'], 0);
    }

    // Buscar o crear categoría (como hija de supracategoría)
    if (!empty($producto['categoria'])) {
        $parentId = $supracategoriaId ?? 0;
        $categoriaId = buscarOCrearCategoria($producto['categoria'], $parentId);
        if ($categoriaId) {
            $categoryIds[] = ['id' => $categoriaId];
        }
    } elseif ($supracategoriaId) {
        // Si no hay categoría pero sí supracategoría, usar la supra
        $categoryIds[] = ['id' => $supracategoriaId];
    }

    // La marca va por el plugin de brands, no como atributo genérico
    $marcaToSync = !empty($producto['marca']) ? trim($producto['marca']) : null;

    // Enviar precio CON IVA - WooCommerce no calcula nada
    $precioFinal = number_format((float) ($producto['precio_final'] ?? 0), 2, '.', '');
    $precioSinIva = number_format((float) ($producto['precio_sin_iva'] ?? 0), 2, '.', '');

    $nombre = trim($producto['nombre']);
    $descripcionLarga = trim($producto['descripcion_larga'] ?? '');
    $descripcionCorta = trim($inputDescripcionML ?? '');

    $stockQty = (int) ($producto['stock'] ?? 0);
    $productData = [
        'sku' => $producto['sku'],
        'name' => $nombre,
        'short_description' => $descripcionCorta,
        'description' => !empty($descripcionLarga) ? $descripcionLarga : $descripcionCorta,
        'regular_price' => $precioFinal,
        'stock_quantity' => $stockQty,
        'stock_status' => $stockQty > 0 ? 'instock' : 'outofstock',
        'manage_stock' => true,
        'catalog_visibility' => 'visible',
        'status' => 'publish',
        'type' => 'simple',
        'meta_data' => [
            [
                'key' => '_precio_sin_iva',
                'value' => $precioSinIva
            ]
        ]
    ];

    // Agregar categorías si existen
    if (!empty($categoryIds)) {
        $productData['categories'] = $categoryIds;
    }

    // Agregar imágenes si vienen en el input
    if (!empty($inputImages)) {
        $productData['images'] = $inputImages;
    }

    if (!empty($producto['peso']) && $producto['peso'] > 0) {
        $productData['weight'] = strval($producto['peso']);
    }

    $dimensions = [];
    if (!empty($producto['alto']) && $producto['alto'] > 0) {
        $dimensions['height'] = strval($producto['alto']);
    }
    if (!empty($producto['ancho']) && $producto['ancho'] > 0) {
        $dimensions['width'] = strval($producto['ancho']);
    }
    if (!empty($producto['profundidad']) && $producto['profundidad'] > 0) {
        $dimensions['length'] = strval($producto['profundidad']);
    }
    if (!empty($dimensions)) {
        $productData['dimensions'] = $dimensions;
    }

    if (!empty($atributos)) {
        $wcAttributes = [];
        foreach ($atributos as $attr) {
            $globalId = buscarOCrearAtributoGlobal(trim($attr['nombre']));
            if ($globalId) {
                $wcAttributes[] = [
                    'id' => $globalId,
                    'options' => [trim($attr['valor'])],
                    'visible' => true,
                    'variation' => false
                ];
            } else {
                $wcAttributes[] = [
                    'name' => trim($attr['nombre']),
                    'options' => [trim($attr['valor'])],
                    'visible' => true,
                    'variation' => false
                ];
            }
        }
        $productData['attributes'] = $wcAttributes;
    }

    // Buscar producto existente con el SKU (en cualquier estado)
    $wcProducts = wcRequest('/products?sku=' . urlencode($producto['sku']) . '&status=any');
    $existingProduct = null;

    if (!empty($wcProducts)) {
        foreach ($wcProducts as $p) {
            // Comparación exacta de SKU sin distinción de tipos
            if (strcasecmp(trim($p['sku']), trim($producto['sku'])) === 0) {
                $existingProduct = $p;
                break;
            }
        }
    }

    if ($existingProduct) {
        // Producto ya existe - actualizar
        $response = wcRequest('/products/' . $existingProduct['id'], 'PUT', $productData);
        $mensaje = "Producto actualizado en WooCommerce (ya existía con status: {$existingProduct['status']})";
    } else {
        // Producto nuevo - crear
        $response = wcRequest('/products', 'POST', $productData);
        $mensaje = 'Producto creado en WooCommerce';
    }

    // ========================================
    // SINCRONIZAR MARCA CON PLUGIN DE BRANDS
    // ========================================
    $brandSynced = null;
    if (!empty($response['id']) && $marcaToSync) {
        $brandSynced = syncBrandToWooCommerce($response['id'], $marcaToSync);
    }

    // ========================================
    // MARCAR COMO PUBLICADO EN SIGE (art_articuloweb = 'S')
    // ========================================
    if (!empty($response['id'])) {
        try {
            $dbUpdate = getDbConnection();
            $sqlUpdate = "UPDATE sige_art_articulo SET art_articuloweb = 'S' WHERE TRIM(ART_IDArticulo) = ?";
            $stmtUpdate = $dbUpdate->prepare($sqlUpdate);
            $stmtUpdate->bind_param("s", $sku);
            $stmtUpdate->execute();
            $stmtUpdate->close();
            $dbUpdate->close();
        } catch (Exception $e) {
            // Log error pero no fallar la respuesta - el producto ya se publicó
            error_log("Error actualizando art_articuloweb para SKU $sku: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'debug' => [
            'atributos_sige' => count($atributos),
            'atributos_enviados' => !empty($productData['attributes']) ? count($productData['attributes']) : 0
        ],
        'brand_sync' => $brandSynced,
        'product' => [
            'id' => $response['id'],
            'sku' => $response['sku'],
            'name' => $response['name'],
            'status' => $response['status'],
            'regular_price' => $response['regular_price'],
            'stock_quantity' => $response['stock_quantity'],
            'weight' => $response['weight'] ?? null,
            'dimensions' => $response['dimensions'] ?? null,
            'categories' => $response['categories'] ?? [],
            'attributes' => $response['attributes'] ?? [],
            'permalink' => $response['permalink']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function buscarOCrearAtributoGlobal(string $nombre): ?int {
    try {
        $atributos = wcRequest('/products/attributes?per_page=100');
        foreach ($atributos as $atr) {
            if (strcasecmp($atr['name'], $nombre) === 0) {
                return (int) $atr['id'];
            }
        }
        $nuevo = wcRequest('/products/attributes', 'POST', [
            'name' => $nombre,
            'type' => 'select',
            'has_archives' => false
        ]);
        return isset($nuevo['id']) ? (int) $nuevo['id'] : null;
    } catch (Exception $e) {
        return null;
    }
}

function syncBrandToWooCommerce($productId, $brandName) {
    try {
        $brands = wcRequest('/products/brands?search=' . urlencode($brandName));
        $brandId = null;
        $wasCreated = false;

        if (!empty($brands)) {
            foreach ($brands as $brand) {
                if (strtolower($brand['name']) === strtolower($brandName)) {
                    $brandId = $brand['id'];
                    break;
                }
            }
        }

        if (!$brandId) {
            $newBrand = wcRequest('/products/brands', 'POST', ['name' => $brandName]);
            if (isset($newBrand['id'])) {
                $brandId = $newBrand['id'];
                $wasCreated = true;
            }
        }

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
