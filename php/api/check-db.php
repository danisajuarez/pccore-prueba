<?php
/**
 * Script para verificar la base de datos actual
 */
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['cliente_config'])) {
    echo json_encode([
        'error' => 'No hay sesion activa',
        'mensaje' => 'Debes iniciar sesion primero'
    ], JSON_PRETTY_PRINT);
    exit;
}

$config = $_SESSION['cliente_config'];

echo json_encode([
    'cliente_id' => $config['id'] ?? 'N/A',
    'cliente_nombre' => $config['nombre'] ?? 'N/A',
    'bd_sige' => [
        'host' => $config['db_host'] ?? 'N/A',
        'nombre' => $config['db_name'] ?? 'N/A',
        'puerto' => $config['db_port'] ?? 3306,
        'usuario' => $config['db_user'] ?? 'N/A'
    ],
    'woocommerce_url' => $config['wc_url'] ?? 'N/A'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
