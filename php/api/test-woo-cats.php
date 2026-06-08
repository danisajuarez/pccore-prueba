<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    die(json_encode(['error' => 'No autenticado']));
}

$config = $_SESSION['cliente_config'];
$url = $config['wc_url'] . "/products/categories?per_page=100";
$url .= "&consumer_key=" . $config['wc_key'] . "&consumer_secret=" . $config['wc_secret'];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$cats = json_decode($response, true);

// Simplificar output
$lista = [];
foreach ($cats as $cat) {
    $lista[] = [
        'id' => $cat['id'],
        'name' => $cat['name'],
        'parent' => $cat['parent'],
        'count' => $cat['count']
    ];
}

echo json_encode($lista, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
