<?php
/**
 * Página de Nuevo Producto - Buscar en ML y crear en WooCommerce
 */

require_once __DIR__ . '/../bootstrap.php';

// Requerir autenticación
if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}
checkSession();

// Cambiar headers para HTML
header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');

// Variables para el template
$pageTitle = 'Nuevo Producto - ' . strtoupper($CLIENTE_ID);
$clienteId = $CLIENTE_ID;
$userName = $_SESSION['user_name'] ?? 'Usuario';
$apiKey = $_SESSION['api_key'] ?? API_KEY;
$activePage = 'nuevo-producto';
$jsFile = '/public/js/nuevo-producto.js';

// Contenido del template
ob_start();
include __DIR__ . '/../templates/admin/nuevo-producto.php';
$content = ob_get_clean();

// Renderizar layout
include __DIR__ . '/../templates/layouts/main.php';
