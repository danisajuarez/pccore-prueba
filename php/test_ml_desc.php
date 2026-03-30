<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/mercadolibre.php';

$itemId = 'MLA1395914659'; // Random ML Item. Or we can just search.
if (isset($argv[1])) {
    $itemId = $argv[1];
} else {
    // Search a random item
    $res = mlRequest('/sites/MLA/search?q=monitor+samsung&limit=1');
    $itemId = $res['data']['results'][0]['id'] ?? null;
}

if ($itemId) {
    echo "Item ID: $itemId\n";
    $descRes = mlRequest("/items/{$itemId}/description");
    print_r($descRes);
    
    // Also, try with the full function
    echo "\n\nbuscarDatosProductoML:\n";
    print_r(buscarDatosProductoML('LS19D300', 'LS19D300', 'Monitor Samsung 19'));
} else {
    echo "No item found.\n";
}
