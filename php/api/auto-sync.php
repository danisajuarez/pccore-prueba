<?php
/**
 * AUTO-SYNC: Híbrido - Busca individual, actualiza en batch
 *
 * Procesa productos con cambios de precio/stock:
 * - Detecta diferencias entre SIGE y lo último sincronizado
 * - Actualiza TODOS juntos en un batch a WooCommerce
 *
 * Soporta:
 * - Llamadas con sesión activa (desde el navegador)
 * - Llamadas desde cron (sin sesión, usa API key para identificar cliente)
 */

// Silencia errores PHP para que no rompan la respuesta JSON
error_reporting(0);
ini_set('display_errors', 0);

// El script puede tardar hasta 5 minutos (sincronizar miles de productos)
set_time_limit(300);

// Toda respuesta de este endpoint es JSON
header('Content-Type: application/json');

// Constantes de la BD Master (MASTER_DB_HOST, USER, PASS, NAME, PORT)
require_once __DIR__ . '/../config/master.php';

// Cuántos productos procesa por llamada
define('BATCH_SIZE', 100);

// Config del cliente activo (se llena más abajo por sesión o por API key)
$SYNC_CONFIG = null;

/**
 * Cuando no hay sesión (ej: cron job), resuelve el cliente a partir de la API key.
 * La key tiene el formato "clienteid-sync-2024".
 * Busca el cliente en la BD Master y devuelve sus credenciales completas.
 */
function loadClienteConfigFromKey($apiKey) {
    // Valida el formato y extrae el clienteId del prefijo
    if (!preg_match('/^(.+)-sync-2024$/', $apiKey, $matches)) {
        return null;
    }
    $clienteId = $matches[1];

    // Conecta a la BD Master
    $masterDb = new mysqli(MASTER_DB_HOST, MASTER_DB_USER, MASTER_DB_PASS, MASTER_DB_NAME, MASTER_DB_PORT);
    if ($masterDb->connect_error) {
        throw new Exception("Error conectando a BD Master: " . $masterDb->connect_error);
    }

    // Detecta charset de la BD Master para evitar problemas con tildes/ñ
    $masterCharset = $masterDb->query("SELECT @@character_set_database as cs");
    $masterCharset = $masterCharset ? $masterCharset->fetch_assoc()['cs'] : 'utf8';
    @$masterDb->set_charset(strpos($masterCharset, 'latin') !== false ? 'latin1' : 'utf8mb4');
    if ($masterDb->errno) $masterDb->set_charset('utf8');

    // Busca el cliente por su ID en la tabla de clientes WooCommerce
    $stmt = $masterDb->prepare("SELECT * FROM sige_two_terwoo WHERE TER_IdTercero = ?");
    $stmt->bind_param("s", $clienteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $clienteData = $result->fetch_assoc();
    $stmt->close();
    $masterDb->close();

    if (!$clienteData) {
        return null;
    }

    // Devuelve todas las credenciales del cliente: BD SIGE + WooCommerce
    return [
        'id'          => $clienteData['TER_IdTercero'],
        'nombre'      => $clienteData['TER_RazonSocialTer'],
        'db_host'     => $clienteData['TWO_ServidorDBAnt'],
        'db_user'     => $clienteData['TWO_UserDBAnt'],
        'db_pass'     => $clienteData['TWO_PassDBAnt'],
        'db_port'     => (int)($clienteData['TWO_PuertoDBAnt'] ?? 3306),
        'db_name'     => $clienteData['TWO_NombreDBAnt'],
        'wc_url'      => $clienteData['TWO_WooUrl'] ?? null,
        'wc_key'      => $clienteData['TWO_WooKey'] ?? null,
        'wc_secret'   => $clienteData['TWO_WooSecret'] ?? null,
        'lista_precio'=> (int)($clienteData['TWO_ListaPrecio'] ?? 1),
        'deposito'    => $clienteData['TWO_Deposito'] ?? '1',
    ];
}

// ============================================================================
// AUTENTICACIÓN: Soporta sesión activa (browser) O API key (cron)
// ============================================================================

// La key siempre tiene que venir en el URL: ?key=clienteid-sync-2024
$keyFromUrl = $_GET['key'] ?? '';

// Intenta cargar sesión primero
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['cliente_config'])) {
    // Tiene sesión activa: toma la config del cliente desde la sesión
    $SYNC_CONFIG = $_SESSION['cliente_config'];
    $expectedKey = $SYNC_CONFIG['id'] . '-sync-2024';

    // Igual valida que la key del URL corresponda a este cliente
    if ($keyFromUrl !== $expectedKey) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'API Key invalida']);
        exit;
    }
} else {
    // Sin sesión: el cron debe pasar la key en el URL
    if (empty($keyFromUrl)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'API Key requerida']);
        exit;
    }

    try {
        // Resuelve el cliente buscándolo en la BD Master por la key
        $SYNC_CONFIG = loadClienteConfigFromKey($keyFromUrl);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }

    if (!$SYNC_CONFIG) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Cliente no encontrado para esta API key']);
        exit;
    }
}

// Si no hay wc_url no tiene sentido continuar
if (!$SYNC_CONFIG || empty($SYNC_CONFIG['wc_url'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuración de cliente incompleta']);
    exit;
}

/**
 * Hace un request a la API REST de WooCommerce del cliente activo.
 * Arma la URL con consumer_key y consumer_secret desde $SYNC_CONFIG.
 * GET: timeout 30s | POST/PUT: timeout 120s con body JSON.
 * Lanza excepción si hay error cURL o HTTP >= 400.
 */
function wcRequest($endpoint, $method = 'GET', $data = null) {
    global $SYNC_CONFIG;

    if (!$SYNC_CONFIG) {
        throw new Exception("No hay configuración de cliente cargada");
    }

    if (empty($SYNC_CONFIG['wc_url']) || empty($SYNC_CONFIG['wc_key']) || empty($SYNC_CONFIG['wc_secret'])) {
        throw new Exception("Credenciales de WooCommerce incompletas");
    }

    // Arma la URL completa con las credenciales en el querystring
    $url = $SYNC_CONFIG['wc_url'] . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . urlencode($SYNC_CONFIG['wc_key']) . '&consumer_secret=' . urlencode($SYNC_CONFIG['wc_secret']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // entornos con SSL autofirmado
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($method === 'PUT' || $method === 'POST') {
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // batch puede tardar más
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    } else {
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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

try {
    // Conecta a la BD SIGE del cliente (distinta para cada cliente)
    $db = new mysqli(
        $SYNC_CONFIG['db_host'],
        $SYNC_CONFIG['db_user'],
        $SYNC_CONFIG['db_pass'],
        $SYNC_CONFIG['db_name'],
        $SYNC_CONFIG['db_port']
    );

    if ($db->connect_error) {
        throw new Exception("Error conectando a BD SIGE: " . $db->connect_error);
    }

    // Detecta charset de la BD del cliente para evitar problemas con tildes/ñ
    $dbCharset = $db->query("SELECT @@character_set_database as cs");
    $dbCharset = $dbCharset ? $dbCharset->fetch_assoc()['cs'] : 'utf8';
    @$db->set_charset(strpos($dbCharset, 'latin') !== false ? 'latin1' : 'utf8mb4');
    if ($db->errno) $db->set_charset('utf8');

    // -------------------------------------------------------------------------
    // PASO 1: Contar cuántos productos tienen cambios pendientes
    // pal_precvtaart = precio actual en SIGE
    // prs_precvtaart = precio que ya fue sincronizado a Woo (snapshot)
    // ads_disponible = stock actual en SIGE
    // prs_disponible = stock que ya fue sincronizado a Woo (snapshot)
    // -------------------------------------------------------------------------
    $countSql = "SELECT COUNT(*) as total
                 FROM sige_prs_presho s
                 INNER JOIN sige_art_articulo a ON a.ART_IDArticulo = s.art_idarticulo
                 WHERE s.pal_precvtaart <> s.prs_precvtaart
                    OR s.prs_disponible <> s.ads_disponible";

    $countResult = $db->query($countSql);
    $totalPendientes = $countResult ? $countResult->fetch_assoc()['total'] : 0;

    // Si no hay diferencias, no hay nada que sincronizar
    if ($totalPendientes == 0) {
        $db->close();
        echo json_encode(['success' => true, 'message' => 'Sin cambios detectados.', 'remaining' => 0]);
        exit;
    }

    // -------------------------------------------------------------------------
    // PASO 2: Traer UN lote de hasta BATCH_SIZE productos con cambios
    // Calcula precio_sin_iva dividiendo por (1 + IVA%) para guardarlo como meta
    // -------------------------------------------------------------------------
    $sql = "SELECT s.art_idarticulo as sku,
                   s.pal_precvtaart as precio,
                   s.ads_disponible as stock,
                   (s.pal_precvtaart / (1 + (a.ART_PorcIVARI / 100))) AS precio_sin_iva
            FROM sige_prs_presho s
            INNER JOIN sige_art_articulo a ON a.ART_IDArticulo = s.art_idarticulo
            WHERE (s.pal_precvtaart <> s.prs_precvtaart
               OR s.prs_disponible <> s.ads_disponible)
            --    and s.art_idarticulo = 'DCPT530DW'
            LIMIT " . BATCH_SIZE;

    $result = $db->query($sql);
    if (!$result) throw new Exception("Error en DB: " . $db->error);

    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }

    // -------------------------------------------------------------------------
    // PASO 3: Buscar los IDs de WooCommerce para todos los SKUs del lote
    // Una sola llamada a la API: GET /products?sku=A,B,C,...
    // WooCommerce devuelve los productos que encuentra; los que no aparecen
    // es porque no existen en la tienda (not_in_woo).
    // -------------------------------------------------------------------------
    $batchUpdate = []; // productos encontrados en Woo → se van a actualizar
    $notInWoo    = []; // SKUs que no existen en Woo → se marcan igual para no quedar en loop
    $skuToData   = []; // mapa sku → datos del producto (para acceder rápido después)

    foreach ($productos as $prod) {
        $skuToData[$prod['sku']] = $prod;
    }

    // Junta todos los SKUs separados por coma para la búsqueda en lote
    $skuList   = implode(',', array_map('urlencode', array_keys($skuToData)));
    $wcIdBySku = []; // mapa sku_minúscula → woo_id

    try {
        $wcProducts = wcRequest('/products?sku=' . $skuList . '&per_page=100&status=any');
        if (!empty($wcProducts)) {
            foreach ($wcProducts as $p) {
                $sku = trim($p['sku']);
                if ($sku !== '') {
                    $wcIdBySku[strtolower($sku)] = $p['id'];
                }
            }
        }
    } catch (Exception $e) {
        // Si falla la búsqueda en lote, todos se tratan como not_in_woo
        $notInWoo = array_keys($skuToData);
    }

    // -------------------------------------------------------------------------
    // PASO 4: Clasificar cada producto del lote
    // - Tiene ID en Woo → armar el payload para el batch update
    // - No tiene ID en Woo → agregar a la lista de not_in_woo
    // -------------------------------------------------------------------------
    foreach ($skuToData as $sku => $prod) {
        $wcId = $wcIdBySku[strtolower($sku)] ?? null;
        if ($wcId !== null) {
            $batchUpdate[] = [
                'id'             => $wcId,
                'sku'            => $sku, // solo para identificarlo acá, no se manda a Woo
                'regular_price'  => number_format((float)$prod['precio'], 2, '.', ''),
                'sale_price'     => '', // limpia precio oferta si existía
                'manage_stock'   => true,
                'stock_quantity' => (int)$prod['stock'],
                'stock_status'   => ((int)$prod['stock'] > 0) ? 'instock' : 'outofstock',
                'meta_data'      => [
                    // Precio sin IVA para mostrar en la ficha del producto
                    ['key' => '_price_no_taxes', 'value' => number_format((float)$prod['precio_sin_iva'], 2, '.', '')]
                ]
            ];
        } else {
            $notInWoo[] = $sku;
        }
    }

    // -------------------------------------------------------------------------
    // PASO 5: Batch update a WooCommerce (una sola llamada con todos)
    // POST /products/batch { "update": [ {id, precio, stock...}, ... ] }
    //
    // Si el batch es exitoso → actualiza el snapshot en BD SIGE para que
    //   estos productos no vuelvan a aparecer como pendientes.
    // Si el batch falla → NO actualiza BD (se reintentarán en el próximo lote).
    // -------------------------------------------------------------------------
    $successful = 0;
    $failed = 0;
    $results = [];

    if (!empty($batchUpdate)) {
        // Quita el campo 'sku' que usamos internamente pero Woo no necesita
        $wcPayload = array_map(function($item) {
            $clean = $item;
            unset($clean['sku']);
            return $clean;
        }, $batchUpdate);

        try {
            wcRequest('/products/batch', 'POST', ['update' => $wcPayload]);

            // Batch exitoso: marca cada producto como sincronizado en BD SIGE
            foreach ($batchUpdate as $item) {
                $sku = $item['sku'];
                $data = $skuToData[$sku];
                $precio = $db->real_escape_string($data['precio']);
                $stock = $db->real_escape_string($data['stock']);

                // Actualiza el snapshot: precio y stock "ya sincronizados"
                // prs_fecultactweb = fecha de la última actualización web
                $db->query("UPDATE sige_prs_presho
                            SET prs_fecultactweb = NOW(),
                                prs_precvtaart = '$precio',
                                prs_disponible = '$stock'
                            WHERE art_idarticulo = '$sku'");

                $successful++;
                $results[] = ['sku' => $sku, 'status' => 'updated', 'price' => $item['regular_price'], 'stock' => $item['stock_quantity']];
            }
        } catch (Exception $e) {
            // Batch falló: no se toca la BD, quedan pendientes para el próximo intento
            foreach ($batchUpdate as $item) {
                $failed++;
                $results[] = ['sku' => $item['sku'], 'status' => 'error', 'error' => $e->getMessage()];
            }
        }
    }

    // -------------------------------------------------------------------------
    // PASO 6: Marcar los not_in_woo en BD SIGE
    // No existen en la tienda pero igual se actualiza el snapshot para que
    // no queden dando vueltas en la cola indefinidamente.
    // -------------------------------------------------------------------------
    foreach ($notInWoo as $sku) {
        $data = $skuToData[$sku];
        $precio = $db->real_escape_string($data['precio']);
        $stock = $db->real_escape_string($data['stock']);

        $db->query("UPDATE sige_prs_presho
                    SET prs_fecultactweb = NOW(),
                        prs_precvtaart = '$precio',
                        prs_disponible = '$stock'
                    WHERE art_idarticulo = '$sku'");

        $results[] = ['sku' => $sku, 'status' => 'not_in_woo'];
    }

    $db->close();

    // remaining: cuántos productos siguen pendientes para la próxima llamada
    $remaining = $totalPendientes - count($productos);

    // El frontend/cron sigue llamando mientras remaining > 0
    echo json_encode([
        'success'    => true,
        'processed'  => count($productos),  // cuántos tomó este lote
        'successful' => $successful,         // actualizados OK en Woo
        'not_in_woo' => count($notInWoo),   // no existían en la tienda
        'failed'     => $failed,             // fallaron en el batch de Woo
        'remaining'  => max(0, $remaining), // pendientes para el próximo lote
        'details'    => $results             // detalle por SKU
    ]);

} catch (Exception $e) {
    // Error no capturado → responde 500 con el mensaje
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}