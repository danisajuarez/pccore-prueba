<?php
$conn = new mysqli('antartidasige.com', 'u962801258_0Ov4s', 'Dona2012', 'u962801258_vUylQ', 3306);
if ($conn->connect_error) die('Error: ' . $conn->connect_error);

echo "=== TODOS LOS CLIENTES EN sige_two_terwoo ===\n\n";
$r = $conn->query('SELECT * FROM sige_two_terwoo');
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        echo "ID: " . $row['TER_IdTercero'] . "\n";
        echo "  Nombre: " . ($row['TER_RazonSocialTer'] ?? 'NULL') . "\n";
        echo "  Pass: " . ($row['TWO_Pass'] ?? 'NULL') . "\n";
        echo "  Activo: " . ($row['TWO_Activo'] ?? 'NULL') . "\n";
        echo "  DB Ant Server: " . ($row['TWO_ServidorDBAnt'] ?? 'NULL') . "\n";
        echo "  DB Ant User: " . ($row['TWO_UserDBAnt'] ?? 'NULL') . "\n";
        echo "  DB Ant Name: " . ($row['TWO_NombreDBAnt'] ?? 'NULL') . "\n";
        echo "  DB Woo Server: " . ($row['TWO_ServidorDBWoo'] ?? 'NULL') . "\n";
        echo "---\n";
    }
} else {
    echo "No hay registros o error: " . $conn->error . "\n";
}

echo "\n=== BUSCAR TABLAS CON 'woo' o 'api' ===\n\n";
$r = $conn->query("SHOW TABLES LIKE '%woo%'");
while ($row = $r->fetch_array()) {
    echo $row[0] . "\n";
}
$r = $conn->query("SHOW TABLES LIKE '%api%'");
while ($row = $r->fetch_array()) {
    echo $row[0] . "\n";
}

echo "\n=== BUSCAR COLUMNAS CON 'key' o 'secret' en todas las tablas ===\n\n";
$tables = ['sige_two_terwoo', 'sige_woo_woocommer', 'sige_tml_termerlib'];
foreach ($tables as $table) {
    $r = $conn->query("SHOW COLUMNS FROM $table WHERE Field LIKE '%key%' OR Field LIKE '%secret%' OR Field LIKE '%url%' OR Field LIKE '%consumer%'");
    if ($r && $r->num_rows > 0) {
        echo "$table:\n";
        while ($row = $r->fetch_assoc()) {
            echo "  - " . $row['Field'] . "\n";
        }
    }
}

$conn->close();
