<?php

namespace App\Database;

use Exception;
use mysqli;

/**
 * Conexión a la Base de Datos Master
 *
 * Gestiona la conexión a la BD central donde se almacenan
 * los clientes y sus configuraciones (sige_two_terwoo).
 */
class MasterDatabase
{
    private static ?mysqli $connection = null;

    /**
     * Obtener conexión a la BD Master
     */
    public static function getConnection(): mysqli
    {
        if (self::$connection === null || !self::$connection->ping()) {
            self::connect();
        }

        return self::$connection;
    }

    /**
     * Establecer conexión
     */
    private static function connect(): void
    {
        // Cargar configuración si no está definida
        if (!defined('MASTER_DB_HOST')) {
            require_once dirname(__DIR__, 2) . '/config/master.php';
        }

        self::$connection = new mysqli(
            MASTER_DB_HOST,
            MASTER_DB_USER,
            MASTER_DB_PASS,
            MASTER_DB_NAME,
            MASTER_DB_PORT
        );

        if (self::$connection->connect_error) {
            throw new Exception("Error conectando a BD Master: " . self::$connection->connect_error);
        }

        self::$connection->set_charset("utf8");
    }

    /**
     * Buscar cliente por ID y password
     *
     * @param int $clienteId TER_IdTercero
     * @param string $password TWO_Pass
     * @return array|null Datos del cliente o null si no existe/inválido
     */
    public static function findCliente(int $clienteId, string $password): ?array
    {
        $conn = self::getConnection();

        $stmt = $conn->prepare("
            SELECT *
            FROM sige_two_terwoo
            WHERE TER_IdTercero = ?
              AND TWO_Pass = ?
              AND TWO_Activo = 'S'
        ");

        if (!$stmt) {
            throw new Exception("Error en prepare: " . $conn->error);
        }

        $stmt->bind_param('is', $clienteId, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        $cliente = $result->fetch_assoc();
        $stmt->close();

        return $cliente ?: null;
    }

    /**
     * Buscar cliente solo por ID (sin validar password)
     */
    public static function getClienteById(int $clienteId): ?array
    {
        $conn = self::getConnection();

        $stmt = $conn->prepare("
            SELECT *
            FROM sige_two_terwoo
            WHERE TER_IdTercero = ?
        ");

        if (!$stmt) {
            throw new Exception("Error en prepare: " . $conn->error);
        }

        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $result = $stmt->get_result();
        $cliente = $result->fetch_assoc();
        $stmt->close();

        return $cliente ?: null;
    }

    /**
     * Obtener configuración de Mercado Libre del cliente
     */
    public static function getClienteML(int $clienteId): ?array
    {
        $conn = self::getConnection();

        $stmt = $conn->prepare("
            SELECT *
            FROM sige_tml_termerlib
            WHERE TER_IdTercero = ?
        ");

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $result = $stmt->get_result();
        $mlConfig = $result->fetch_assoc();
        $stmt->close();

        return $mlConfig ?: null;
    }

    /**
     * Cerrar conexión
     */
    public static function close(): void
    {
        if (self::$connection !== null) {
            self::$connection->close();
            self::$connection = null;
        }
    }
}
