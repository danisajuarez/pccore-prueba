<?php
/**
 * Logout Multi-tenant
 *
 * Cierra la sesión usando el AuthService
 * y redirige al login.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Container;
use App\Auth\AuthService;

$auth = Container::get(AuthService::class);
$auth->logout();

header('Location: /api/login.php');
exit();
