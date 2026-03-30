<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$db = getDbConnection();

// Ver valor actual
echo "ANTES:\n";
$r = $db->query("SELECT art_idarticulo, pal_precvtaart, prs_precvtaart FROM sige_prs_presho WHERE TRIM(art_idarticulo) = 'DCPT530DW'");
$row = $r->fetch_assoc();
print_r($row);

// Actualizar con valor entero
$precio = round($row['pal_precvtaart']);
echo "\nActualizando prs_precvtaart a: $precio\n";

$result = $db->query("UPDATE sige_prs_presho SET prs_precvtaart = $precio WHERE TRIM(art_idarticulo) = 'DCPT530DW'");
echo "UPDATE ejecutado: " . ($result ? "OK" : "ERROR: " . $db->error) . "\n";
echo "Filas afectadas: " . $db->affected_rows . "\n";

// Ver valor después
echo "\nDESPUES:\n";
$r2 = $db->query("SELECT art_idarticulo, pal_precvtaart, prs_precvtaart FROM sige_prs_presho WHERE TRIM(art_idarticulo) = 'DCPT530DW'");
print_r($r2->fetch_assoc());

$db->close();
