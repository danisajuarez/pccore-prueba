<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== DIAGNÓSTICO ATRIBUTOS CLIENTE 5 ===\n\n";

$sku = $_GET['sku'] ?? '198153768561';

$m = new mysqli('localhost', 'u962801258_0Ov4s', 'Dona2012', 'u962801258_vUylQ', 3306);
$r = $m->query("SELECT * FROM sige_two_terwoo WHERE TER_IdTercero = '5'");
$c = $r->fetch_assoc();
$m->close();

echo "Cliente: " . $c['TER_RazonSocialTer'] . "\n";
echo "BD SIGE: " . $c['TWO_NombreDBAnt'] . " @ " . $c['TWO_ServidorDBAnt'] . "\n";
echo "SKU a buscar: $sku\n\n";

$sige = new mysqli($c['TWO_ServidorDBAnt'], $c['TWO_UserDBAnt'], $c['TWO_PassDBAnt'], $c['TWO_NombreDBAnt'], $c['TWO_PuertoDBAnt'] ?: 3306);
if ($sige->connect_error) {
    die('Error BD SIGE: ' . $sige->connect_error);
}
$sige->set_charset('utf8');

// 1. Buscar producto en articulo
echo "--- 1. Producto en sige_art_articulo ---\n";
$res = $sige->query("SELECT ART_IDArticulo, ART_DesArticulo, LENGTH(ART_IDArticulo) as len, HEX(SUBSTR(ART_IDArticulo,1,3)) as hex_inicio FROM sige_art_articulo WHERE TRIM(ART_IDArticulo) = '$sku' OR ART_IDArticulo LIKE '%$sku%' LIMIT 5");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "  SKU: [" . $row['ART_IDArticulo'] . "] len=" . $row['len'] . " hex_inicio=" . $row['hex_inicio'] . "\n";
        echo "  Nombre: " . $row['ART_DesArticulo'] . "\n";
    }
} else {
    echo "  NO ENCONTRADO\n";
}

// 2. Verificar tabla sige_aat_artatrib existe
echo "\n--- 2. Tablas de atributos disponibles ---\n";
$tables = $sige->query("SHOW TABLES LIKE '%artatrib%'");
if ($tables) {
    while ($t = $tables->fetch_array()) echo "  " . $t[0] . "\n";
} else {
    echo "  Error: " . $sige->error . "\n";
}

// 3. Buscar atributos con TRIM
echo "\n--- 3. Atributos en sige_aat_artatrib (TRIM) ---\n";
$res2 = $sige->query("SELECT art_idarticulo, atr_descatr, aat_descripcion, aat_orden FROM sige_aat_artatrib WHERE TRIM(art_idarticulo) = '$sku' ORDER BY aat_orden");
if ($res2 && $res2->num_rows > 0) {
    echo "  Encontrados: " . $res2->num_rows . " atributos\n";
    while ($row = $res2->fetch_assoc()) {
        echo sprintf("  %2d. %-25s: %s\n", $row['aat_orden'], $row['atr_descatr'], $row['aat_descripcion']);
    }
} else {
    echo "  NO HAY ATRIBUTOS (con TRIM)\n";
    echo "  Error MySQL: " . $sige->error . "\n";
}

// 4. Buscar atributos sin TRIM (busqueda LIKE)
echo "\n--- 4. Atributos con LIKE '%$sku%' ---\n";
$res3 = $sige->query("SELECT art_idarticulo, atr_descatr, aat_descripcion, LENGTH(art_idarticulo) as len FROM sige_aat_artatrib WHERE art_idarticulo LIKE '%$sku%' LIMIT 10");
if ($res3 && $res3->num_rows > 0) {
    while ($row = $res3->fetch_assoc()) {
        echo "  [" . $row['art_idarticulo'] . "] len=" . $row['len'] . " | " . $row['atr_descatr'] . " = " . $row['aat_descripcion'] . "\n";
    }
} else {
    echo "  No encontrado con LIKE\n";
}

// 5. Ver estructura de sige_aat_artatrib
echo "\n--- 5. Estructura sige_aat_artatrib (primeras 3 filas) ---\n";
$res4 = $sige->query("SELECT * FROM sige_aat_artatrib LIMIT 3");
if ($res4 && $res4->num_rows > 0) {
    $row = $res4->fetch_assoc();
    echo "  Columnas: " . implode(', ', array_keys($row)) . "\n";
    $res4->data_seek(0);
    while ($row = $res4->fetch_assoc()) {
        echo "  " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "  Tabla vacía o error: " . $sige->error . "\n";
}

// 6. Verificar que columna atr_descatr existe
echo "\n--- 6. ¿Columna atr_descatr existe? ---\n";
$res5 = $sige->query("SHOW COLUMNS FROM sige_aat_artatrib LIKE 'atr_descatr'");
if ($res5 && $res5->num_rows > 0) {
    echo "  SÍ existe atr_descatr\n";
} else {
    echo "  NO existe atr_descatr - verificar nombre de columna\n";
    $cols = $sige->query("SHOW COLUMNS FROM sige_aat_artatrib");
    while ($col = $cols->fetch_assoc()) {
        echo "  Columna: " . $col['Field'] . "\n";
    }
}

// 7. JOIN completo igual al product-search
echo "\n--- 7. JOIN completo (igual a product-search.php) ---\n";
$lista = $c['TWO_ListaPrecio'] ?? 1;
$deposito = $c['TWO_Deposito'] ?? 1;
$sqlJoin = "SELECT
    a.ART_IDArticulo as sku,
    a.ART_DesArticulo as nombre,
    attr.atr_descatr as attr_nombre,
    attr.aat_descripcion as attr_valor
FROM sige_art_articulo a
LEFT JOIN sige_aat_artatrib attr ON a.ART_IDArticulo = attr.art_idarticulo
WHERE TRIM(a.ART_IDArticulo) = '$sku'
ORDER BY attr.aat_orden";
$res6 = $sige->query($sqlJoin);
if ($res6 && $res6->num_rows > 0) {
    echo "  Filas retornadas: " . $res6->num_rows . "\n";
    while ($row = $res6->fetch_assoc()) {
        echo "  SKU=[" . $row['sku'] . "] attr=[" . $row['attr_nombre'] . "] val=[" . $row['attr_valor'] . "]\n";
    }
} else {
    echo "  Sin resultados. Error: " . $sige->error . "\n";
}

// 8. Ver config del cliente 5 relevante
echo "\n--- 8. Config cliente 5 ---\n";
$m2 = new mysqli('localhost', 'u962801258_0Ov4s', 'Dona2012', 'u962801258_vUylQ', 3306);
$r2 = $m2->query("SELECT TER_IdTercero, TER_RazonSocialTer, TWO_ListaPrecio, TWO_Deposito, TWO_NombreDBAnt FROM sige_two_terwoo WHERE TER_IdTercero = '5'");
$c2 = $r2->fetch_assoc();
$m2->close();
echo "  lista_precio=" . $c2['TWO_ListaPrecio'] . "\n";
echo "  deposito=" . $c2['TWO_Deposito'] . "\n";
echo "  bd=" . $c2['TWO_NombreDBAnt'] . "\n";

$sige->close();
echo "\n=== FIN DIAGNÓSTICO ===\n";
