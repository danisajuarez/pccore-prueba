<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $db = getDbConnection();

    echo "<h2>Estructura de sige_usu_usuario:</h2>";
    $cols = $db->query("DESCRIBE sige_usu_usuario");
    echo "<table border='1' cellpadding='8'><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
    while ($col = $cols->fetch_assoc()) {
        echo "<tr><td><strong>{$col['Field']}</strong></td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
    }
    echo "</table><br>";

    echo "<h3>Primeros 3 usuarios (passwords ocultos):</h3>";
    $datos = $db->query("SELECT * FROM sige_usu_usuario LIMIT 3");
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
                if (stripos($key, 'pass') !== false || stripos($key, 'clave') !== false || stripos($key, 'pwd') !== false) {
                    echo "<td>****</td>";
                } else {
                    echo "<td>" . htmlspecialchars(substr($val ?? '', 0, 50)) . "</td>";
                }
            }
            echo "</tr>";
        }
        echo "</table>";
    }

    $db->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
