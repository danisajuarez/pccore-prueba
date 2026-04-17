<?php
/**
 * Debug de dimensiones de producto
 */

require_once __DIR__ . '/../bootstrap.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}

header('Content-Type: text/html; charset=utf-8');

$sku = $_GET['sku'] ?? '00910';

try {
    echo "<h2>Debug de dimensiones - SKU: $sku</h2>";

    // 1. Datos de la base de datos
    $db = getDbConnection();

    $sql = "SELECT
                a.ART_IDArticulo as sku,
                a.ART_DesArticulo as nombre,
                d.ADV_Peso as peso,
                d.ADV_Alto as alto,
                d.ADV_Ancho as ancho,
                d.ADV_Profundidad as profundidad
            FROM sige_art_articulo a
            LEFT JOIN sige_adv_artdatvar d ON a.ART_IDArticulo = d.art_idarticulo
            WHERE TRIM(a.ART_IDArticulo) = ?";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<h3>1. Datos en Base de Datos (SIGE):</h3>";
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>Campo</th><th>Valor</th></tr>";
        echo "<tr><td>Nombre</td><td>{$row['nombre']}</td></tr>";
        echo "<tr><td>Peso (kg)</td><td>" . ($row['peso'] ?: '<em>NULL</em>') . "</td></tr>";
        echo "<tr><td>Alto (cm)</td><td>" . ($row['alto'] ?: '<em>NULL</em>') . "</td></tr>";
        echo "<tr><td>Ancho (cm)</td><td>" . ($row['ancho'] ?: '<em>NULL</em>') . "</td></tr>";
        echo "<tr><td>Profundidad (cm)</td><td>" . ($row['profundidad'] ?: '<em>NULL</em>') . "</td></tr>";
        echo "</table>";
    } else {
        echo "<p style='color: red;'>Producto no encontrado en BD</p>";
    }

    $stmt->close();
    $db->close();

    // 2. Datos en WooCommerce
    echo "<h3>2. Datos en WooCommerce:</h3>";
    try {
        $wcProducts = wcRequest('/products?sku=' . urlencode($sku));

        if (!empty($wcProducts)) {
            $found = false;
            foreach ($wcProducts as $p) {
                if (strcasecmp(trim($p['sku']), trim($sku)) === 0) {
                    $found = true;
                    echo "<table border='1' cellpadding='8'>";
                    echo "<tr><th>Campo</th><th>Valor</th></tr>";
                    echo "<tr><td>ID</td><td>{$p['id']}</td></tr>";
                    echo "<tr><td>Nombre</td><td>{$p['name']}</td></tr>";
                    echo "<tr><td>Peso</td><td>" . ($p['weight'] ?: '<em>vacío</em>') . "</td></tr>";

                    if (!empty($p['dimensions'])) {
                        echo "<tr><td>Alto</td><td>" . ($p['dimensions']['height'] ?: '<em>vacío</em>') . "</td></tr>";
                        echo "<tr><td>Ancho</td><td>" . ($p['dimensions']['width'] ?: '<em>vacío</em>') . "</td></tr>";
                        echo "<tr><td>Profundidad</td><td>" . ($p['dimensions']['length'] ?: '<em>vacío</em>') . "</td></tr>";
                    } else {
                        echo "<tr><td colspan='2' style='color: orange;'>⚠ No tiene dimensions configuradas en WC</td></tr>";
                    }

                    echo "</table>";

                    // Mostrar descripción si contiene dimensiones
                    if (!empty($p['description'])) {
                        echo "<h4>Descripción del producto:</h4>";
                        echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9; max-height: 200px; overflow-y: auto;'>";
                        echo nl2br(htmlspecialchars(substr($p['description'], 0, 500)));
                        if (strlen($p['description']) > 500) echo "...";
                        echo "</div>";
                    }

                    break;
                }
            }

            if (!$found) {
                echo "<p style='color: orange;'>Producto no encontrado en WooCommerce</p>";
            }
        } else {
            echo "<p style='color: orange;'>Producto no encontrado en WooCommerce</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }

    // 3. Datos de Mercado Libre (si aplica)
    echo "<h3>3. Datos de Mercado Libre:</h3>";
    require_once __DIR__ . '/../config/mercadolibre.php';

    $datosML = buscarDatosProductoML($sku, null, $row['nombre'] ?? null);

    if ($datosML['encontrado']) {
        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>Campo</th><th>Valor</th></tr>";
        echo "<tr><td>Encontrado por</td><td>{$datosML['encontrado_por']}</td></tr>";
        echo "<tr><td>Peso (kg)</td><td>" . ($datosML['peso'] ?: '<em>NULL</em>') . "</td></tr>";
        echo "<tr><td>Alto (cm)</td><td>" . ($datosML['alto'] ?: '<em>NULL</em>') . "</td></tr>";
        echo "<tr><td>Ancho (cm)</td><td>" . ($datosML['ancho'] ?: '<em>NULL</em>') . "</td></tr>";
        echo "<tr><td>Profundidad (cm)</td><td>" . ($datosML['profundidad'] ?: '<em>NULL</em>') . "</td></tr>";
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No se encontró en Mercado Libre</p>";
    }

    echo "<hr>";
    echo "<h3>Resumen:</h3>";
    echo "<ul>";
    echo "<li>Si las dimensiones están en BD pero NO en WC → Republicar el producto</li>";
    echo "<li>Si las dimensiones están en WC pero NO en la página web → Problema del tema de WordPress</li>";
    echo "<li>Si las dimensiones están en la descripción → Crear opción para mostrarlas en la ficha técnica</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
