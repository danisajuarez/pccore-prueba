<?php
/**
 * AUTO-SYNC: Híbrido - Busca individual, actualiza en batch
 *
 * Procesa productos con cambios de precio/stock:
 * - Busca cada SKU en WooCommerce (individual)
 * - Actualiza TODOS juntos en un batch
 */

error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(180);

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

define('BATCH_SIZE', 50);

// Autenticación: requiere sesión activa
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Validar API Key contra el cliente en sesión
$keyFromUrl = $_GET['key'] ?? '';
$expectedKey = getClienteId() . '-sync-2024';
if ($keyFromUrl !== $expectedKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'API Key invalida']);
    exit;
}

// Función para request a WooCommerce (usa constantes del bootstrap)
function wcRequest($endpoint, $method = 'GET', $data = null) {
    $url = WC_BASE_URL . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . WC_CONSUMER_KEY . '&consumer_secret=' . WC_CONSUMER_SECRET;

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
    // Conexión a BD SIGE del cliente
    $dbService = getSigeConnection();
    $db = $dbService->getConnection();

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