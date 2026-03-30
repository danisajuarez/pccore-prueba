<?php
/**
 * API: Publicar producto en WooCommerce
 *
 * Crea o actualiza un producto en WooCommerce desde SIGE
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/mercadolibre.php';
checkAuth();

/**
 * Buscar o crear una categoría en WooCommerce
 * @param string $nombre Nombre de la categoría
 * @param int $parentId ID de la categoría padre (0 = raíz)
 * @return int|null ID de la categoría
 */
function buscarOCrearCategoria($nombre, $parentId = 0) {
    if (empty($nombre)) return null;

    $nombre = trim($nombre);

    // Buscar categoría existente
    $categorias = wcRequest('/products/categories?search=' . urlencode($nombre) . '&per_page=100');

    foreach ($categorias as $cat) {
        // Coincidencia exacta (case-insensitive) y mismo padre
        if (strcasecmp($cat['name'], $nombre) === 0 && $cat['parent'] == $parentId) {
            return $cat['id'];
        }
    }

    // Si no existe, crear
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
        $result = $syncService->publishProduct($sku);
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
    $sql = "SELECT
                a.ART_IDArticulo as sku,
                a.ART_DesArticulo as nombre,
                a.ART_PartNumber as part_number,
                a.art_artobs as descripcion_larga,
                (p.PAL_PrecVtaArt * m.MON_CotizMon) AS precio_sin_iva,
                (p.PAL_PrecVtaArt * m.MON_CotizMon * (1 + (a.ART_PorcIVARI / 100))) AS precio_final,
                COALESCE(s.ADS_CanFisicoArt - s.ADS_CanReservArt, 0) AS stock,
                d.ADV_Peso as peso,
                d.ADV_Alto as alto,
                d.ADV_Ancho as ancho,
                d.ADV_Profundidad as profundidad,
                attr.atr_descatr as attr_nombre,
                attr.aat_descripcion as attr_valor,
                lin.LIN_DesLinea as categoria,
                gli.gli_descripcion as supracategoria,
                car.CAR_DesCatArt as marca
            FROM sige_art_articulo a
            LEFT JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
                AND p.LIS_IDListaPrecio = $listaPrecio
            LEFT JOIN sige_ads_artdepsck s ON a.ART_IDArticulo = s.ART_IDArticulo
                AND s.DEP_IDDeposito = $deposito
            LEFT JOIN sige_adv_artdatvar d ON a.ART_IDArticulo = d.art_idarticulo
            LEFT JOIN sige_aat_artatrib attr ON a.ART_IDArticulo = attr.art_idarticulo
            LEFT JOIN sige_lin_linea lin ON a.LIN_IDLinea = lin.LIN_IDLinea
            LEFT JOIN sige_gli_gruplin gli ON lin.GLI_IdGli = gli.gli_idgli
            LEFT JOIN sige_car_catarticulo car ON a.CAR_IdCar = car.CAR_IdCar
            INNER JOIN sige_mon_moneda m ON m.MON_IdMon = 2
            WHERE TRIM(a.ART_IDArticulo) = ?
            ORDER BY attr.aat_orden";

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

    $producto = null;
    $atributos = [];

    while ($row = $result->fetch_assoc()) {
        if ($producto === null) {
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
        }

        if (!empty($row['attr_nombre']) && !empty($row['attr_valor'])) {
            $atributos[] = [
                'nombre' => $row['attr_nombre'],
                'valor' => $row['attr_valor']
            ];
        }
    }

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

    // ========================================
    // MARCA COMO ATRIBUTO
    // ========================================
    if (!empty($producto['marca'])) {
        $atributos[] = [
            'nombre' => 'Marca',
            'valor' => $producto['marca']
        ];
    }

    // Enviar precio CON IVA - WooCommerce no calcula nada
    $precioFinal = number_format((float) ($producto['precio_final'] ?? 0), 2, '.', '');
    $precioSinIva = number_format((float) ($producto['precio_sin_iva'] ?? 0), 2, '.', '');

    $nombre = trim($producto['nombre']);
    $descripcionLarga = trim($producto['descripcion_larga'] ?? '');

    // Si no hay descripción en SIGE, usar la de ML si viene
    if (empty($descripcionLarga) && !empty($inputDescripcionML)) {
        $descripcionLarga = trim($inputDescripcionML);
    }

    $productData = [
        'sku' => $producto['sku'],
        'name' => $nombre,
        'short_description' => $nombre,
        'description' => !empty($descripcionLarga) ? $descripcionLarga : $nombre,
        'regular_price' => $precioFinal,
        'stock_quantity' => (int) ($producto['stock'] ?? 0),
        'manage_stock' => true,
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
            $wcAttributes[] = [
                'name' => trim($attr['nombre']),
                'options' => [trim($attr['valor'])],
                'visible' => true,
                'variation' => false
            ];
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
