<?php
require_once __DIR__ . '/../config.php';
checkAuth();

$sku = $_GET['sku'] ?? '';

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU requerido']);
    exit();
}

try {
    $producto = null;
    $wooProducto = null;

    // 1. Buscar en SIGE (base de datos)
    $db = getDbConnection();

    $sql = "SELECT
                sige_art_articulo.ART_IDArticulo as sku,
                sige_art_articulo.ART_DesArticulo as nombre,
                sige_art_articulo.ART_PartNumber as part_number,
                sige_art_articulo.art_artobs as descripcion_larga,
                sige_pal_preartlis.PAL_PrecVtaArt AS precio_sin_iva,
                (sige_pal_preartlis.PAL_PrecVtaArt * (1 + (sige_art_articulo.ART_PorcIVARI / 100))) AS precio_final,
                (sige_ads_artdepsck.ADS_CanFisicoArt - sige_ads_artdepsck.ADS_CanReservArt) AS stock,
                sige_adv_artdatvar.ADV_Peso as peso,
                sige_adv_artdatvar.ADV_Alto as alto,
                sige_adv_artdatvar.ADV_Ancho as ancho,
                sige_adv_artdatvar.ADV_Profundidad as profundidad,
                sige_aat_artatrib.atr_descatr as attr_nombre,
                sige_aat_artatrib.aat_descripcion as attr_valor
            FROM sige_art_articulo
            INNER JOIN sige_pal_preartlis ON sige_art_articulo.ART_IDArticulo = sige_pal_preartlis.ART_IDArticulo
            INNER JOIN sige_ads_artdepsck ON sige_art_articulo.ART_IDArticulo = sige_ads_artdepsck.ART_IDArticulo
            LEFT JOIN sige_adv_artdatvar ON sige_art_articulo.ART_IDArticulo = sige_adv_artdatvar.art_idarticulo
            LEFT JOIN sige_aat_artatrib ON sige_art_articulo.ART_IDArticulo = sige_aat_artatrib.art_idarticulo
            WHERE sige_art_articulo.ART_IDArticulo = ?
            AND sige_pal_preartlis.LIS_IDListaPrecio = ?
            AND sige_ads_artdepsck.DEP_IDDeposito = ?
            ORDER BY sige_aat_artatrib.aat_orden";

    $stmt = $db->prepare($sql);
    $listaPrecios = SIGE_LISTA_PRECIO;
    $deposito = SIGE_DEPOSITO;
    $stmt->bind_param("sii", $sku, $listaPrecios, $deposito);
    $stmt->execute();
    $result = $stmt->get_result();

    // Procesar resultados de SIGE
    $atributos = [];
    while ($row = $result->fetch_assoc()) {
        if ($producto === null) {
            $producto = [
                'sku' => $row['sku'],
                'nombre' => $row['nombre'],
                'part_number' => $row['part_number'],
                'descripcion_larga' => $row['descripcion_larga'],
                'precio_sin_iva' => $row['precio_sin_iva'],
                'precio' => $row['precio_final'],
                'stock' => $row['stock'],
                'peso' => $row['peso'],
                'alto' => $row['alto'],
                'ancho' => $row['ancho'],
                'profundidad' => $row['profundidad'],
                'atributos' => []
            ];
        }

        if (!empty($row['attr_nombre']) && !empty($row['attr_valor'])) {
            $atributos[] = [
                'nombre' => $row['attr_nombre'],
                'valor' => $row['attr_valor']
            ];
        }
    }

    if ($producto !== null) {
        $producto['atributos'] = $atributos;
    }

    $stmt->close();
    $db->close();

    // 2. Buscar en WooCommerce
    $wcProducts = wcRequest('/products?sku=' . urlencode($sku));

    if (!empty($wcProducts)) {
        foreach ($wcProducts as $p) {
            if ($p['sku'] === $sku) {
                $wooProducto = [
                    'id' => $p['id'],
                    'status' => $p['status'],
                    'permalink' => $p['permalink'],
                    'regular_price' => $p['regular_price'],
                    'stock_quantity' => $p['stock_quantity']
                ];
                break;
            }
        }
    }

    // 3. Responder
    if ($producto === null && $wooProducto === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Producto '$sku' no encontrado"]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'producto' => $producto,
        'woo_producto' => $wooProducto
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
