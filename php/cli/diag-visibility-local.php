<?php
/**
 * CLI: Diagnosticar visibilidad de productos - PCCore
 */

require_once __DIR__ . '/../config/load_secrets.php';
$config = [
    'wc_url' => pccore_secret('WC_URL'),
    'wc_key' => pccore_secret('WC_KEY'),
    'wc_secret' => pccore_secret('WC_SECRET')
];

$action = $argv[1] ?? 'diagnose';

echo "=== DIAGNOSTICO DE VISIBILIDAD - PCCORE ===\n\n";

function wcRequest($config, $endpoint, $method = 'GET', $data = null) {
    $url = $config['wc_url'] . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . urlencode($config['wc_key']) . '&consumer_secret=' . urlencode($config['wc_secret']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, $method === 'GET' ? 60 : 120);

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

    echo "Escaneando productos publicados...\n";

    do {
        $products = wcRequest($config, "/products?status=publish&per_page=$perPage&page=$page");
        $count = count($products);
        $totalScanned += $count;

        foreach ($products as $product) {
            // catalog_visibility: visible, catalog, search, hidden
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

        echo "  Pagina $page: $count productos (total: $totalScanned)\n";
        $page++;

    } while (count($products) === $perPage && $page <= 50);

    echo "\n=== RESULTADOS ===\n";
    echo "Total escaneados: $totalScanned\n";
    echo "Con visibilidad incorrecta: " . count($problematicos) . "\n\n";

    if (count($problematicos) > 0) {
        echo "PRODUCTOS PROBLEMATICOS:\n";
        echo str_repeat("-", 90) . "\n";
        printf("%-8s %-18s %-12s %-5s %s\n", "ID", "SKU", "VISIBILIDAD", "CATS", "NOMBRE");
        echo str_repeat("-", 90) . "\n";

        foreach ($problematicos as $p) {
            printf("%-8s %-18s %-12s %-5s %s\n",
                $p['id'],
                substr($p['sku'], 0, 18),
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
                    wcRequest($config, '/products/' . $p['id'], 'PUT', [
                        'catalog_visibility' => 'visible'
                    ]);
                    echo "  [OK] {$p['sku']} - corregido\n";
                    $fixed++;
                } catch (Exception $e) {
                    echo "  [ERROR] {$p['sku']} - {$e->getMessage()}\n";
                    $errors++;
                }
                usleep(200000); // 200ms entre requests
            }

            echo "\nCorregidos: $fixed / Errores: $errors\n";
        } else {
            echo "\nPara corregir todos ejecuta:\n";
            echo "  php cli/diag-visibility-local.php fix_all\n";
        }
    } else {
        echo "Todos los productos tienen visibilidad correcta.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
