<?php

namespace App\Auth;

use App\Database\MasterDatabase;
use Exception;

/**
 * Servicio de autenticación Multi-tenant
 *
 * Valida usuarios contra la BD Master (sige_two_terwoo)
 * y carga la configuración del cliente en sesión.
 */
class AuthService
{
    private SessionManager $session;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
    }

    /**
     * Intentar login con ID de cliente y password
     *
     * @param int $clienteId TER_IdTercero
     * @param string $password TWO_Pass
     * @return array|false Datos del cliente si es exitoso, false si falla
     */
    public function attempt(int $clienteId, string $password)
    {
        try {
            $cliente = MasterDatabase::findCliente($clienteId, $password);

            if ($cliente === null) {
                return false;
            }

            return $cliente;

        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Realizar login completo
     *
     * @param int $clienteId TER_IdTercero
     * @param string $password TWO_Pass
     * @return bool
     */
    public function login(int $clienteId, string $password): bool
    {
        $clienteData = $this->attempt($clienteId, $password);

        if ($clienteData === false) {
            return false;
        }

        // Guardar datos del cliente en sesión
        $this->session->login($clienteData);

        return true;
    }

    /**
     * Cerrar sesión
     */
    public function logout(): void
    {
        $this->session->logout();
    }

    /**
     * Verificar si hay sesión activa válida
     */
    public function check(): bool
    {
        return $this->session->isValidSession();
    }

    /**
     * Requerir autenticación (redirige si no está logueado)
     */
    public function requireAuth(): void
    {
        if (!$this->check()) {
            header('Location: /api/login.php');
            exit();
        }
    }

    /**
     * Obtener datos del usuario/cliente actual
     */
    public function user(): ?array
    {
        return $this->session->getUser();
    }

    /**
     * Obtener configuración del cliente actual
     */
    public function getClienteConfig(): ?array
    {
        return $this->session->getClienteConfig();
    }

    /**
     * Obtener un valor específico de la config
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->session->getConfigValue($key, $default);
    }
}
