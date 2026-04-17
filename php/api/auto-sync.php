<?php
/**
 * AUTO-SYNC: Híbrido - Busca individual, actualiza en batch
 *
 * Procesa productos con cambios de precio/stock:
 * - Busca cada SKU en WooCommerce (individual)
 * - Actualiza TODOS juntos en un batch
 *
 * Soporta:
 * - Llamadas con sesión activa (desde el navegador)
 * - Llamadas desde cron (sin sesión, usa API key para identificar cliente)
 */

error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(180);

header('Content-Type: application/json');

// Configuración Master DB (para cargar cliente sin sesión)
require_once __DIR__ . '/../config/master.php';

define('BATCH_SIZE', 50);

// Variable global para la config del cliente (sesión o cargada por API key)
$SYNC_CONFIG = null;

/**
 * Cargar configuración de cliente desde BD Master usando API key
 */
function loadClienteConfigFromKey($apiKey) {
    // Extraer cliente_id de la key (formato: "clienteid-sync-2024")
    if (!preg_match('/^(.+)-sync-2024$/', $apiKey, $matches)) {
        return null;
    }
    $clienteId = $matches[1];

    // Conectar a BD Master
    $masterDb = new mysqli(MASTER_DB_HOST, MASTER_DB_USER, MASTER_DB_PASS, MASTER_DB_NAME, MASTER_DB_PORT);
    if ($masterDb->connect_error) {
        throw new Exception("Error conectando a BD Master: " . $masterDb->connect_error);
    }
    $masterDb->set_charset('utf8');

    // Buscar cliente
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

    // Construir config igual que SessionManager
    return [
        'id' => $clienteData['TER_IdTercero'],
        'nombre' => $clienteData['TER_RazonSocialTer'],
        'db_host' => $clienteData['TWO_ServidorDBAnt'],
        'db_user' => $clienteData['TWO_UserDBAnt'],
        'db_pass' => $clienteData['TWO_PassDBAnt'],
        'db_port' => (int)($clienteData['TWO_PuertoDBAnt'] ?? 3306),
        'db_name' => $clienteData['TWO_NombreDBAnt'],
        'wc_url' => $clienteData['TWO_WooUrl'] ?? null,
        'wc_key' => $clienteData['TWO_WooKey'] ?? null,
        'wc_secret' => $clienteData['TWO_WooSecret'] ?? null,
        'lista_precio' => (int)($clienteData['TWO_ListaPrecio'] ?? 1),
        'deposito' => $clienteData['TWO_Deposito'] ?? '1',
    ];
}

// ============================================================================
// AUTENTICACIÓN: Soporta sesión O API key
// ============================================================================

$keyFromUrl = $_GET['key'] ?? '';

// Intentar cargar sesión primero
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['cliente_config'])) {
    // Tiene sesión activa
    $SYNC_CONFIG = $_SESSION['cliente_config'];
    $expectedKey = $SYNC_CONFIG['id'] . '-sync-2024';

    if ($keyFromUrl !== $expectedKey) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'API Key invalida']);
        exit;
    }
} else {
    // Sin sesión - intentar cargar por API key (para cron)
    if (empty($keyFromUrl)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'API Key requerida']);
        exit;
    }

    try {
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

// Validar que tenemos config
if (!$SYNC_CONFIG || empty($SYNC_CONFIG['wc_url'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuración de cliente incompleta']);
    exit;
}

// Función para request a WooCommerce - USA $SYNC_CONFIG
function wcRequest($endpoint, $method = 'GET', $data = null) {
    global $SYNC_CONFIG;

    if (!$SYNC_CONFIG) {
        throw new Exception("No hay configuración de cliente cargada");
    }

    // Validar que tenemos credenciales de WooCommerce
    if (empty($SYNC_CONFIG['wc_url']) || empty($SYNC_CONFIG['wc_key']) || empty($SYNC_CONFIG['wc_secret'])) {
        throw new Exception("Credenciales de WooCommerce incompletas");
    }

    $url = $SYNC_CONFIG['wc_url'] . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . urlencode($SYNC_CONFIG['wc_key']) . '&consumer_secret=' . urlencode($SYNC_CONFIG['wc_secret']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($method === 'PUT' || $method === 'POST') {
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
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
    // Conexión a BD SIGE del cliente (usando $SYNC_CONFIG)
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
    $db->set_charset('utf8');

    // Contar pendientes
    $countSql = "SELECT COUNT(*) as total
                 FROM sige_prs_presho s
                 INNER JOIN sige_art_articulo a ON a.ART_IDArticulo = s.art_idarticulo
                 WHERE s.pal_precvtaart <> s.prs_precvtaart
                    OR s.prs_disponible <> s.ads_disponible";

    $countResult = $db->query($countSql);
    $totalPendientes = $countResult ? $countResult->fetch_assoc()['total'] : 0;

    if ($totalPendientes == 0) {
        $db->close();
        echo json_encode(['success' => true, 'message' => 'Sin cambios detectados.', 'remaining' => 0]);
        exit;
    }

    // Traer UN lote de productos
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

    // Buscar IDs en WooCommerce y preparar batch
    $batchUpdate = [];
    $notInWoo = [];
    $skuToData = [];

    foreach ($productos as $prod) {
        $sku = $prod['sku'];
        $skuToData[$sku] = $prod;

        try {
            $wcProducts = wcRequest('/products?sku=' . urlencode($sku));
            $wcProduct = null;

            // Buscar producto con SKU exacto
            if (!empty($wcProducts)) {
                foreach ($wcProducts as $p) {
                    if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                        $wcProduct = $p;
                        break;
                    }
                }
            }

            if ($wcProduct !== null) {
                $batchUpdate[] = [
                    'id' => $wcProduct['id'],
                    'sku' => $sku,
                    'regular_price' => number_format((float)$prod['precio'], 2, '.', ''),
                    'sale_price' => '',
                    'manage_stock' => true,
                    'stock_quantity' => (int)$prod['stock'],
                    'stock_status' => ((int)$prod['stock'] > 0) ? 'instock' : 'outofstock',
                    'meta_data' => [
                        ['key' => '_price_no_taxes', 'value' => number_format((float)$prod['precio_sin_iva'], 2, '.', '')]
                    ]
                ];
            } else {
                $notInWoo[] = $sku;
            }
        } catch (Exception $e) {
            $notInWoo[] = $sku;
        }
    }

    // Hacer batch update a WooCommerce
    $successful = 0;
    $failed = 0;
    $results = [];

    if (!empty($batchUpdate)) {
        $wcPayload = array_map(function($item) {
            $clean = $item;
            unset($clean['sku']);
            return $clean;
        }, $batchUpdate);

        try {
            wcRequest('/products/batch', 'POST', ['update' => $wcPayload]);

            foreach ($batchUpdate as $item) {
                $sku = $item['sku'];
                $data = $skuToData[$sku];
                $precio = $db->real_escape_string($data['precio']);
                $stock = $db->real_escape_string($data['stock']);

                $db->query("UPDATE sige_prs_presho
                            SET prs_fecultactweb = NOW(),
                                prs_precvtaart = '$precio',
                                prs_disponible = '$stock'
                            WHERE art_idarticulo = '$sku'");

                $successful++;
                $results[] = ['sku' => $sku, 'status' => 'updated', 'price' => $item['regular_price'], 'stock' => $item['stock_quantity']];
            }
        } catch (Exception $e) {
            foreach ($batchUpdate as $item) {
                $failed++;
                $results[] = ['sku' => $item['sku'], 'status' => 'error', 'error' => $e->getMessage()];
            }
        }
    }

    // Marcar los que no están en WooCommerce
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

    $remaining = $totalPendientes - count($productos);

    echo json_encode([
        'success' => true,
        'processed' => count($productos),
        'successful' => $successful,
        'not_in_woo' => count($notInWoo),
        'failed' => $failed,
        'remaining' => max(0, $remaining),
        'details' => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}