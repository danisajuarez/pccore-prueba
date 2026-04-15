<?php

namespace App\Database;

use Exception;
use mysqli;

/**
 * Servicio de conexión a base de datos SIGE (Multi-tenant)
 *
 * Lee las credenciales de conexión desde la sesión del cliente.
 * Cada cliente tiene su propia BD SIGE configurada en sige_two_terwoo.
 */
class DatabaseService
{
    private ?mysqli $connection = null;
    private array $credentials = [];

    /**
     * Constructor
     *
     * @param array|null $credentials Credenciales manuales (opcional)
     *                                Si no se pasan, se leen de la sesión
     */
    public function __construct(?array $credentials = null)
    {
        if ($credentials !== null) {
            $this->credentials = $credentials;
        } else {
            $this->loadCredentialsFromSession();
        }
    }

    /**
     * Cargar credenciales desde la sesión
     */
    private function loadCredentialsFromSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['cliente_config'])) {
            throw new Exception("No hay sesión de cliente activa. Debe iniciar sesión primero.");
        }

        $config = $_SESSION['cliente_config'];

        $this->credentials = [
            'host' => $config['db_host'] ?? null,
            'user' => $config['db_user'] ?? null,
            'pass' => $config['db_pass'] ?? null,
            'name' => $config['db_name'] ?? null,
            'port' => $config['db_port'] ?? 3306,
        ];

        // Validar que tenemos todas las credenciales necesarias
        if (empty($this->credentials['host']) || empty($this->credentials['user']) ||
            empty($this->credentials['name'])) {
            throw new Exception("Configuración de BD incompleta para este cliente.");
        }
    }

    /**
     * Crear instancia con credenciales específicas (factory method)
     */
    public static function withCredentials(
        string $host,
        string $user,
        string $pass,
        string $name,
        int $port = 3306
    ): self {
        return new self([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'name' => $name,
            'port' => $port,
        ]);
    }

    /**
     * Obtener conexión a la base de datos
     * Crea una nueva conexión si no existe
     */
    public function getConnection(): mysqli
    {
        if ($this->connection === null || !$this->connection->ping()) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Establecer conexión a la base de datos
     */
    private function connect(): void
    {
        $this->connection = new mysqli(
            $this->credentials['host'],
            $this->credentials['user'],
            $this->credentials['pass'] ?? '',
            $this->credentials['name'],
            $this->credentials['port']
        );

        if ($this->connection->connect_error) {
            throw new Exception("Error de conexión a BD SIGE: " . $this->connection->connect_error);
        }

        $this->connection->set_charset("utf8");
    }

    /**
     * Ejecutar una query preparada con parámetros
     *
     * @param string $sql SQL con placeholders ?
     * @param string $types Tipos de parámetros (s=string, i=int, d=double, b=blob)
     * @param array $params Valores de los parámetros
     * @return mysqli_result|bool
     */
    public function query(string $sql, string $types = '', array $params = [])
    {
        $conn = $this->getConnection();

        if (empty($params)) {
            $result = $conn->query($sql);
            if ($result === false) {
                throw new Exception("Error en query: " . $conn->error);
            }
            return $result;
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error en prepare: " . $conn->error);
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();
        $stmt->close();

        return $result;
    }

    /**
     * Ejecutar query y obtener todos los resultados como array
     */
    public function fetchAll(string $sql, string $types = '', array $params = []): array
    {
        $result = $this->query($sql, $types, $params);

        if ($result === false || $result === true) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Ejecutar query y obtener una sola fila
     */
    public function fetchOne(string $sql, string $types = '', array $params = []): ?array
    {
        $result = $this->query($sql, $types, $params);

        if ($result === false || $result === true) {
            return null;
        }

        return $result->fetch_assoc();
    }

    /**
     * Escapar un valor para uso en SQL (para queries sin preparar)
     */
    public function escape(string $value): string
    {
        return $this->getConnection()->real_escape_string($value);
    }

    /**
     * Obtener el último ID insertado
     */
    public function lastInsertId(): int
    {
        return $this->getConnection()->insert_id;
    }

    /**
     * Obtener las credenciales actuales (para debug)
     */
    public function getCredentials(): array
    {
        return [
            'host' => $this->credentials['host'],
            'user' => $this->credentials['user'],
            'name' => $this->credentials['name'],
            'port' => $this->credentials['port'],
            // No exponemos el password
        ];
    }

    /**
     * Cerrar la conexión
     */
    public function close(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * Destructor - cierra la conexión automáticamente
     */
    public function __destruct()
    {
        $this->close();
    }
}
