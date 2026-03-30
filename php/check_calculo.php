<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'pccoreprueba.antartidasige.com';
require_once __DIR__ . '/config.php';

$db = getDbConnection();

$sql = "SELECT
    (p.PAL_PrecVtaArt * m.MON_CotizMon * (1 + (a.ART_PorcIVARI / 100))) as precio_calculado,
    p.PAL_PrecVtaArt as precio_base,
    m.MON_CotizMon as cotiz,
    a.ART_PorcIVARI as iva
FROM sige_art_articulo a
INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
INNER JOIN sige_mon_moneda m ON m.MON_IdMon = 2
WHERE TRIM(a.ART_IDArticulo) = 'DCPT530DW' AND p.LIS_IDListaPrecio = " . SIGE_LISTA_PRECIO;

$r = $db->query($sql);
$row = $r->fetch_assoc();

echo "=== CALCULO DE PRECIO DCPT530DW ===\n";
echo "Precio base USD: " . $row['precio_base'] . "\n";
echo "Cotizacion: " . $row['cotiz'] . "\n";
echo "IVA %: " . $row['iva'] . "\n";
echo "Precio calculado (con decimales): " . $row['precio_calculado'] . "\n";
echo "Precio redondeado (sin decimales): " . round($row['precio_calculado']) . "\n";

$db->close();
