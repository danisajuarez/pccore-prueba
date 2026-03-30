<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$db = getDbConnection();

echo "=== ULTIMOS 15 REGISTROS ACTUALIZADOS ===\n\n";
$sql = "SELECT art_idarticulo, pal_precvtaart, prs_precvtaart, ads_disponible, prs_disponible, prs_fecultactweb
        FROM sige_prs_presho
        ORDER BY prs_fecultactweb DESC LIMIT 15";
$r = $db->query($sql);

echo "SKU          | SIGE Precio | Sync Precio | SIGE Stock | Sync Stock | Fecha\n";
echo "-------------|-------------|-------------|------------|------------|------------------\n";

while ($row = $r->fetch_assoc()) {
    printf("%-12s | %11s | %11s | %10s | %10s | %s\n",
        $row['art_idarticulo'],
        $row['pal_precvtaart'],
        $row['prs_precvtaart'],
        $row['ads_disponible'],
        $row['prs_disponible'],
        $row['prs_fecultactweb']
    );
}

$db->close();
