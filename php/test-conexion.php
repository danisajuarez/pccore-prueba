<?php
/**
 * Test de conexión a BD Master
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(10); // Timeout de 10 segundos

echo "<h2>Test Conexión BD Master</h2>";

$host = 'localhost';
$port = 3306;
$user = 'u962801258_0Ov4s';
$pass = 'Dona2012';
$db = 'u962801258_vUylQ';

echo "<p>Intentando conectar a: <strong>$host:$port</strong></p>";

$start = microtime(true);

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);

    if ($conn->connect_error) {
        echo "<p style='color:red;'>ERROR: " . $conn->connect_error . "</p>";
    } else {
        $time = round((microtime(true) - $start) * 1000);
        echo "<p style='color:green;'>CONECTADO en {$time}ms</p>";

        // Test query
        $result = $conn->query("SELECT COUNT(*) as total FROM sige_two_terwoo");
        $row = $result->fetch_assoc();
        echo "<p>Clientes en sige_two_terwoo: <strong>" . $row['total'] . "</strong></p>";

        $conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>EXCEPCIÓN: " . $e->getMessage() . "</p>";
}

echo "<p><small>Tiempo total: " . round((microtime(true) - $start), 2) . "s</small></p>";
