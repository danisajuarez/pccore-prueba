<?php
session_start();

// Detectar cliente desde el subdominio (ej: pccore.antartidasige.com -> pccore)
function getClienteId() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Si es subdominio.antartidasige.com, extraer subdominio
    if (preg_match('/^([a-zA-Z0-9-]+)\.antartidasige\.com$/', $host, $matches)) {
        return strtolower($matches[1]);
    }

    // Para desarrollo local o acceso directo, usar parámetro o default
    if (isset($_GET['cliente'])) {
        return strtolower($_GET['cliente']);
    }

    // Default para desarrollo
    return 'portalgcom';
}

// Cargar configuración del cliente desde archivo .txt
function loadClientConfig($clienteId) {
    $configFile = __DIR__ . '/config/' . $clienteId . '.txt';

    if (!file_exists($configFile)) {
        throw new Exception("Cliente '$clienteId' no encontrado");
    }

    $config = [];
    $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), ';') === 0) continue;

        // Parsear key=value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }

    return $config;
}

// Cargar configuración
$CLIENTE_ID = getClienteId();
$CONFIG = loadClientConfig($CLIENTE_ID);

// Definir constantes desde la configuración
define('WC_BASE_URL', $CONFIG['wc_url']);
define('WC_CONSUMER_KEY', $CONFIG['wc_key']);
define('WC_CONSUMER_SECRET', $CONFIG['wc_secret']);

define('DB_HOST', $CONFIG['db_host']);
define('DB_PORT', intval($CONFIG['db_port']));
define('DB_USER', $CONFIG['db_user']);
define('DB_PASS', $CONFIG['db_pass']);
define('DB_NAME', $CONFIG['db_name']);

define('SIGE_LISTA_PRECIO', intval($CONFIG['lista_precio']));
define('SIGE_DEPOSITO', intval($CONFIG['deposito']));

define('ADMIN_USER', $CONFIG['admin_user']);
define('ADMIN_PASS', $CONFIG['admin_pass']);

// API Key única por cliente (generada desde el ID)
define('API_KEY', $CLIENTE_ID . '-sync-2024');

// Conexión a MySQL
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
    return $conn;
}

// Headers CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Manejar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar API Key (para APIs)
function checkAuth() {
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

// Verificar sesión (para páginas admin)
function checkSession() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: /api/login.php');
        exit();
    }

    // Verificar que la sesión es del mismo cliente
    global $CLIENTE_ID;
    if ($_SESSION['cliente_id'] !== $CLIENTE_ID) {
        session_destroy();
        header('Location: /api/login.php');
        exit();
    }
}

// Validar login contra credenciales del config
function validateLogin($user, $pass) {
    // Usar credenciales del archivo de configuracion
    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        return [
            'USU_IDUsuario' => 1,
            'USU_LogUsu' => $user,
            'USU_DatosUsu' => 'Administrador'
        ];
    }
    return false;
}

// Hacer request a WooCommerce
function wcRequest($endpoint, $method = 'GET', $data = null) {
    $url = WC_BASE_URL . $endpoint;
    $url .= (strpos($url, '?') === false ? '?' : '&');
    $url .= 'consumer_key=' . WC_CONSUMER_KEY . '&consumer_secret=' . WC_CONSUMER_SECRET;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($method === 'PUT' || $method === 'POST') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
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
