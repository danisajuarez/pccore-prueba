<?php
header('Content-Type: application/json');

echo json_encode([
    'key_recibida' => $_GET['key'] ?? 'NO RECIBIDA',
    'key_esperada' => 'pccore-sync-2024',
    'coincide' => ($_GET['key'] ?? '') === 'pccore-sync-2024'
]);
