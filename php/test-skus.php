<?php
$conn = new mysqli('giuggia.dyndns-home.com', 'root', 'giuggia', 'giuggia', 3307);
if ($conn->connect_error) die('Error: ' . $conn->connect_error);

$result = $conn->query('SELECT ART_IDArticulo, ART_DesArticulo FROM sige_art_articulo WHERE ART_DesArticulo IS NOT NULL LIMIT 10');

if (!$result) {
    die('Query error: ' . $conn->error);
}

while ($row = $result->fetch_assoc()) {
    echo $row['ART_IDArticulo'] . ' | ' . $row['ART_DesArticulo'] . "\n";
}
$conn->close();
