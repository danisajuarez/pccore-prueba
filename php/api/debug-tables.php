<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $db = getDbConnection();

    // Buscar tablas que contengan "usu"
    $result = $db->query("SHOW TABLES LIKE '%usu%'");

    echo "<h2>Tablas que contienen 'usu':</h2>";
    echo "<ul>";
    while ($row = $result->fetch_array()) {
        echo "<li><strong>" . $row[0] . "</strong></li>";
    }
    echo "</ul>";

    // Si encontramos alguna, mostrar su estructura
    $result = $db->query("SHOW TABLES LIKE '%usu%'");
    while ($row = $result->fetch_array()) {
        $tabla = $row[0];
        echo "<h3>Estructura de: $tabla</h3>";
        $cols = $db->query("DESCRIBE $tabla");
        echo "<table border='1' cellpadding='5'><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
        while ($col = $cols->fetch_assoc()) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
        }
        echo "</table><br>";

        // Mostrar algunos registros de ejemplo (ocultando passwords)
        echo "<h4>Primeros 5 registros:</h4>";
        $datos = $db->query("SELECT * FROM $tabla LIMIT 5");
        if ($datos->num_rows > 0) {
            echo "<table border='1' cellpadding='5'><tr>";
            $fields = $datos->fetch_fields();
            foreach ($fields as $field) {
                echo "<th>{$field->name}</th>";
            }
            echo "</tr>";
            $datos->data_seek(0);
            while ($d = $datos->fetch_assoc()) {
                echo "<tr>";
                foreach ($d as $key => $val) {
                    // Ocultar campos de password
                    if (stripos($key, 'pass') !== false || stripos($key, 'clave') !== false) {
                        echo "<td>****</td>";
                    } else {
                        echo "<td>" . htmlspecialchars(substr($val ?? '', 0, 50)) . "</td>";
                    }
                }
                echo "</tr>";
            }
            echo "</table>";
        }
        echo "<hr>";
    }

    $db->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
