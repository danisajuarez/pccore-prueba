<?php

namespace App\Auth;

use App\Config\AppConfig;

/**
 * Gestiona las sesiones de usuario
 */
class SessionManager
{
    private AppConfig $config;

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
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
     * Verificar si la sesión pertenece al cliente actual
     */
    public function isValidForClient(): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        return isset($_SESSION['cliente_id']) &&
               $_SESSION['cliente_id'] === $this->config->getClienteId();
    }

    /**
     * Iniciar sesión de usuario
     */
    public function login(array $userData): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['cliente_id'] = $this->config->getClienteId();
        $_SESSION['user'] = $userData['USU_LogUsu'];
        $_SESSION['user_id'] = $userData['USU_IDUsuario'];
        $_SESSION['user_nombre'] = $userData['USU_DatosUsu'];
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
}
