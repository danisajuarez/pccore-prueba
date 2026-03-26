<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $db = getDbConnection();

    // Buscar tablas relacionadas con categorías
    echo "<h2>Tablas relacionadas con categorías/rubros/familias:</h2>";
    $tables = $db->query("SHOW TABLES");
    echo "<ul>";
    while ($row = $tables->fetch_array()) {
        $table = $row[0];
        if (stripos($table, 'categ') !== false ||
            stripos($table, 'rubro') !== false ||
            stripos($table, 'familia') !== false ||
            stripos($table, 'grupo') !== false) {
            echo "<li><strong>$table</strong></li>";
        }
    }
    echo "</ul><br>";

    // Ver estructura de sige_art_articulo para ver si tiene campos de categoría
    echo "<h2>Campos de categoría en sige_art_articulo:</h2>";
    $cols = $db->query("DESCRIBE sige_art_articulo");
    echo "<ul>";
    while ($col = $cols->fetch_assoc()) {
        $field = $col['Field'];
        if (stripos($field, 'categ') !== false ||
            stripos($field, 'rubro') !== false ||
            stripos($field, 'familia') !== false ||
            stripos($field, 'grupo') !== false) {
            echo "<li><strong>{$field}</strong> ({$col['Type']})</li>";
        }
    }
    echo "</ul>";

    $db->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
