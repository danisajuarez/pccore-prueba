<?php
// ============================================================================
// AUTO-SYNC PRUEBA: Version sin calculo de IVA para probar en BD de prueba
// ============================================================================

error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0);

header('Content-Type: application/json');

// Configuracion BD de prueba
$dbConfig = [
    'host' => 'localhost',
    'user' => 'u962801258_0Ov4s',
    'pass' => 'Dona2012',
    'db'   => 'u962801258_vUylQ'
];

// Configuracion de lotes
define('BATCH_SIZE', 50);
define('PAUSE_SECONDS', 3);

// Autenticacion simple
$key = $_GET['key'] ?? '';
if ($key !== 'pccore-sync-2024') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'API Key invalida']);
    exit;
}

// Modo test: solo simula, no actualiza WooCommerce
$modoTest = isset($_GET['test']);

function getDb($config) {
    $conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['db']);
    if ($conn->connect_error) {
        throw new Exception("Error de conexion: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
    return $conn;
}

try {
    $db = getDb($dbConfig);

    // Contar pendientes (SIN JOIN)
    $countSql = "SELECT COUNT(*) as total
                 FROM sige_prs_presho
                 WHERE pal_precvtaart <> prs_precvtaart
                    OR prs_disponible <> ads_disponible";

    $countResult = $db->query($countSql);
    $totalPendientes = $countResult ? $countResult->fetch_assoc()['total'] : 0;

    if ($totalPendientes == 0) {
        echo json_encode(['success' => true, 'message' => 'Sin cambios detectados.', 'remaining' => 0]);
        exit;
    }

    // Traer productos con diferencias (SIN JOIN, SIN IVA)
    $sql = "SELECT art_idarticulo, pal_precvtaart, ads_disponible
            FROM sige_prs_presho
            WHERE pal_precvtaart <> prs_precvtaart
               OR prs_disponible <> ads_disponible";

    $result = $db->query($sql);
    if (!$result) throw new Exception("Error en DB: " . $db->error);

    $productosACambiar = [];
    while ($row = $result->fetch_assoc()) {
        $productosACambiar[] = $row;
    }

    $db->close();

    $totalProductos = count($productosACambiar);
    $totalLotes = ceil($totalProductos / BATCH_SIZE);
    $successful = 0;
    $failed = 0;
    $results = [];
    $loteActual = 0;

    $lotes = array_chunk($productosACambiar, BATCH_SIZE);

    foreach ($lotes as $lote) {
        $loteActual++;

        foreach ($lote as $prod) {
            $sku = (string)$prod['art_idarticulo'];
            $precioNuevo = (float)$prod['pal_precvtaart'];
            $stockNuevo = (int)$prod['ads_disponible'];

            if ($modoTest) {
                // MODO TEST: Solo simula, no toca WooCommerce ni BD
                $results[] = [
                    'sku' => $sku,
                    'status' => 'simulated',
                    'price' => number_format($precioNuevo, 2, '.', ''),
                    'stock' => $stockNuevo,
                    'lote' => $loteActual
                ];
                $successful++;
            } else {
                // MODO REAL: Actualiza BD (marca como sincronizado)
                // No actualiza WooCommerce porque es prueba
                try {
                    $dbUpdate = getDb($dbConfig);
                    $updateSql = "UPDATE sige_prs_presho
                                  SET prs_fecultactweb = NOW(),
                                      prs_precvtaart = '" . $dbUpdate->real_escape_string($precioNuevo) . "',
                                      prs_disponible = '" . $dbUpdate->real_escape_string($stockNuevo) . "'
                                  WHERE art_idarticulo = '" . $dbUpdate->real_escape_string($sku) . "'";
                    $dbUpdate->query($updateSql);
                    $dbUpdate->close();

                    $results[] = [
                        'sku' => $sku,
                        'status' => 'updated_db_only',
                        'price' => number_format($precioNuevo, 2, '.', ''),
                        'stock' => $stockNuevo,
                        'lote' => $loteActual
                    ];
                    $successful++;
                } catch (Exception $e) {
                    $results[] = ['sku' => $sku, 'status' => 'error', 'error' => $e->getMessage()];
                    $failed++;
                }
            }

            usleep(100000); // 100ms entre productos
        }

        // Pausa entre lotes
        if ($loteActual < $totalLotes) {
            sleep(PAUSE_SECONDS);
        }
    }

    echo json_encode([
        'success' => true,
        'modo' => $modoTest ? 'TEST (simulacion)' : 'REAL (actualiza BD)',
        'total_productos' => $totalProductos,
        'total_lotes' => $totalLotes,
        'batch_size' => BATCH_SIZE,
        'pause_seconds' => PAUSE_SECONDS,
        'successful' => $successful,
        'failed' => $failed,
        'details' => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
