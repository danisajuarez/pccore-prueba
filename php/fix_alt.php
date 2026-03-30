<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$db = getDbConnection();
$db->query("UPDATE sige_prs_presho SET prs_precvtaart = pal_precvtaart WHERE art_idarticulo = 'ALT300108'");
echo "ALT300108 corregido: " . $db->affected_rows . " filas\n";
$db->close();
