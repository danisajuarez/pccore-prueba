<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $db = getDbConnection();

    echo "<h2>Usuarios habilitados:</h2>";
    $datos = $db->query("SELECT USU_IDUsuario, USU_LogUsu, USU_PassWord, USU_DatosUsu, USU_Habilitado FROM sige_usu_usuario WHERE USU_Habilitado = 'S' LIMIT 5");
    
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Usuario (login)</th><th>Password</th><th>Nombre</th><th>Habilitado</th></tr>";
    
    while ($d = $datos->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$d['USU_IDUsuario']}</td>";
        echo "<td><strong>{$d['USU_LogUsu']}</strong></td>";
        echo "<td>{$d['USU_PassWord']}</td>";
        echo "<td>{$d['USU_DatosUsu']}</td>";
        echo "<td>{$d['USU_Habilitado']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    $db->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
