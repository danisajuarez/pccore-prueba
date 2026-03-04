<?php
// Script temporal para ver estructura de tabla usu
$conn = new mysqli('remoto.retec.com.ar', 'danisa', 'danisa2025', 'retec', 3307);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

echo "Conexión exitosa!\n\n";

// Buscar productos disponibles
$sql = "SELECT
            a.ART_IDArticulo as sku,
            a.ART_DesArticulo as nombre,
            (p.PAL_PrecVtaArt * (1 + (a.ART_PorcIVARI / 100))) AS precio,
            (s.ADS_CanFisicoArt - s.ADS_CanReservArt) AS stock
        FROM sige_art_articulo a
        INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
        INNER JOIN sige_ads_artdepsck s ON a.ART_IDArticulo = s.ART_IDArticulo
        WHERE p.LIS_IDListaPrecio = 1
        AND s.DEP_IDDeposito = 1
        ORDER BY a.ART_IDArticulo
        LIMIT 20";

$result = $conn->query($sql);
if ($result) {
    echo "Productos disponibles para probar:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-15s | %-40s | %10s | %5s\n", "SKU", "NOMBRE", "PRECIO", "STOCK");
    echo str_repeat("-", 80) . "\n";
    while ($row = $result->fetch_assoc()) {
        $nombre = mb_substr($row['nombre'], 0, 40);
        printf("%-15s | %-40s | %10.2f | %5d\n",
            $row['sku'],
            $nombre,
            $row['precio'],
            $row['stock']);
    }
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
