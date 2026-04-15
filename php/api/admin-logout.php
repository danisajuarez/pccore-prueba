<?php
/**
 * Logout Admin Productos
 *
 * Solo cierra la sesión de admin (sige_usu_usuario)
 * pero mantiene la sesión del cliente (sige_two_terwoo)
 */

require_once __DIR__ . '/../bootstrap.php';

// Limpiar solo las variables de admin
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_user_id']);
unset($_SESSION['admin_user']);
unset($_SESSION['admin_user_nombre']);

// Redirigir al index (sincronizador)
header('Location: /');
exit();
