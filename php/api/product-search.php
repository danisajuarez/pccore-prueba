<?php
/**
 * API: Búsqueda de productos (Multi-tenant)
 *
 * Busca un producto por SKU en SIGE y WooCommerce
 */

// Capturar cualquier output/warning para evitar contaminar JSON
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/mercadolibre.php';

// Requiere autenticación por sesión
if (!isAuthenticated()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Validar API Key
$headers = getallheaders();
$apiKey = $headers['X-Api-Key'] ?? $headers['x-api-key'] ?? $_GET['api_key'] ?? '';
$expectedKey = getClienteId() . '-sync-2024';

if ($apiKey !== $expectedKey) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'API Key inválida']);
    exit;
}

// Función wcRequest para el fallback - LEE DIRECTAMENTE DE SESIÓN
function wcRequest($endpoint, $method = 'GET', $data = null) {
    // Obtener credenciales directamente de la sesión (no de constantes que pueden estar vacías)
    if (!isset($_SESSION['cliente_config'])) {
        throw new Exception("No hay sesión de cliente activa");
    }
    
    $config = $_SESSION['cliente_config'];
    
    // Validar que tenemos credenciales de WooCommerce
    if (empty($config['wc_url']) || empty($config['wc_key']) || empty($config['wc_secret'])) {
        throw new Exception("Credenciales de WooCommerce incompletas en la sesión");
    }
    
    // Armar URL con credenciales de sesión
    $url = $config['wc_url'] . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . urlencode($config['wc_key']) . '&consumer_secret=' . urlencode($config['wc_secret']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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
        throw new Exception("WooCommerce API error: $httpCode");
    }

    return json_decode($response, true);
}

$sku = trim($_GET['sku'] ?? '');
// Buscar en ML siempre (automático)
$searchML = !isset($_GET['search_ml']) || $_GET['search_ml'] !== 'false';

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU requerido']);
    exit();
}

try {
    $producto = null;
    $wooProducto = null;

    // Usar servicios si están disponibles
    if (class_exists('\App\Container') && \App\Container::isBooted()) {
        $repository = \App\Container::get(\App\Sige\ProductRepository::class);
        $wcClient = \App\Container::get(\App\WooCommerce\WooCommerceClient::class);
        $mapper = \App\Container::get(\App\WooCommerce\ProductMapper::class);

        // 1. Buscar en SIGE
        $producto = $repository->findBySku($sku);

        // 2. Buscar en WooCommerce
        $wcProduct = $wcClient->findBySku($sku);
        if ($wcProduct !== null) {
            $wooProducto = $mapper->extractWooSummary($wcProduct);
            // Agregar descripción
            if (isset($wcProduct['description'])) {
                $wooProducto['description'] = $wcProduct['description'];
            }
        }
    } else {
        // Fallback al código original - usar conexión de sesión
        $dbService = getSigeConnection();
        $db = $dbService->getConnection();

        // Buscar por PartNumber primero, luego por IDArticulo
        $listaPrecio = SIGE_LISTA_PRECIO;
        $deposito = SIGE_DEPOSITO;
        $sql = "SELECT
                    a.ART_IDArticulo as sku,
                    a.ART_DesArticulo as nombre,
                    a.ART_PartNumber as part_number,
                    a.art_artobs as descripcion_larga,
                    (p.PAL_PrecVtaArt * COALESCE(m.MON_CotizMon, 1)) AS precio_sin_iva,
                    (p.PAL_PrecVtaArt * COALESCE(m.MON_CotizMon, 1) * (1 + (a.ART_PorcIVARI / 100))) AS precio_final,
                    GREATEST(COALESCE(s.ADS_CanFisicoArt, 0) - COALESCE(s.ADS_CanReservArt, 0), 0) AS stock,
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
                LEFT JOIN sige_mon_moneda m ON a.MON_IdMon = m.MON_IdMon
                LEFT JOIN sige_adv_artdatvar d ON a.ART_IDArticulo = d.art_idarticulo
                LEFT JOIN sige_aat_artatrib attr ON a.ART_IDArticulo = attr.art_idarticulo
                LEFT JOIN sige_lin_linea lin ON a.LIN_IDLinea = lin.LIN_IDLinea
                LEFT JOIN sige_gli_gruplin gli ON lin.GLI_IdGli = gli.gli_idgli
                LEFT JOIN sige_car_catarticulo car ON a.CAR_IdCar = car.CAR_IdCar
                WHERE TRIM(a.ART_IDArticulo) = ?
                ORDER BY attr.aat_orden";

        $stmt = $db->prepare($sql);
        $stmt->bind_param("s", $sku);
        $stmt->execute();
        $result = $stmt->get_result();

        $atributos = [];
        while ($row = $result->fetch_assoc()) {
            if ($producto === null) {
                $producto = [
                    'sku' => trim($row['sku']),
                    'nombre' => $row['nombre'],
                    'part_number' => trim($row['part_number'] ?? ''),
                    'descripcion_larga' => $row['descripcion_larga'],
                    'precio_sin_iva' => $row['precio_sin_iva'],
                    'precio' => $row['precio_final'],
                    'stock' => $row['stock'],
                    'peso' => $row['peso'],
                    'alto' => $row['alto'],
                    'ancho' => $row['ancho'],
                    'profundidad' => $row['profundidad'],
                    'categoria' => $row['categoria'],
                    'supracategoria' => $row['supracategoria'],
                    'marca' => $row['marca'],
                    'atributos' => []
                ];
            }

            if (!empty($row['attr_nombre']) && !empty($row['attr_valor'])) {
                $atributos[] = [
                    'nombre' => $row['attr_nombre'],
                    'valor' => $row['attr_valor']
                ];
            }
        }

        if ($producto !== null) {
            $producto['atributos'] = $atributos;
        }

        $stmt->close();
        $db->close();

        // Buscar en WooCommerce (comparación exacta de SKU)
        // Primero intentar búsqueda normal
        $wcProducts = wcRequest('/products?sku=' . urlencode($sku));

        if (!empty($wcProducts)) {
            foreach ($wcProducts as $p) {
                // Comparar SKU como string, sin distinción de tipos
                if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                    $wooProducto = [
                        'id' => $p['id'],
                        'status' => $p['status'],
                        'permalink' => $p['permalink'],
                        'regular_price' => $p['regular_price'],
                        'stock_quantity' => $p['stock_quantity'],
                        'description' => $p['description'] ?? null,
                        'weight' => $p['weight'] ?? null,
                        'dimensions' => $p['dimensions'] ?? null
                    ];
                    break;
                }
            }
        }

        // Si no se encontró, buscar también en borradores y papelera
        if ($wooProducto === null) {
            $wcProductsAll = wcRequest('/products?sku=' . urlencode($sku) . '&status=any');

            if (!empty($wcProductsAll)) {
                foreach ($wcProductsAll as $p) {
                    if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                        $wooProducto = [
                            'id' => $p['id'],
                            'status' => $p['status'],
                            'permalink' => $p['permalink'],
                            'regular_price' => $p['regular_price'],
                            'stock_quantity' => $p['stock_quantity'],
                            'description' => $p['description'] ?? null,
                            'weight' => $p['weight'] ?? null,
                            'dimensions' => $p['dimensions'] ?? null
                        ];
                        break;
                    }
                }
            }
        }
    }

    // Responder
    if ($producto === null && $wooProducto === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Producto '$sku' no encontrado"]);
        exit();
    }

    $response = [
        'success' => true,
        'producto' => $producto,
        'woo_producto' => $wooProducto
    ];

    // Buscar datos en ML si se solicitó
    if ($searchML && $producto !== null) {
        $datosML = buscarDatosProductoML(
            $producto['sku'],
            $producto['part_number'] ?? null,
            $producto['nombre']
        );

        if (!empty($datosML['encontrado'])) {
            $response['ml_data'] = [
                'encontrado_por' => $datosML['encontrado_por'],
                'descripcion' => $datosML['descripcion'],
                'peso' => $datosML['peso'],
                'alto' => $datosML['alto'],
                'ancho' => $datosML['ancho'],
                'profundidad' => $datosML['profundidad'],
                'atributos' => $datosML['atributos']
            ];
        }
    }

    // Limpiar buffer antes de enviar respuesta
    ob_end_clean();
    echo json_encode($response);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Error $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fatal: ' . $e->getMessage()]);
}
