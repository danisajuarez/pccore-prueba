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
$sku = $input['sku'] ?? '';

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU requerido']);
    exit();
}

try {
    $db = getDbConnection();

    // Buscar producto en la BD (consulta SIGE con JOINs - incluye dimensiones y atributos)
    $sql = "SELECT
                a.ART_IDArticulo as sku,
                a.ART_DesArticulo as nombre,
                a.ART_PartNumber as part_number,
                a.art_artobs as descripcion_larga,
                p.PAL_PrecVtaArt AS precio_sin_iva,
                (p.PAL_PrecVtaArt * (1 + (a.ART_PorcIVARI / 100))) AS precio_final,
                (s.ADS_CanFisicoArt - s.ADS_CanReservArt) AS stock,
                d.ADV_Peso as peso,
                d.ADV_Alto as alto,
                d.ADV_Ancho as ancho,
                d.ADV_Profundidad as profundidad,
                attr.atr_descatr as attr_nombre,
                attr.aat_descripcion as attr_valor
            FROM sige_art_articulo a
            INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
            INNER JOIN sige_ads_artdepsck s ON a.ART_IDArticulo = s.ART_IDArticulo
            LEFT JOIN sige_adv_artdatvar d ON a.ART_IDArticulo = d.art_idarticulo
            LEFT JOIN sige_aat_artatrib attr ON a.ART_IDArticulo = attr.art_idarticulo
            WHERE a.ART_IDArticulo = ?
            AND p.LIS_IDListaPrecio = ?
            AND s.DEP_IDDeposito = ?
            ORDER BY attr.aat_orden";

    $stmt = $db->prepare($sql);
    $listaPrecios = SIGE_LISTA_PRECIO;
    $deposito = SIGE_DEPOSITO;
    $stmt->bind_param("sii", $sku, $listaPrecios, $deposito);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $db->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Producto con SKU '$sku' no encontrado en la base de datos"]);
        exit();
    }

    // Procesar resultados (múltiples filas si hay varios atributos)
    $producto = null;
    $atributos = [];

    while ($row = $result->fetch_assoc()) {
        if ($producto === null) {
            // Primera fila: guardar datos del producto
            $producto = [
                'sku' => $row['sku'],
                'nombre' => $row['nombre'],
                'part_number' => $row['part_number'],
                'descripcion_larga' => $row['descripcion_larga'],
                'precio_sin_iva' => $row['precio_sin_iva'],
                'precio_final' => $row['precio_final'],
                'stock' => $row['stock'],
                'peso' => $row['peso'],
                'alto' => $row['alto'],
                'ancho' => $row['ancho'],
                'profundidad' => $row['profundidad']
            ];
        }

        // Agregar atributo si existe
        if (!empty($row['attr_nombre']) && !empty($row['attr_valor'])) {
            $atributos[] = [
                'nombre' => $row['attr_nombre'],
                'valor' => $row['attr_valor']
            ];
        }
    }

    $db->close();

    // Validar que tiene datos mínimos
    if (empty($producto['nombre'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El producto no tiene nombre']);
        exit();
    }

    if (empty($producto['precio_final']) || $producto['precio_final'] <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El producto no tiene precio válido']);
        exit();
    }

    // Precio final con IVA incluido (como viene de SIGE)
    $precioFinal = number_format((float) $producto['precio_final'], 2, '.', '');

    // Preparar datos para WooCommerce
    $nombre = trim($producto['nombre']);  // ART_DesArticulo
    $descripcionLarga = trim($producto['descripcion_larga'] ?? '');  // art_artobs

    $productData = [
        'sku' => $producto['sku'],
        'name' => $nombre,
        'short_description' => $nombre,
        'description' => !empty($descripcionLarga) ? $descripcionLarga : $nombre,
        'regular_price' => $precioFinal,
        'stock_quantity' => (int) ($producto['stock'] ?? 0),
        'manage_stock' => true,
        'status' => 'publish',
        'type' => 'simple'
    ];

    // Agregar peso si existe
    if (!empty($producto['peso']) && $producto['peso'] > 0) {
        $productData['weight'] = strval($producto['peso']);
    }

    // Agregar dimensiones si existen
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

    // Agregar atributos si existen
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

    // Verificar si el producto ya existe en WooCommerce
    $wcProducts = wcRequest('/products?sku=' . urlencode($producto['sku']));
    $existingProduct = null;

    if (!empty($wcProducts)) {
        foreach ($wcProducts as $p) {
            if ($p['sku'] === $producto['sku']) {
                $existingProduct = $p;
                break;
            }
        }
    }

    if ($existingProduct) {
        // Actualizar producto existente
        $response = wcRequest('/products/' . $existingProduct['id'], 'PUT', $productData);
        $mensaje = 'Producto actualizado en WooCommerce';
    } else {
        // Crear nuevo producto
        $response = wcRequest('/products', 'POST', $productData);
        $mensaje = 'Producto creado en WooCommerce';
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
            'attributes' => $response['attributes'] ?? [],
            'permalink' => $response['permalink']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
