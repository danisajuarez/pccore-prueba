<?php

namespace App\Config;

use Exception;

/**
 * Gestiona la configuración del cliente (Multi-tenant)
 *
 * Lee toda la configuración desde la sesión del cliente.
 * Los datos vienen de la tabla sige_two_terwoo en la BD Master.
 */
class AppConfig
{
    private string $clienteId;
    private array $config = [];

    public function __construct()
    {
        $this->loadConfigFromSession();
    }

    /**
     * Cargar configuración desde la sesión del cliente
     */
    private function loadConfigFromSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['cliente_config'])) {
            throw new Exception("No hay sesión de cliente activa. Debe iniciar sesión primero.");
        }

        $sessionConfig = $_SESSION['cliente_config'];

        // Guardar ID del cliente
        $this->clienteId = (string)($sessionConfig['id'] ?? '');

        // Mapear config de sesión a formato esperado por AppConfig
        $this->config = [
            // WooCommerce API
            'wc_url' => $sessionConfig['wc_url'] ?? '',
            'wc_key' => $sessionConfig['wc_key'] ?? '',
            'wc_secret' => $sessionConfig['wc_secret'] ?? '',

            // Base de datos WooCommerce
            'db_host' => $sessionConfig['woo_db_host'] ?? '',
            'db_port' => $sessionConfig['woo_db_port'] ?? 3306,
            'db_user' => $sessionConfig['woo_db_user'] ?? '',
            'db_pass' => $sessionConfig['woo_db_pass'] ?? '',
            'db_name' => $sessionConfig['woo_db_name'] ?? '',

            // Configuración SIGE
            'lista_precio' => $sessionConfig['lista_precio'] ?? 1,
            'deposito' => $sessionConfig['deposito'] ?? '1',

            // No hay admin_user/admin_pass - el login es por cliente
        ];
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
