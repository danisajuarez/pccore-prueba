<?php

namespace App\Auth;

/**
 * Gestiona las sesiones de usuario (Multi-tenant)
 *
 * Guarda tanto los datos del usuario como la configuración
 * del cliente para conexiones dinámicas.
 */
class SessionManager
{
    public function __construct()
    {
        $this->ensureStarted();
    }

    /**
     * Asegurar que la sesión está iniciada
     */
    private function ensureStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Verificar si el usuario está logueado
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Verificar si hay una sesión válida con config de cliente
     */
    public function isValidSession(): bool
    {
        return $this->isLoggedIn() && isset($_SESSION['cliente_config']);
    }

    /**
     * Iniciar sesión de usuario con datos del cliente
     *
     * @param array $clienteData Datos de sige_two_terwoo
     */
    public function login(array $clienteData): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['cliente_id'] = $clienteData['TER_IdTercero'];
        $_SESSION['cliente_nombre'] = $clienteData['TER_RazonSocialTer'];

        // Credenciales WC desde BD o fallback desde archivo config
        $wcUrl = $clienteData['TWO_WooUrl'] ?? null;
        $wcKey = $clienteData['TWO_WooKey'] ?? null;
        $wcSecret = $clienteData['TWO_WooSecret'] ?? null;
        
        // Lista de precios y depósito - defaults de BD
        $listaPrecio = (int)($clienteData['TWO_ListaPrecio'] ?? 1);
        $deposito = (int)($clienteData['TWO_Deposito'] ?? 1);

        // SIEMPRE cargar el archivo de configuración del cliente
        // Este es la FUENTE DE VERDAD en prueba
        $configFile = $this->findConfigFile($clienteData['TER_RazonSocialTer']);
        $fileConfig = null;
        
        if ($configFile) {
            $fileConfig = $this->loadConfigFile($configFile);
            
            // Usar valores del archivo si está disponible (fallback a BD defaults si no)
            if (isset($fileConfig['lista_precio'])) {
                $listaPrecio = (int)$fileConfig['lista_precio'];
            }
            if (isset($fileConfig['deposito'])) {
                $deposito = (int)$fileConfig['deposito'];
            }
            
            // WC credentials: usar BD si existe, sino archivo
            if (empty($wcUrl)) {
                $wcUrl = $fileConfig['wc_url'] ?? null;
                $wcKey = $fileConfig['wc_key'] ?? null;
                $wcSecret = $fileConfig['wc_secret'] ?? null;
            }
        }

        // Guardar toda la configuración del cliente para conexiones dinámicas
        $_SESSION['cliente_config'] = [
            // Datos del cliente
            'id' => $clienteData['TER_IdTercero'],
            'nombre' => $clienteData['TER_RazonSocialTer'],

            // Credenciales BD SIGE (Antártida)
            'db_host' => $clienteData['TWO_ServidorDBAnt'],
            'db_user' => $clienteData['TWO_UserDBAnt'],
            'db_pass' => $clienteData['TWO_PassDBAnt'],
            'db_port' => (int)($clienteData['TWO_PuertoDBAnt'] ?? 3306),
            'db_name' => $clienteData['TWO_NombreDBAnt'],

            // Credenciales BD WooCommerce (si las necesita)
            'woo_db_host' => $clienteData['TWO_ServidorDBWoo'] ?? null,
            'woo_db_user' => $clienteData['TWO_UserDBWoo'] ?? null,
            'woo_db_pass' => $clienteData['TWO_PassDBWoo'] ?? null,
            'woo_db_port' => (int)($clienteData['TWO_PuertoDBWoo'] ?? 3306),
            'woo_db_name' => $clienteData['TWO_NombreDBWoo'] ?? null,

            // Credenciales API WooCommerce
            'wc_url' => $wcUrl,
            'wc_key' => $wcKey,
            'wc_secret' => $wcSecret,

            // Configuración SIGE - Lee del archivo .txt (fuente de verdad en prueba)
            'lista_precio' => $listaPrecio,
            'deposito' => $deposito,

            // Flags
            'sincronizar_auto' => ($clienteData['TWO_SincronizarAut'] ?? 'N') === 'S',
        ];

        // Guardar datos del usuario (en multi-tenant, el "usuario" es el cliente)
        $_SESSION['user'] = $clienteData['TER_IdTercero'];
        $_SESSION['user_id'] = $clienteData['TER_IdTercero'];
        $_SESSION['user_nombre'] = $clienteData['TER_RazonSocialTer'];
    }

    /**
     * Buscar archivo de configuración basado en el nombre del cliente
     */
    private function findConfigFile(string $clienteName): ?string
    {
        $configDir = dirname(__DIR__, 2) . '/config/';

        // Normalizar nombre: quitar espacios, lowercase, sin acentos
        $slug = strtolower(trim($clienteName));
        $slug = preg_replace('/[^a-z0-9]/', '', $slug);

        // Buscar archivo exacto
        $file = $configDir . $slug . '.txt';
        if (file_exists($file)) {
            return $file;
        }

        // Buscar parcial (ej: "Digital Pergamino" -> "digitalpergamino.txt")
        $files = glob($configDir . '*.txt');
        foreach ($files as $f) {
            $basename = strtolower(basename($f, '.txt'));
            if (strpos($slug, $basename) !== false || strpos($basename, $slug) !== false) {
                return $f;
            }
        }

        return null;
    }

    /**
     * Cargar configuración desde archivo .txt
     */
    private function loadConfigFile(string $path): array
    {
        $config = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === ';') {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }

        return $config;
    }

    /**
     * Cerrar sesión
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Obtener configuración del cliente actual
     */
    public function getClienteConfig(): ?array
    {
        return $_SESSION['cliente_config'] ?? null;
    }

    /**
     * Obtener un valor específico de la config del cliente
     */
    public function getConfigValue(string $key, $default = null)
    {
        return $_SESSION['cliente_config'][$key] ?? $default;
    }

    /**
     * Obtener datos del usuario actual
     */
    public function getUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['user'] ?? null,
            'nombre' => $_SESSION['user_nombre'] ?? null,
            'cliente_id' => $_SESSION['cliente_id'] ?? null,
        ];
    }

    /**
     * Obtener un valor de la sesión
     */
    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Establecer un valor en la sesión
     */
    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Actualizar un valor de la config del cliente en sesión
     */
    public function updateConfig(string $key, $value): void
    {
        if (isset($_SESSION['cliente_config'])) {
            $_SESSION['cliente_config'][$key] = $value;
        }
    }
}
