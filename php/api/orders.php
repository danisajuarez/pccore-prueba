<?php
/**
 * API: Pedidos WooCommerce
 *
 * Consulta pedidos directamente desde la API de WooCommerce.
 * actions: list | detail
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

function wcOrdersRequest(string $endpoint): array
{
    if (!isset($_SESSION['cliente_config'])) {
        throw new Exception("No hay sesión de cliente activa");
    }

    $config = $_SESSION['cliente_config'];

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!empty($curlError)) {
        throw new Exception("CURL Error: " . $curlError);
    }

    if ($httpCode >= 400) {
        throw new Exception("WooCommerce API error: $httpCode - $response");
    }

    return json_decode($response, true) ?? [];
}

function formatOrder(array $o): array
{
    $billing = $o['billing'] ?? [];
    $shipping = $o['shipping'] ?? [];

    // Extraer DNI de meta_data
    $dni = '';
    foreach ($o['meta_data'] ?? [] as $meta) {
        if ($meta['key'] === '_billing_dni' || $meta['key'] === 'billing_dni') {
            $dni = $meta['value'];
            break;
        }
    }

    // Extraer info de Mercado Pago si existe
    $mpInfo = [];
    foreach ($o['meta_data'] ?? [] as $meta) {
        if (str_starts_with($meta['key'], 'Mercado Pago - ') && str_contains($meta['key'], 'installments')) {
            $mpInfo['cuotas'] = $meta['value'];
        }
        if (str_starts_with($meta['key'], 'Mercado Pago - ') && str_contains($meta['key'], 'installment_amount')) {
            $mpInfo['valor_cuota'] = $meta['value'];
        }
        if (str_starts_with($meta['key'], 'Mercado Pago - ') && str_contains($meta['key'], 'card_last_four_digits')) {
            $mpInfo['tarjeta_ultimos4'] = $meta['value'];
        }
    }

    // Formatear line_items
    $items = [];
    foreach ($o['line_items'] ?? [] as $item) {
        $items[] = [
            'id'         => $item['id'],
            'nombre'     => $item['name'],
            'sku'        => $item['sku'] ?? '',
            'product_id' => $item['product_id'],
            'cantidad'   => $item['quantity'],
            'subtotal'   => $item['subtotal'],
            'total'      => $item['total'],
            'imagen'     => $item['image']['src'] ?? '',
        ];
    }

    // Formatear shipping_lines
    $envio = [];
    foreach ($o['shipping_lines'] ?? [] as $sl) {
        $envio[] = [
            'metodo' => $sl['method_title'],
            'total'  => $sl['total'],
        ];
    }

    return [
        'id'               => $o['id'],
        'number'           => $o['number'],
        'status'           => $o['status'],
        'currency'         => $o['currency'],
        'currency_symbol'  => $o['currency_symbol'] ?? '$',
        'date_created'     => $o['date_created'],
        'date_paid'        => $o['date_paid'],
        'date_completed'   => $o['date_completed'],
        'total'            => $o['total'],
        'subtotal'         => number_format(
            (float)$o['total'] - (float)($o['shipping_total'] ?? 0) - (float)($o['total_tax'] ?? 0),
            2, '.', ''
        ),
        'shipping_total'   => $o['shipping_total'],
        'total_tax'        => $o['total_tax'],
        'payment_method'   => $o['payment_method'],
        'payment_title'    => $o['payment_method_title'],
        'customer_id'      => $o['customer_id'],
        'customer_note'    => $o['customer_note'],
        'billing'          => [
            'nombre'    => trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')),
            'empresa'   => $billing['company'] ?? '',
            'email'     => $billing['email'] ?? '',
            'telefono'  => $billing['phone'] ?? '',
            'dni'       => $dni,
            'direccion' => trim(($billing['address_1'] ?? '') . ' ' . ($billing['address_2'] ?? '')),
            'ciudad'    => $billing['city'] ?? '',
            'provincia' => $billing['state'] ?? '',
            'cp'        => $billing['postcode'] ?? '',
            'pais'      => $billing['country'] ?? '',
        ],
        'shipping'         => [
            'nombre'    => trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')),
            'direccion' => trim(($shipping['address_1'] ?? '') . ' ' . ($shipping['address_2'] ?? '')),
            'ciudad'    => $shipping['city'] ?? '',
            'provincia' => $shipping['state'] ?? '',
            'cp'        => $shipping['postcode'] ?? '',
            'pais'      => $shipping['country'] ?? '',
        ],
        'items'            => $items,
        'envio_lineas'     => $envio,
        'mp_info'          => $mpInfo,
    ];
}

/**
 * Convierte un string UTF-8 a latin1 si el servidor DB lo requiere.
 * Necesario para servidores MySQL legacy con character_set_database=latin1.
 */
function dbStr(string $s, bool $latin1): string
{
    if (!$latin1) return $s;
    return mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
}

/**
 * Guarda un pedido WC en las tablas SIGE (eml, dml, vml).
 * Usa INSERT ... ON DUPLICATE KEY UPDATE para ser idempotente.
 */
function guardarEnSige(array $o, mysqli $db): void
{
    // Detectar si la conexión es latin1 (servidores MySQL legacy)
    $res    = $db->query("SELECT @@character_set_connection as cs");
    $row    = $res ? $res->fetch_assoc() : null;
    $latin1 = $row && strpos($row['cs'], 'latin') !== false;
    $billing  = $o['billing']  ?? [];
    $shipping = $o['shipping'] ?? [];

    // --- Extraer meta_data relevante ---
    $dni          = '';
    $isVatExempt  = 'no';
    $mpPaymentId  = '';
    $mpCuotas     = 0;
    $mpValorCuota = 0.0;

    foreach ($o['meta_data'] ?? [] as $meta) {
        $key = $meta['key'];
        $val = $meta['value'];
        if ($key === '_billing_dni' || $key === 'billing_dni')            $dni         = $val;
        if ($key === 'is_vat_exempt')                                     $isVatExempt = $val;
        if ($key === '_Mercado_Pago_Payment_IDs')                         $mpPaymentId = $val;
        if (strpos($key, 'installments') !== false && strpos($key, 'amount') === false) $mpCuotas     = (int)$val;
        if (strpos($key, 'installment_amount') !== false)                            $mpValorCuota = (float)$val;
    }

    $cancelado = ($o['status'] === 'cancelled') ? 'S' : 'N';

    // Mapeo códigos ISO WooCommerce → nombres de provincia SIGE
    static $provincias = [
        'B' => 'Buenos Aires',      'C' => 'Capital Federal',   'K' => 'Catamarca',
        'H' => 'Chaco',             'U' => 'Chubut',             'X' => 'Córdoba',
        'W' => 'Corrientes',        'E' => 'Entre Ríos',         'P' => 'Formosa',
        'Y' => 'Jujuy',             'L' => 'La Pampa',           'F' => 'La Rioja',
        'M' => 'Mendoza',           'N' => 'Misiones',           'Q' => 'Neuquén',
        'R' => 'Río Negro',         'A' => 'Salta',              'J' => 'San Juan',
        'D' => 'San Luis',          'Z' => 'Santa Cruz',         'S' => 'Santa Fe',
        'G' => 'Sgo. del Estero',   'V' => 'Tierra del Fuego',   'T' => 'Tucumán',
    ];

    $domBilling     = dbStr(trim(($billing['address_1'] ?? '') . ' ' . ($billing['address_2'] ?? '')), $latin1);
    $domShipping    = dbStr(trim(($shipping['address_1'] ?? '') . ' ' . ($shipping['address_2'] ?? '')), $latin1);
    $nombreCompleto = dbStr(trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')), $latin1);

    // IVA: mapear is_vat_exempt → nombre SIGE real
    if ($isVatExempt === 'yes') {
        $ivaNombre = 'Exento';
    } else {
        // Sin CUIT o customer_id=0 → Consumidor Final; con CUIT de 11 dígitos → RI
        $cuitLimpio = preg_replace('/\D/', '', $dni);
        $ivaNombre  = (strlen($cuitLimpio) === 11) ? 'Responsable Inscripto' : 'Consumidor Final';
    }

    // LOC: buscar ID de localidad por nombre de ciudad en sige_loc_localidad
    $buscarLoc = function(string $ciudad) use ($db): string {
        if (empty($ciudad)) return '0';
        $stmt = $db->prepare("SELECT LOC_IDLocalidad FROM sige_loc_localidad WHERE LOC_NomLocalidad = ? LIMIT 1");
        $stmt->bind_param('s', $ciudad);
        $stmt->execute();
        $stmt->bind_result($locId);
        $found = $stmt->fetch() ? (string)$locId : '0';
        $stmt->close();
        return $found;
    };

    $locIdBilling  = $buscarLoc($billing['city']  ?? '');
    $locIdShipping = $buscarLoc($shipping['city'] ?? '');

    // ---- sige_eml_encmerlib (encabezado) ----
    $stmt = $db->prepare("
        INSERT INTO sige_eml_encmerlib
            (EML_IdEml, TCP_IDTipoComp, EML_IdPackML, EML_Fecha, EML_FechaDescarga,
             TER_IdTercero, TER_RazonSocialTer, TER_CUITTer, IVA_NomIVA,
             TER_DomicilioTer, LOC_IDLocalidad, LOC_NomLocalidad, PRO_NomProvincia,
             TER_DomicilioTerEnvio, LOC_IDLocalidadEnvio, LOC_NomLocalidadEnvio, PRO_NomProvinciaEnvio,
             EML_Total, EML_Cancelado, EML_CostoEnvio)
        VALUES (?, 61, ?, ?, NOW(), ?, ?, ?, ?,  ?, ?, ?, ?,  ?, ?, ?, ?,  ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            EML_FechaDescarga     = NOW(),
            TER_RazonSocialTer    = VALUES(TER_RazonSocialTer),
            TER_CUITTer           = VALUES(TER_CUITTer),
            IVA_NomIVA            = VALUES(IVA_NomIVA),
            TER_DomicilioTer      = VALUES(TER_DomicilioTer),
            LOC_IDLocalidad       = VALUES(LOC_IDLocalidad),
            LOC_NomLocalidad      = VALUES(LOC_NomLocalidad),
            PRO_NomProvincia      = VALUES(PRO_NomProvincia),
            TER_DomicilioTerEnvio = VALUES(TER_DomicilioTerEnvio),
            LOC_IDLocalidadEnvio  = VALUES(LOC_IDLocalidadEnvio),
            LOC_NomLocalidadEnvio = VALUES(LOC_NomLocalidadEnvio),
            PRO_NomProvinciaEnvio = VALUES(PRO_NomProvinciaEnvio),
            EML_Total             = VALUES(EML_Total),
            EML_Cancelado         = VALUES(EML_Cancelado),
            EML_CostoEnvio        = VALUES(EML_CostoEnvio)
    ");

    $fechaCreada = str_replace('T', ' ', $o['date_created'] ?? '');
    $customerId  = (int)($o['customer_id'] ?? 0);
    $total       = (float)($o['total'] ?? 0);
    $costoEnvio  = (float)($o['shipping_total'] ?? 0);
    $ciudadBilling  = dbStr($billing['city']  ?? '', $latin1);
    $ciudadShipping = dbStr($shipping['city'] ?? '', $latin1);
    $codProvB       = strtoupper(trim($billing['state']  ?? ''));
    $codProvS       = strtoupper(trim($shipping['state'] ?? ''));
    $provBilling    = dbStr($provincias[$codProvB] ?? $codProvB, $latin1);
    $provShipping   = dbStr($provincias[$codProvS] ?? $codProvS, $latin1);

    $stmt->bind_param(
        'issississssssssdsd',
        $o['id'],
        $o['number'],
        $fechaCreada,
        $customerId,
        $nombreCompleto,
        $dni,
        $ivaNombre,
        $domBilling,
        $locIdBilling,
        $ciudadBilling,
        $provBilling,
        $domShipping,
        $locIdShipping,
        $ciudadShipping,
        $provShipping,
        $total,
        $cancelado,
        $costoEnvio
    );
    $stmt->execute();
    $stmt->close();

    // ---- sige_dml_detmerlib (líneas) ----
    // Borramos las líneas anteriores del pedido y las re-insertamos (más simple que upsert por renglon)
    $db->query("DELETE FROM sige_dml_detmerlib WHERE eml_ideml = " . (int)$o['id']);

    $stmtD = $db->prepare("
        INSERT INTO sige_dml_detmerlib
            (eml_ideml, dml_renglon, dml_idventaml, art_idarticulo, art_idarticuloml,
             art_desarticulo, dml_cantidad, dml_cantidadpend, dml_impounit, dml_impototal, dep_iddeposito)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $deposito = (int)(SIGE_DEPOSITO ?? 1);

    foreach ($o['line_items'] ?? [] as $idx => $item) {
        $renglon    = $idx + 1;
        $itemId     = (int)$item['id'];
        $sku        = (string)($item['sku'] ?? '');
        $productId  = (string)($item['product_id'] ?? '');
        $nombre     = dbStr((string)($item['name'] ?? ''), $latin1);
        $cantidad   = (float)($item['quantity'] ?? 1);
        $impoUnit   = (float)($item['price'] ?? 0);
        $impoTotal  = (float)($item['total'] ?? 0);

        $stmtD->bind_param(
            'iiisssddddi',
            $o['id'], $renglon, $itemId, $sku, $productId,
            $nombre, $cantidad, $cantidad, $impoUnit, $impoTotal, $deposito
        );
        $stmtD->execute();
    }
    $stmtD->close();

    // ---- sige_vml_varmerlib (variantes: pago MP + envío) ----
    $db->query("DELETE FROM sige_vml_varmerlib WHERE eml_ideml = " . (int)$o['id']);

    $stmtV = $db->prepare("
        INSERT INTO sige_vml_varmerlib
            (eml_ideml, dml_renglon, vml_renglonvml, val_idvalorml, vml_idoperacionml, vml_importe)
        VALUES (?, 0, ?, ?, ?, ?)
    ");

    $variantes = [];

    // Método de pago
    $variantes[] = ['payment_method', $o['payment_method'] ?? '', 0.0];

    // Datos Mercado Pago
    if ($mpPaymentId) {
        $variantes[] = ['mp_payment_id',  $mpPaymentId, 0.0];
        $variantes[] = ['mp_cuotas',       $mpPaymentId, (float)$mpCuotas];
        $variantes[] = ['mp_valor_cuota',  $mpPaymentId, $mpValorCuota];
    }

    // Costo de envío
    foreach ($o['shipping_lines'] ?? [] as $sl) {
        $variantes[] = ['costo_envio_' . ($sl['method_id'] ?? 'envio'), dbStr($sl['method_title'] ?? '', $latin1), (float)($sl['total'] ?? 0)];
    }

    foreach ($variantes as $vIdx => [$valId, $opId, $importe]) {
        $renglonVml = $vIdx + 1;
        $stmtV->bind_param('iissd', $o['id'], $renglonVml, $valId, $opId, $importe);
        $stmtV->execute();
    }
    $stmtV->close();
}

$action = $_GET['action'] ?? 'list';

// Capturar errores fatales que ocurran después de ob_end_clean
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Error interno: ' . $err['message']]);
    }
});

try {
    ob_end_clean();

    // Obtener conexión SIGE si está disponible
    $sigeDb = null;
    if (isAuthenticated()) {
        try {
            $dbService = \App\Container::get(\App\Database\DatabaseService::class);
            $sigeDb = $dbService->getConnection();
        } catch (\Exception $e) {
            // Sin conexión SIGE: seguimos sin guardar
        }
    }

    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID de pedido requerido']);
            exit;
        }
        $order = wcOrdersRequest("/orders/{$id}");

        if ($sigeDb) {
            try {
                guardarEnSige($order, $sigeDb);
            } catch (\Exception $e) {
                error_log("Error guardando pedido #{$id} en SIGE: " . $e->getMessage());
            }
        }

        echo json_encode(['success' => true, 'pedido' => formatOrder($order)]);
        exit;
    }

    // action === list
    $params = [];
    if (!empty($_GET['status']))   $params['status']   = $_GET['status'];
    if (!empty($_GET['page']))     $params['page']     = (int)$_GET['page'];
    if (!empty($_GET['per_page'])) $params['per_page'] = min((int)$_GET['per_page'], 100);
    if (!empty($_GET['search']))   $params['search']   = $_GET['search'];
    if (!empty($_GET['after']))    $params['after']    = $_GET['after'];
    if (!empty($_GET['before']))   $params['before']   = $_GET['before'];

    if (empty($params['per_page'])) $params['per_page'] = 20;
    if (empty($params['page']))     $params['page']     = 1;

    $endpoint = '/orders?' . http_build_query($params);
    $orders = wcOrdersRequest($endpoint);

    // Guardar cada pedido en SIGE
    if ($sigeDb) {
        foreach ($orders as $order) {
            try {
                guardarEnSige($order, $sigeDb);
            } catch (\Exception $e) {
                error_log("Error guardando pedido #{$order['id']} en SIGE: " . $e->getMessage());
            }
        }
    }

    $formatted = array_map('formatOrder', $orders);

    echo json_encode([
        'success'      => true,
        'pedidos'      => $formatted,
        'page'         => $params['page'],
        'per_page'     => $params['per_page'],
        'total'        => count($formatted),
        'guardado_sige' => $sigeDb !== null,
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
