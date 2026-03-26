<?php

namespace App\Database;

use App\Config\AppConfig;
use Exception;
use mysqli;

/**
 * Servicio de conexión a base de datos MySQL
 */
class DatabaseService
{
    private AppConfig $config;
    private ?mysqli $connection = null;

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
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
            $this->config->getDbHost(),
            $this->config->getDbUser(),
            $this->config->getDbPass(),
            $this->config->getDbName(),
            $this->config->getDbPort()
        );

        if ($this->connection->connect_error) {
            throw new Exception("Error de conexión: " . $this->connection->connect_error);
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
