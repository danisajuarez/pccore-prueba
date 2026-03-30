<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
$_GET['key'] = 'pccoreprueba-sync-2024';
$_GET['sku'] = '214763';  // Probar con un solo producto

ob_start();
require __DIR__ . '/api/auto-sync.php';
$output = ob_get_clean();

echo "=== RESPUESTA DEL SYNC ===\n";
echo $output . "\n\n";

echo "=== DECODIFICADO ===\n";
$data = json_decode($output, true);
print_r($data);
