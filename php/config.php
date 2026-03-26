<?php
/**
 * Configuración del Sistema - Wrapper de Compatibilidad
 *
 * Este archivo mantiene la compatibilidad con el código legacy mientras
 * delega a los nuevos servicios del container.
 *
 * @deprecated Las funciones globales están obsoletas.
 *             Usar los servicios del Container en su lugar.
 */

// Iniciar sesión (mantener compatibilidad)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar autoloader si existe (después de composer install)
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    require_once __DIR__ . '/bootstrap.php';

    // Usar servicios del container
    $appConfig = \App\Container::get(\App\Config\AppConfig::class);
    $CLIENTE_ID = $appConfig->getClienteId();
    $CONFIG = $appConfig->all();
} else {
    // Fallback al código original si no hay autoloader
    $CLIENTE_ID = getClienteId();
    $CONFIG = loadClientConfig($CLIENTE_ID);
}

// Definir constantes para compatibilidad
if (!defined('WC_BASE_URL')) {
    define('WC_BASE_URL', $CONFIG['wc_url'] ?? '');
    define('WC_CONSUMER_KEY', $CONFIG['wc_key'] ?? '');
    define('WC_CONSUMER_SECRET', $CONFIG['wc_secret'] ?? '');

    define('DB_HOST', $CONFIG['db_host'] ?? 'localhost');
    define('DB_PORT', intval($CONFIG['db_port'] ?? 3306));
    define('DB_USER', $CONFIG['db_user'] ?? '');
    define('DB_PASS', $CONFIG['db_pass'] ?? '');
    define('DB_NAME', $CONFIG['db_name'] ?? '');

    define('SIGE_LISTA_PRECIO', intval($CONFIG['lista_precio'] ?? 1));
    define('SIGE_DEPOSITO', intval($CONFIG['deposito'] ?? 1));

    define('ADMIN_USER', $CONFIG['admin_user'] ?? '');
    define('ADMIN_PASS', $CONFIG['admin_pass'] ?? '');

    // API Key única por cliente
    define('API_KEY', $CLIENTE_ID . '-sync-2024');
}

// Headers CORS para APIs
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Manejar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================================
// FUNCIONES LEGACY - Mantener compatibilidad
// ============================================================================

/**
 * Detectar cliente desde el subdominio
 *
 * @deprecated Usar App\Config\AppConfig::getClienteId()
 */
function getClienteId() {
    // Verificar si el container está disponible
    if (class_exists('\App\Container') && \App\Container::isBooted()) {
        return \App\Container::get(\App\Config\AppConfig::class)->getClienteId();
    }

    // Fallback al código original
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    if (preg_match('/^([a-zA-Z0-9-]+)\.antartidasige\.com$/', $host, $matches)) {
        return strtolower($matches[1]);
    }

    if (isset($_GET['cliente'])) {
        return strtolower($_GET['cliente']);
    }

    return 'portalgcom';
}

/**
 * Cargar configuración del cliente desde archivo .txt
 *
 * @deprecated Usar App\Config\AppConfig
 */
function loadClientConfig($clienteId) {
    $configFile = __DIR__ . '/config/' . $clienteId . '.txt';

    if (!file_exists($configFile)) {
        throw new Exception("Cliente '$clienteId' no encontrado");
    }

    $config = [];
    $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), ';') === 0) continue;

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }

    return $config;
}

/**
 * Obtener conexión a la base de datos
 *
 * @deprecated Usar App\Database\DatabaseService
 */
function getDbConnection() {
    // Usar servicio si está disponible
    if (class_exists('\App\Container') && \App\Container::isBooted()) {
        return \App\Container::get(\App\Database\DatabaseService::class)->getConnection();
    }

    // Fallback al código original
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
    return $conn;
}

/**
 * Verificar API Key
 *
 * @deprecated Usar App\Auth\ApiKeyValidator
 */
function checkAuth() {
    // Usar servicio si está disponible
    if (class_exists('\App\Container') && \App\Container::isBooted()) {
        \App\Container::get(\App\Auth\ApiKeyValidator::class)->requireValid();
        return;
    }

    // Fallback al código original
    $headers = getallheaders();
    $apiKey = $headers['X-Api-Key'] ?? $headers['x-api-key'] ?? $headers['X-API-KEY'] ?? '';

    if (empty($apiKey)) {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    }

    if (empty($apiKey)) {
        $apiKey = $_GET['api_key'] ?? '';
    }

    if ($apiKey !== API_KEY) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'API Key inválida']);
        exit();
    }
}

/**
 * Verificar sesión
 *
 * @deprecated Usar App\Auth\AuthService::requireAuth()
 */
function checkSession() {
    // Usar servicio si está disponible
    if (class_exists('\App\Container') && \App\Container::isBooted()) {
        \App\Container::get(\App\Auth\AuthService::class)->requireAuth();
        return;
    }

    // Fallback al código original
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: /api/login.php');
        exit();
    }

    global $CLIENTE_ID;
    if ($_SESSION['cliente_id'] !== $CLIENTE_ID) {
        session_destroy();
        header('Location: /api/login.php');
        exit();
    }
}

/**
 * Validar login contra la base de datos
 *
 * @deprecated Usar App\Auth\AuthService::attempt()
 */
function validateLogin($user, $pass) {
    // Usar servicio si está disponible
    if (class_exists('\App\Container') && \App\Container::isBooted()) {
        return \App\Container::get(\App\Auth\AuthService::class)->attempt($user, $pass);
    }

    // Fallback al código original
    try {
        $conn = @getDbConnection();

        if (!$conn || $conn->connect_error) {
            throw new Exception("No se pudo conectar a la BD");
        }

        $stmt = $conn->prepare("SELECT USU_IDUsuario, USU_LogUsu, USU_DatosUsu, USU_Habilitado
                                FROM sige_usu_usuario
                                WHERE USU_LogUsu = ? AND USU_PassWord = ?");

        if (!$stmt) {
            throw new Exception("Error en prepare: " . $conn->error);
        }

        $stmt->bind_param("ss", $user, $pass);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($row['USU_Habilitado'] !== 'S') {
                $stmt->close();
                $conn->close();
                return false;
            }

            $stmt->close();
            $conn->close();
            return [
                'USU_IDUsuario' => $row['USU_IDUsuario'],
                'USU_LogUsu' => $row['USU_LogUsu'],
                'USU_DatosUsu' => $row['USU_DatosUsu']
            ];
        }

        $stmt->close();
        $conn->close();
        return false;

    } catch (Exception $e) {
        if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
            return [
                'USU_IDUsuario' => 1,
                'USU_LogUsu' => $user,
                'USU_DatosUsu' => 'Administrador'
            ];
        }
        return false;
    }
}

/**
 * Hacer request a WooCommerce
 *
 * @deprecated Usar App\WooCommerce\WooCommerceClient
 */
function wcRequest($endpoint, $method = 'GET', $data = null) {
    // Usar servicio si está disponible
    if (class_exists('\App\Container') && \App\Container::isBooted()) {
        return \App\Container::get(\App\WooCommerce\WooCommerceClient::class)
            ->request($endpoint, $method, $data);
    }

    // Fallback al código original
    $url = WC_BASE_URL . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . WC_CONSUMER_KEY . '&consumer_secret=' . WC_CONSUMER_SECRET;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($method === 'PUT' || $method === 'POST') {
        // Timeout más largo para PUT/POST (puede incluir imágenes)
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    } else {
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("CURL Error: $error");
    }

    if ($httpCode >= 400) {
        throw new Exception("WooCommerce API error: $httpCode - $response");
    }

    return json_decode($response, true);
}
