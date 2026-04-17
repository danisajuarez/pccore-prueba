<?php
/**
 * Bootstrap Multi-tenant
 *
 * Centraliza la lógica de inicialización:
 * - Verifica sesión activa
 * - Recupera configuración del cliente
 * - Prepara conexiones dinámicas
 *
 * El resto de la app usa los servicios sin preocuparse por la configuración.
 */

// ============================================================================
// CARGA DE CLASES (sin Composer)
// ============================================================================

require_once __DIR__ . '/config/master.php';
require_once __DIR__ . '/src/Container.php';
require_once __DIR__ . '/src/Database/MasterDatabase.php';
require_once __DIR__ . '/src/Database/DatabaseService.php';
require_once __DIR__ . '/src/Auth/SessionManager.php';
require_once __DIR__ . '/src/Auth/AuthService.php';
require_once __DIR__ . '/src/Http/Response.php';

// Cargar clases adicionales solo si existen
$optionalClasses = [
    '/src/Auth/ApiKeyValidator.php',
    '/src/WooCommerce/WooCommerceClient.php',
    '/src/WooCommerce/ProductMapper.php',
    '/src/Sige/ProductRepository.php',
    '/src/Sige/SyncService.php',
    '/src/MercadoLibre/TokenManager.php',
    '/src/MercadoLibre/MercadoLibreClient.php',
    '/src/MercadoLibre/ImageSearchService.php',
];

foreach ($optionalClasses as $class) {
    $path = __DIR__ . $class;
    if (file_exists($path)) {
        require_once $path;
    }
}

use App\Container;
use App\Database\DatabaseService;
use App\Auth\SessionManager;
use App\Auth\AuthService;
use App\Http\Response;

// ============================================================================
// INICIAR SESIÓN
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// VERIFICAR ESTADO DE AUTENTICACIÓN
// ============================================================================

/**
 * Indica si hay un cliente autenticado con configuración cargada
 */
$CLIENTE_AUTENTICADO = isset($_SESSION['logged_in']) &&
                        $_SESSION['logged_in'] === true &&
                        isset($_SESSION['cliente_config']);

/**
 * Configuración del cliente actual (null si no está autenticado)
 */
$CLIENTE_CONFIG = $CLIENTE_AUTENTICADO ? $_SESSION['cliente_config'] : null;

/**
 * ID del cliente actual (null si no está autenticado)
 */
$CLIENTE_ID = $CLIENTE_AUTENTICADO ? $_SESSION['cliente_id'] : null;

// ============================================================================
// REGISTRAR SERVICIOS EN EL CONTAINER
// ============================================================================

// SessionManager - Siempre disponible
Container::register(SessionManager::class, function () {
    return new SessionManager();
});

// AuthService - Siempre disponible (para login/logout)
Container::register(AuthService::class, function () {
    return new AuthService(Container::get(SessionManager::class));
});

// Response - Siempre disponible
Container::register(Response::class, function () {
    return new Response();
});

// ============================================================================
// SERVICIOS QUE REQUIEREN AUTENTICACIÓN
// ============================================================================

if ($CLIENTE_AUTENTICADO) {

    // DatabaseService - Conexión a BD SIGE del cliente
    Container::register(DatabaseService::class, function () {
        // Lee credenciales automáticamente de $_SESSION['cliente_config']
        return new DatabaseService();
    });

    // WooCommerceClient - Solo si tiene credenciales configuradas
    if (class_exists('App\WooCommerce\WooCommerceClient')) {
        // Validar que tenemos TODAS las credenciales de WooCommerce antes de registrar
        if (!empty($CLIENTE_CONFIG['wc_url']) && !empty($CLIENTE_CONFIG['wc_key']) && !empty($CLIENTE_CONFIG['wc_secret'])) {
            Container::register(\App\WooCommerce\WooCommerceClient::class, function () use ($CLIENTE_CONFIG) {
                return new \App\WooCommerce\WooCommerceClient(
                    $CLIENTE_CONFIG['wc_url'],
                    $CLIENTE_CONFIG['wc_key'],
                    $CLIENTE_CONFIG['wc_secret']
                );
            });
        } else {
            // Si faltan credenciales, registrar un placeholder que lance error si se usa
            Container::register(\App\WooCommerce\WooCommerceClient::class, function () {
                throw new Exception("Credenciales de WooCommerce incompletas para este cliente");
            });
        }
    }

    // ProductMapper
    if (class_exists('App\WooCommerce\ProductMapper')) {
        Container::register(\App\WooCommerce\ProductMapper::class, function () {
            return new \App\WooCommerce\ProductMapper();
        });
    }

    // ProductRepository - Recibe conexión MySQLi directa (no DatabaseService)
    if (class_exists('App\Sige\ProductRepository')) {
        Container::register(\App\Sige\ProductRepository::class, function () use ($CLIENTE_CONFIG) {
            $dbService = Container::get(DatabaseService::class);
            return new \App\Sige\ProductRepository(
                $dbService->getConnection(),  // Pasar conexión MySQLi directa
                $CLIENTE_CONFIG['lista_precio'] ?? 1,
                $CLIENTE_CONFIG['deposito'] ?? 1
            );
        });
    }

    // SyncService
    if (class_exists('App\Sige\SyncService')) {
        Container::register(\App\Sige\SyncService::class, function () {
            return new \App\Sige\SyncService(
                Container::get(\App\Sige\ProductRepository::class),
                Container::get(\App\WooCommerce\WooCommerceClient::class),
                Container::get(DatabaseService::class)
            );
        });
    }

    // MercadoLibre - TokenManager, Client, ImageSearch
    if (class_exists('App\MercadoLibre\TokenManager')) {
        Container::register(\App\MercadoLibre\TokenManager::class, function () {
            return new \App\MercadoLibre\TokenManager();
        });
    }

    if (class_exists('App\MercadoLibre\MercadoLibreClient')) {
        Container::register(\App\MercadoLibre\MercadoLibreClient::class, function () {
            return new \App\MercadoLibre\MercadoLibreClient(
                Container::get(\App\MercadoLibre\TokenManager::class)
            );
        });
    }

    if (class_exists('App\MercadoLibre\ImageSearchService')) {
        Container::register(\App\MercadoLibre\ImageSearchService::class, function () {
            return new \App\MercadoLibre\ImageSearchService(
                Container::get(\App\MercadoLibre\MercadoLibreClient::class)
            );
        });
    }
}

// ============================================================================
// MARCAR COMO INICIALIZADO
// ============================================================================

Container::boot();

// ============================================================================
// FUNCIONES HELPER GLOBALES
// ============================================================================

/**
 * Verificar si hay un cliente autenticado
 */
function isAuthenticated(): bool {
    global $CLIENTE_AUTENTICADO;
    return $CLIENTE_AUTENTICADO;
}

/**
 * Obtener configuración del cliente actual
 */
function getClienteConfig(): ?array {
    global $CLIENTE_CONFIG;
    return $CLIENTE_CONFIG;
}

/**
 * Obtener un valor específico de la configuración del cliente
 */
function getConfig(string $key, $default = null) {
    global $CLIENTE_CONFIG;
    return $CLIENTE_CONFIG[$key] ?? $default;
}

/**
 * Obtener ID del cliente actual
 */
function getClienteId() {
    global $CLIENTE_ID;
    return $CLIENTE_ID;
}

/**
 * Requerir autenticación - redirige al login si no está autenticado
 */
function requireAuth(string $loginUrl = '/api/login.php'): void {
    if (!isAuthenticated()) {
        header('Location: ' . $loginUrl);
        exit();
    }
}

/**
 * Obtener la conexión a BD SIGE del cliente actual
 * @throws Exception si no está autenticado
 */
function getSigeConnection(): \App\Database\DatabaseService {
    if (!isAuthenticated()) {
        throw new Exception("Debe iniciar sesión para acceder a la base de datos");
    }
    return Container::get(DatabaseService::class);
}

// ============================================================================
// VARIABLES LEGACY PARA COMPATIBILIDAD
// ============================================================================

// Estas variables permiten que código antiguo siga funcionando
if ($CLIENTE_AUTENTICADO) {
    // Simular constantes del sistema anterior
    if (!defined('DB_HOST')) {
        define('DB_HOST', $CLIENTE_CONFIG['db_host'] ?? '');
        define('DB_PORT', $CLIENTE_CONFIG['db_port'] ?? 3306);
        define('DB_USER', $CLIENTE_CONFIG['db_user'] ?? '');
        define('DB_PASS', $CLIENTE_CONFIG['db_pass'] ?? '');
        define('DB_NAME', $CLIENTE_CONFIG['db_name'] ?? '');
        define('SIGE_LISTA_PRECIO', $CLIENTE_CONFIG['lista_precio'] ?? 1);
        define('SIGE_DEPOSITO', $CLIENTE_CONFIG['deposito'] ?? 1);
    }

    // WooCommerce (solo si está completa la configuración)
    if (!defined('WC_BASE_URL')) {
        // Validar que tenemos TODAS las credenciales antes de definir
        if (!empty($CLIENTE_CONFIG['wc_url']) && !empty($CLIENTE_CONFIG['wc_key']) && !empty($CLIENTE_CONFIG['wc_secret'])) {
            define('WC_BASE_URL', $CLIENTE_CONFIG['wc_url']);
            define('WC_CONSUMER_KEY', $CLIENTE_CONFIG['wc_key']);
            define('WC_CONSUMER_SECRET', $CLIENTE_CONFIG['wc_secret']);
        } else {
            // Si faltan credenciales, definir valores especiales que causarán error si se usan
            define('WC_BASE_URL', '');
            define('WC_CONSUMER_KEY', '');
            define('WC_CONSUMER_SECRET', '');
            error_log("WARN: Credenciales de WooCommerce incompletas para cliente {$CLIENTE_ID}");
        }
    }
}
