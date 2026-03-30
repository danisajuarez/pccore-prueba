<?php
/**
 * Verificar tabla sige_prs_presho
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$db = getDbConnection();

// 1. Estructura de la tabla
echo "=== ESTRUCTURA DE sige_prs_presho ===\n";
$res = $db->query("DESCRIBE sige_prs_presho");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . " | " . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
} else {
    echo "Error: " . $db->error . "\n";
}

// 2. Cantidad de registros
echo "\n=== CANTIDAD DE REGISTROS ===\n";
$count = $db->query("SELECT COUNT(*) as c FROM sige_prs_presho");
if ($count) {
    echo "Total: " . $count->fetch_assoc()['c'] . "\n";
}

// 3. Ver algunos registros
echo "\n=== MUESTRA DE REGISTROS ===\n";
$sample = $db->query("SELECT * FROM sige_prs_presho LIMIT 5");
if ($sample) {
    while ($row = $sample->fetch_assoc()) {
        print_r($row);
        echo "---\n";
    }
}

// 4. Ver si DCPT530DW está en esta tabla
echo "\n=== DCPT530DW EN sige_prs_presho ===\n";
$dcpt = $db->query("SELECT * FROM sige_prs_presho WHERE TRIM(art_idarticulo) = 'DCPT530DW'");
if ($dcpt && $dcpt->num_rows > 0) {
    while ($row = $dcpt->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "No encontrado\n";
}

$db->close();
