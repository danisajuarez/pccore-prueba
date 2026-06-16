<?php
// DEBUG: Ver paso a paso donde se traba
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

$key = $_GET['key'] ?? '';
if ($key !== 'pccore-sync-2024') {
    die("ERROR: API Key invalida");
}

echo "PASO 1: Inicio OK\n";
flush();

// Conexion BD
echo "PASO 2: Conectando a BD produccion...\n";
flush();

try {
    $conn = new mysqli('186.136.6.20', 'demo', 'demo', 'pccore', 3307);
    if ($conn->connect_error) {
        die("ERROR BD: " . $conn->connect_error);
    }
    echo "PASO 2: BD conectada OK\n";
    flush();
} catch (Exception $e) {
    die("ERROR BD: " . $e->getMessage());
}

// Contar diferencias
echo "PASO 3: Contando diferencias...\n";
flush();

$countSql = "SELECT COUNT(*) as total
             FROM sige_prs_presho
             INNER JOIN sige_art_articulo ON sige_art_articulo.ART_IDArticulo = sige_prs_presho.art_idarticulo
             WHERE sige_prs_presho.pal_precvtaart <> sige_prs_presho.prs_precvtaart
                OR sige_prs_presho.prs_disponible <> sige_prs_presho.ads_disponible";

$result = $conn->query($countSql);
if (!$result) {
    die("ERROR QUERY: " . $conn->error);
}

$total = $result->fetch_assoc()['total'];
echo "PASO 3: Diferencias encontradas = $total\n";
flush();

if ($total == 0) {
    echo "PASO 4: Sin cambios, terminado.\n";
    $conn->close();
    exit;
}

// Si hay diferencias, mostrar algunas
echo "PASO 4: Obteniendo primeros 3 productos...\n";
flush();

$sql = "SELECT sige_prs_presho.art_idarticulo as sku,
               sige_prs_presho.pal_precvtaart as precio
        FROM sige_prs_presho
        INNER JOIN sige_art_articulo ON sige_art_articulo.ART_IDArticulo = sige_prs_presho.art_idarticulo
        WHERE sige_prs_presho.pal_precvtaart <> sige_prs_presho.prs_precvtaart
           OR sige_prs_presho.prs_disponible <> sige_prs_presho.ads_disponible
        LIMIT 3";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "  - SKU: {$row['sku']}, Precio: {$row['precio']}\n";
    }
}

echo "PASO 5: Probando WooCommerce API...\n";
flush();

require_once __DIR__ . '/../config/load_secrets.php';
$wc_url = pccore_secret('WC_URL') . '/products?per_page=1'
    . '&consumer_key=' . urlencode(pccore_secret('WC_KEY'))
    . '&consumer_secret=' . urlencode(pccore_secret('WC_SECRET'));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $wc_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "ERROR WOO: $error\n";
} else {
    echo "PASO 5: WooCommerce OK (HTTP $httpCode)\n";
}

$conn->close();
echo "\nDIAGNOSTICO COMPLETO.\n";
