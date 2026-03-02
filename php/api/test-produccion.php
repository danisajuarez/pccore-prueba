<?php
// ============================================================================
// TEST: Diagnostico de produccion - Ver que esta fallando
// ============================================================================

header('Content-Type: application/json');

$key = $_GET['key'] ?? '';
if ($key !== 'pccore-sync-2024') {
    http_response_code(401);
    echo json_encode(['error' => 'API Key invalida']);
    exit;
}

$resultado = ['pasos' => []];

// PASO 1: Conexion a BD de produccion
try {
    $conn = new mysqli('186.136.6.20', 'demo', 'demo', 'pccore', 3307);

    if ($conn->connect_error) {
        $resultado['pasos'][] = ['paso' => 'Conexion BD', 'status' => 'ERROR', 'error' => $conn->connect_error];
        echo json_encode($resultado, JSON_PRETTY_PRINT);
        exit;
    }
    $resultado['pasos'][] = ['paso' => 'Conexion BD', 'status' => 'OK'];
} catch (Exception $e) {
    $resultado['pasos'][] = ['paso' => 'Conexion BD', 'status' => 'ERROR', 'error' => $e->getMessage()];
    echo json_encode($resultado, JSON_PRETTY_PRINT);
    exit;
}

// PASO 2: Contar registros en sige_prs_presho
$r1 = $conn->query("SELECT COUNT(*) as total FROM sige_prs_presho");
$resultado['pasos'][] = [
    'paso' => 'Contar sige_prs_presho',
    'status' => $r1 ? 'OK' : 'ERROR',
    'total' => $r1 ? $r1->fetch_assoc()['total'] : $conn->error
];

// PASO 3: Contar registros en sige_art_articulo
$r2 = $conn->query("SELECT COUNT(*) as total FROM sige_art_articulo");
$resultado['pasos'][] = [
    'paso' => 'Contar sige_art_articulo',
    'status' => $r2 ? 'OK' : 'ERROR',
    'total' => $r2 ? $r2->fetch_assoc()['total'] : $conn->error
];

// PASO 4: Contar diferencias SIN JOIN (como en test)
$r3 = $conn->query("
    SELECT COUNT(*) as total
    FROM sige_prs_presho
    WHERE pal_precvtaart <> prs_precvtaart
       OR prs_disponible <> ads_disponible
");
$resultado['pasos'][] = [
    'paso' => 'Diferencias SIN JOIN',
    'status' => $r3 ? 'OK' : 'ERROR',
    'total' => $r3 ? $r3->fetch_assoc()['total'] : $conn->error
];

// PASO 5: Contar diferencias CON JOIN (como en produccion)
$r4 = $conn->query("
    SELECT COUNT(*) as total
    FROM sige_prs_presho
    INNER JOIN sige_art_articulo ON sige_art_articulo.ART_IDArticulo = sige_prs_presho.art_idarticulo
    WHERE sige_prs_presho.pal_precvtaart <> sige_prs_presho.prs_precvtaart
       OR sige_prs_presho.prs_disponible <> sige_prs_presho.ads_disponible
");
$resultado['pasos'][] = [
    'paso' => 'Diferencias CON JOIN',
    'status' => $r4 ? 'OK' : 'ERROR',
    'total' => $r4 ? $r4->fetch_assoc()['total'] : $conn->error
];

// PASO 6: Mostrar 3 ejemplos con diferencias (si hay)
$r5 = $conn->query("
    SELECT sige_prs_presho.art_idarticulo as sku,
           sige_prs_presho.pal_precvtaart as precio_lista,
           sige_prs_presho.prs_precvtaart as precio_sync,
           sige_prs_presho.ads_disponible as stock_actual,
           sige_prs_presho.prs_disponible as stock_sync
    FROM sige_prs_presho
    INNER JOIN sige_art_articulo ON sige_art_articulo.ART_IDArticulo = sige_prs_presho.art_idarticulo
    WHERE sige_prs_presho.pal_precvtaart <> sige_prs_presho.prs_precvtaart
       OR sige_prs_presho.prs_disponible <> sige_prs_presho.ads_disponible
    LIMIT 3
");

$ejemplos = [];
if ($r5) {
    while ($row = $r5->fetch_assoc()) {
        $ejemplos[] = $row;
    }
}
$resultado['ejemplos_diferencias'] = $ejemplos;

// PASO 7: Test WooCommerce API
$wc_url = 'https://pccore.com.ar/wp-json/wc/v3/products?per_page=1&consumer_key=ck_28e04bbb3d5000fb9240cac6bb64ad2597aff0df&consumer_secret=cs_b6442994d793997f0f9c829b8cdf3c38b3231c28';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $wc_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$wc_response = curl_exec($ch);
$wc_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$wc_error = curl_error($ch);
curl_close($ch);

$resultado['pasos'][] = [
    'paso' => 'WooCommerce API',
    'status' => ($wc_code == 200) ? 'OK' : 'ERROR',
    'http_code' => $wc_code,
    'error' => $wc_error ?: null
];

$conn->close();

echo json_encode($resultado, JSON_PRETTY_PRINT);
