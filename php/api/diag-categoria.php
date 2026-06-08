<?php
/**
 * Diagnóstico de categorías - Ver qué pasa con un SKU específico
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$sku = trim($_GET['sku'] ?? '');
if (empty($sku)) {
    echo json_encode(['error' => 'SKU requerido. Uso: ?sku=097855163561']);
    exit;
}

// Función wcRequest
function wcRequest($endpoint, $method = 'GET', $data = null) {
    $config = $_SESSION['cliente_config'];

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
    curl_close($ch);

    return json_decode($response, true);
}

try {
    $resultado = [
        'sku' => $sku,
        'fecha' => date('Y-m-d H:i:s')
    ];

    // 1. Buscar datos en SIGE
    $dbService = getSigeConnection();
    $db = $dbService->getConnection();

    $listaPrecio = SIGE_LISTA_PRECIO;
    $deposito = SIGE_DEPOSITO;

    $sql = "SELECT
                a.ART_IDArticulo as sku,
                a.ART_DesArticulo as nombre,
                lin.LIN_DesLinea as categoria,
                gli.gli_descripcion as supracategoria
            FROM sige_art_articulo a
            LEFT JOIN sige_lin_linea lin ON a.LIN_IDLinea = lin.LIN_IDLinea
            LEFT JOIN sige_gli_gruplin gli ON lin.GLI_IdGli = gli.gli_idgli
            WHERE TRIM(a.ART_IDArticulo) = ?";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    if ($row) {
        $resultado['sige'] = [
            'nombre' => $row['nombre'],
            'categoria' => $row['categoria'],
            'supracategoria' => $row['supracategoria']
        ];
    } else {
        $resultado['sige'] = 'NO ENCONTRADO EN SIGE';
    }

    // 2. Buscar en WooCommerce
    $wcProducts = wcRequest('/products?sku=' . urlencode($sku) . '&status=any');

    if (!empty($wcProducts)) {
        foreach ($wcProducts as $p) {
            if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                $resultado['woocommerce'] = [
                    'id' => $p['id'],
                    'nombre' => $p['name'],
                    'status' => $p['status'],
                    'categorias_actuales' => $p['categories'] ?? [],
                    'permalink' => $p['permalink']
                ];
                break;
            }
        }
    }

    if (!isset($resultado['woocommerce'])) {
        $resultado['woocommerce'] = 'NO ENCONTRADO EN WOOCOMMERCE';
    }

    // 3. Buscar las categorías de SIGE en WooCommerce
    if (isset($resultado['sige']['categoria']) && !empty($resultado['sige']['categoria'])) {
        $catName = $resultado['sige']['categoria'];
        $categorias = wcRequest('/products/categories?search=' . urlencode($catName) . '&per_page=100');

        $encontradas = [];
        foreach ($categorias as $cat) {
            if (strcasecmp($cat['name'], $catName) === 0) {
                $encontradas[] = [
                    'id' => $cat['id'],
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'parent' => $cat['parent'],
                    'count' => $cat['count']
                ];
            }
        }

        $resultado['categoria_en_woo'] = !empty($encontradas) ? $encontradas : 'NO EXISTE - SE CREARÁ';
    }

    // 4. Diagnóstico
    $resultado['diagnostico'] = [];

    if (isset($resultado['woocommerce']['categorias_actuales'])) {
        $catsActuales = $resultado['woocommerce']['categorias_actuales'];
        $catSige = $resultado['sige']['categoria'] ?? '';

        $tieneCategoria = false;
        foreach ($catsActuales as $cat) {
            if (strcasecmp($cat['name'], $catSige) === 0) {
                $tieneCategoria = true;
                break;
            }
        }

        if ($tieneCategoria) {
            $resultado['diagnostico'][] = "✓ El producto YA tiene la categoría '$catSige' asignada en WooCommerce";
            $resultado['diagnostico'][] = "⚠ Si no aparece en la web, puede ser un problema de CACHÉ o del TEMA";
        } else {
            $resultado['diagnostico'][] = "✗ El producto NO tiene la categoría '$catSige' asignada";
            $resultado['diagnostico'][] = "→ Usar 'Sincronizar Todo' para asignarla";
        }

        if (empty($catsActuales)) {
            $resultado['diagnostico'][] = "⚠ El producto no tiene NINGUNA categoría asignada";
        }
    }

    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
