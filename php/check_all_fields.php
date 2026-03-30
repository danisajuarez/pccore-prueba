<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$db = getDbConnection();

echo "=== TODOS LOS CAMPOS DE LOS ULTIMOS 5 PRODUCTOS ===\n\n";

$sql = "SELECT * FROM sige_prs_presho ORDER BY prs_fecultactweb DESC LIMIT 5";
$r = $db->query($sql);

while ($row = $r->fetch_assoc()) {
    echo "SKU: " . $row['art_idarticulo'] . "\n";
    echo "----------------------------------------\n";
    echo "prs_idproducto (ID Woo):      [" . $row['prs_idproducto'] . "]\n";
    echo "pal_precvtaart (Precio SIGE): [" . $row['pal_precvtaart'] . "]\n";
    echo "ads_disponible (Stock SIGE):  [" . $row['ads_disponible'] . "]\n";
    echo "prs_activo1 (Activo local):   [" . $row['prs_activo1'] . "]\n";
    echo "prs_fecultactlocal:           [" . $row['prs_fecultactlocal'] . "]\n";
    echo "prs_precvtaart (Precio sync): [" . $row['prs_precvtaart'] . "]\n";
    echo "prs_disponible (Stock sync):  [" . $row['prs_disponible'] . "]\n";
    echo "prs_activo2 (Activo web):     [" . $row['prs_activo2'] . "]\n";
    echo "prs_fecultactweb:             [" . $row['prs_fecultactweb'] . "]\n";
    echo "ART_IdArticuloML:             [" . $row['ART_IdArticuloML'] . "]\n";
    echo "ART_DesArticulo:              [" . substr($row['ART_DesArticulo'], 0, 50) . "]\n";
    echo "PRS_DesArticulo:              [" . substr($row['PRS_DesArticulo'], 0, 50) . "]\n";
    echo "\n";
}

$db->close();
