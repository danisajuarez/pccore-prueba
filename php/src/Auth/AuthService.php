<?php

namespace App\Auth;

use App\Config\AppConfig;
use App\Database\DatabaseService;
use Exception;

/**
 * Servicio de autenticación de usuarios
 */
class AuthService
{
    private DatabaseService $db;
    private SessionManager $session;
    private AppConfig $config;

    public function __construct(
        DatabaseService $db,
        SessionManager $session,
        AppConfig $config
    ) {
        $this->db = $db;
        $this->session = $session;
        $this->config = $config;
    }

    /**
     * Intentar login con credenciales
     *
     * @param string $username
     * @param string $password
     * @return array|false Datos del usuario si es exitoso, false si falla
     */
    public function attempt(string $username, string $password)
    {
        try {
            $sql = "SELECT USU_IDUsuario, USU_LogUsu, USU_DatosUsu, USU_Habilitado
                    FROM sige_usu_usuario
                    WHERE USU_LogUsu = ? AND USU_PassWord = ?";

            $result = $this->db->query($sql, 'ss', [$username, $password]);

            if ($result && $row = $result->fetch_assoc()) {
                // Verificar si el usuario está habilitado
                if ($row['USU_Habilitado'] !== 'S') {
                    return false;
                }

                return [
                    'USU_IDUsuario' => $row['USU_IDUsuario'],
                    'USU_LogUsu' => $row['USU_LogUsu'],
                    'USU_DatosUsu' => $row['USU_DatosUsu']
                ];
            }

            return false;

        } catch (Exception $e) {
            // Fallback a credenciales del config si falla la BD
            return $this->attemptFallback($username, $password);
        }
    }

    /**
     * Login de fallback usando credenciales del archivo de config
     */
    private function attemptFallback(string $username, string $password)
    {
        if ($username === $this->config->getAdminUser() &&
            $password === $this->config->getAdminPass()) {
            return [
                'USU_IDUsuario' => 1,
                'USU_LogUsu' => $username,
                'USU_DatosUsu' => 'Administrador'
            ];
        }

        return false;
    }

    /**
     * Realizar login completo
     */
    public function login(string $username, string $password): bool
    {
        $userData = $this->attempt($username, $password);

        if ($userData === false) {
            return false;
        }

        $this->session->login($userData);
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
     * Verificar si hay sesión activa
     */
    public function check(): bool
    {
        return $this->session->isValidForClient();
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
     * Obtener usuario actual
     */
    public function user(): ?array
    {
        return $this->session->getUser();
    }
}
