<?php
/**
 * Debug: Listar tablas y columnas de la base de datos
 */

require_once __DIR__ . '/../config.php';
checkAuth();

try {
    $db = getDbConnection();

    $dbName = DB_NAME;

    // Listar todas las tablas
    $result = $db->query("SHOW TABLES");

    $tables = [];
    while ($row = $result->fetch_array()) {
        $tableName = $row[0];

        // Obtener columnas de cada tabla
        $columnsResult = $db->query("DESCRIBE `$tableName`");
        $columns = [];
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = [
                'name' => $col['Field'],
                'type' => $col['Type'],
                'key' => $col['Key']
            ];
        }

        // Contar registros
        $countResult = $db->query("SELECT COUNT(*) as cnt FROM `$tableName`");
        $count = $countResult->fetch_assoc()['cnt'];

        $tables[] = [
            'table' => $tableName,
            'rows' => (int)$count,
            'columns' => $columns
        ];
    }

    $db->close();

    echo json_encode([
        'success' => true,
        'database' => $dbName,
        'total_tables' => count($tables),
        'tables' => $tables
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
