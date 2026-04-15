<?php

namespace App\Config;

use Exception;

/**
 * Gestiona la configuración del cliente basada en subdominios
 */
class AppConfig
{
    private string $clienteId;
    private array $config = [];
    private string $configPath;

    public function __construct(?string $clienteId = null, ?string $configPath = null)
    {
        $this->configPath = $configPath ?? dirname(__DIR__, 2) . '/config';
        $this->clienteId = $clienteId ?? $this->detectClienteId();
        $this->loadConfig();
    }

    /**
     * Detectar el ID del cliente desde el subdominio o dominio
     */
    private function detectClienteId(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Si es subdominio.antartidasige.com, extraer subdominio
        if (preg_match('/^([a-zA-Z0-9-]+)\.antartidasige\.com$/', $host, $matches)) {
            return strtolower($matches[1]);
        }

        // Mapeo de dominios personalizados a clientes
        $dominiosClientes = [
            'digitalpergamino.com.ar' => 'digitalpergamino',
            'dev.digitalpergamino.com.ar' => 'digitalpergamino',
            'www.digitalpergamino.com.ar' => 'digitalpergamino',
            'pccore.com.ar' => 'pccore',
            'www.pccore.com.ar' => 'pccore',
        ];

        // Verificar si el host coincide con algún dominio mapeado
        if (isset($dominiosClientes[$host])) {
            return $dominiosClientes[$host];
        }

        // Para desarrollo local o acceso directo, usar parámetro o default
        if (isset($_GET['cliente'])) {
            return strtolower($_GET['cliente']);
        }

        // Default para desarrollo
        return 'portalgcom';
    }

    /**
     * Cargar configuración desde archivo .txt del cliente
     */
    private function loadConfig(): void
    {
        $configFile = $this->configPath . '/' . $this->clienteId . '.txt';

        if (!file_exists($configFile)) {
            throw new Exception("Cliente '{$this->clienteId}' no encontrado");
        }

        $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), ';') === 0) {
                continue;
            }

            // Parsear key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $this->config[trim($key)] = trim($value);
            }
        }
    }

    /**
     * Obtener el ID del cliente
     */
    public function getClienteId(): string
    {
        return $this->clienteId;
    }

    /**
     * Obtener un valor de configuración
     */
    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Obtener toda la configuración
     */
    public function all(): array
    {
        return $this->config;
    }

    // Métodos de conveniencia para acceso rápido

    public function getWcUrl(): string
    {
        return $this->config['wc_url'] ?? '';
    }

    public function getWcKey(): string
    {
        return $this->config['wc_key'] ?? '';
    }

    public function getWcSecret(): string
    {
        return $this->config['wc_secret'] ?? '';
    }

    public function getDbHost(): string
    {
        return $this->config['db_host'] ?? 'localhost';
    }

    public function getDbPort(): int
    {
        return intval($this->config['db_port'] ?? 3306);
    }

    public function getDbUser(): string
    {
        return $this->config['db_user'] ?? '';
    }

    public function getDbPass(): string
    {
        return $this->config['db_pass'] ?? '';
    }

    public function getDbName(): string
    {
        return $this->config['db_name'] ?? '';
    }

    public function getListaPrecio(): int
    {
        return intval($this->config['lista_precio'] ?? 1);
    }

    public function getDeposito(): int
    {
        return intval($this->config['deposito'] ?? 1);
    }

    public function getAdminUser(): string
    {
        return $this->config['admin_user'] ?? '';
    }

    public function getAdminPass(): string
    {
        return $this->config['admin_pass'] ?? '';
    }

    /**
     * Obtener API Key única del cliente
     */
    public function getApiKey(): string
    {
        return $this->clienteId . '-sync-2024';
    }
}
