<?php
header('Content-Type: text/plain');
echo "=== CLIENTE 3 ===\n\n";

$m = new mysqli('localhost', 'u962801258_0Ov4s', 'Dona2012', 'u962801258_vUylQ', 3306);
$r = $m->query("SELECT * FROM sige_two_terwoo WHERE TER_IdTercero = '3'");
$c = $r->fetch_assoc();
$m->close();

echo "Nombre: " . $c['TER_RazonSocialTer'] . "\n";
echo "Deposito: " . ($c['TWO_Deposito'] ?: 'VACIO') . "\n";
echo "Server: " . $c['TWO_ServidorDBAnt'] . "\n";
echo "DB: " . $c['TWO_NombreDBAnt'] . "\n\n";

$sige = new mysqli($c['TWO_ServidorDBAnt'], $c['TWO_UserDBAnt'], $c['TWO_PassDBAnt'], $c['TWO_NombreDBAnt'], $c['TWO_PuertoDBAnt'] ?: 3306);
if ($sige->connect_error) {
    die('Error BD SIGE: ' . $sige->connect_error);
}

$prs = $sige->query("SELECT COUNT(*) c FROM sige_prs_presho")->fetch_assoc()['c'];
echo "sige_prs_presho: $prs registros\n";

if ($prs > 0) {
    $pend = $sige->query("SELECT COUNT(*) c FROM sige_prs_presho WHERE pal_precvtaart <> prs_precvtaart OR ads_disponible <> prs_disponible")->fetch_assoc()['c'];
    echo "Pendientes sync: $pend\n";
}

$sige->close();
echo "\nFIN\n";
