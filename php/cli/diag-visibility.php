<?php
/**
 * CLI: Diagnosticar visibilidad de productos en WooCommerce
 *
 * Uso: php cli/diag-visibility.php [cliente_id]
 *
 * Sin argumentos lista los clientes disponibles.
 * Con cliente_id hace el diagnóstico para ese cliente.
 */

require_once __DIR__ . '/../config/master.php';

// Conexión a BD master
$masterDb = new mysqli(MASTER_DB_HOST, MASTER_DB_USER, MASTER_DB_PASS, MASTER_DB_NAME, MASTER_DB_PORT);

if ($masterDb->connect_error) {
    die("Error conectando a BD master: " . $masterDb->connect_error . "\n");
}

$clienteId = $argv[1] ?? null;
$action = $argv[2] ?? 'diagnose'; // diagnose, fix_all

if (!$clienteId) {
    // Listar clientes disponibles
    echo "=== CLIENTES DISPONIBLES ===\n\n";
    $result = $masterDb->query("SELECT id, nombre, wc_url FROM clientes WHERE activo = 1");
    while ($row = $result->fetch_assoc()) {
        echo "  {$row['id']}: {$row['nombre']}\n";
        echo "     WC: {$row['wc_url']}\n\n";
    }
    echo "\nUso: php cli/diag-visibility.php <cliente_id> [diagnose|fix_all]\n";
    exit(0);
}

// Obtener configuración del cliente
$stmt = $masterDb->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->bind_param("i", $clienteId);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();

if (!$cliente) {
    die("Cliente no encontrado: $clienteId\n");
}

if (empty($cliente['wc_url']) || empty($cliente['wc_key']) || empty($cliente['wc_secret'])) {
    die("Cliente sin credenciales de WooCommerce completas\n");
}

echo "=== DIAGNOSTICO DE VISIBILIDAD ===\n";
echo "Cliente: {$cliente['nombre']}\n";
echo "WooCommerce: {$cliente['wc_url']}\n\n";

function wcRequest($config, $endpoint, $method = 'GET', $data = null) {
    $url = $config['wc_url'] . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . urlencode($config['wc_key']) . '&consumer_secret=' . urlencode($config['wc_secret']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, $method === 'GET' ? 30 : 120);

    if ($method === 'PUT' || $method === 'POST') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("CURL Error: $error");
    }

    if ($httpCode >= 400) {
        throw new Exception("WooCommerce API error: $httpCode - " . substr($response, 0, 200));
    }

    return json_decode($response, true);
}

try {
    $problematicos = [];
    $page = 1;
    $perPage = 100;
    $totalScanned = 0;

    echo "Escaneando productos...\n";

    do {
        $products = wcRequest($cliente, "/products?status=publish&per_page=$perPage&page=$page");
        $count = count($products);
        $totalScanned += $count;

        foreach ($products as $product) {
            if ($product['catalog_visibility'] !== 'visible') {
                $problematicos[] = [
                    'id' => $product['id'],
                    'sku' => $product['sku'],
                    'name' => substr($product['name'], 0, 50),
                    'visibility' => $product['catalog_visibility'],
                    'categories' => count($product['categories'] ?? [])
                ];
            }
        }

        echo "  Página $page: $count productos (total: $totalScanned)\n";
        $page++;

    } while (count($products) === $perPage && $page <= 20);

    echo "\n=== RESULTADOS ===\n";
    echo "Total escaneados: $totalScanned\n";
    echo "Con visibilidad incorrecta: " . count($problematicos) . "\n\n";

    if (count($problematicos) > 0) {
        echo "PRODUCTOS PROBLEMÁTICOS:\n";
        echo str_repeat("-", 80) . "\n";
        printf("%-8s %-15s %-12s %-5s %s\n", "ID", "SKU", "VISIBILIDAD", "CATS", "NOMBRE");
        echo str_repeat("-", 80) . "\n";

        foreach ($problematicos as $p) {
            printf("%-8s %-15s %-12s %-5s %s\n",
                $p['id'],
                substr($p['sku'], 0, 15),
                $p['visibility'],
                $p['categories'],
                $p['name']
            );
        }

        if ($action === 'fix_all') {
            echo "\n=== CORRIGIENDO PRODUCTOS ===\n";
            $fixed = 0;
            $errors = 0;

            foreach ($problematicos as $p) {
                try {
                    wcRequest($cliente, '/products/' . $p['id'], 'PUT', [
                        'catalog_visibility' => 'visible'
                    ]);
                    echo "  [OK] {$p['sku']} - corregido\n";
                    $fixed++;
                } catch (Exception $e) {
                    echo "  [ERROR] {$p['sku']} - {$e->getMessage()}\n";
                    $errors++;
                }
            }

            echo "\nCorregidos: $fixed / Errores: $errors\n";
        } else {
            echo "\nPara corregir ejecuta:\n";
            echo "  php cli/diag-visibility.php $clienteId fix_all\n";
        }
    } else {
        echo "Todos los productos tienen visibilidad correcta.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

$masterDb->close();
