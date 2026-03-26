<?php
/**
 * Script para limpiar descripciones incorrectas de ML
 * Detecta y elimina descripciones que parecen ser de accesorios
 */

require_once __DIR__ . '/../config.php';
checkAuth();

$action = $_GET['action'] ?? 'detectar';

try {
    $db = getDbConnection();

    // Palabras que indican que la descripción es de un accesorio (no del producto principal)
    $palabrasAccesorio = [
        'adaptador',
        'soporte',
        'base para',
        'funda para',
        'cargador para',
        'fuente para',
        'cable para',
        'kit para',
        'montaje',
        'compatible con',
        'repuesto',
        'vesafix',  // Marca de soportes VESA
    ];

    if ($action === 'detectar') {
        // Buscar productos con descripciones sospechosas
        $sql = "SELECT ART_IDArticulo as sku, ART_NomArticulo as nombre, art_artobs as descripcion
                FROM sige_art_articulo
                WHERE art_artobs IS NOT NULL
                AND art_artobs != ''
                AND LENGTH(art_artobs) > 10
                LIMIT 500";

        $result = $db->query($sql);
        $sospechosos = [];

        while ($row = $result->fetch_assoc()) {
            $descLower = mb_strtolower($row['descripcion'], 'UTF-8');
            $nombreLower = mb_strtolower($row['nombre'], 'UTF-8');

            foreach ($palabrasAccesorio as $palabra) {
                // Si la descripción tiene palabra de accesorio pero el nombre del producto NO
                if (strpos($descLower, $palabra) !== false && strpos($nombreLower, $palabra) === false) {
                    $sospechosos[] = [
                        'sku' => trim($row['sku']),
                        'nombre' => $row['nombre'],
                        'descripcion' => substr($row['descripcion'], 0, 200) . '...',
                        'palabra_detectada' => $palabra
                    ];
                    break;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'action' => 'detectar',
            'mensaje' => 'Productos con descripciones sospechosas (parecen ser de accesorios)',
            'total' => count($sospechosos),
            'productos' => $sospechosos,
            'siguiente_paso' => 'Para limpiar, usar ?action=limpiar o ?action=limpiar&sku=CODIGO'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'limpiar') {
        $sku = $_GET['sku'] ?? null;

        if ($sku) {
            // Limpiar un SKU específico
            $stmt = $db->prepare("UPDATE sige_art_articulo SET art_artobs = NULL WHERE TRIM(ART_IDArticulo) = ?");
            $stmt->bind_param("s", $sku);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            echo json_encode([
                'success' => true,
                'action' => 'limpiar',
                'sku' => $sku,
                'filas_afectadas' => $affected,
                'mensaje' => $affected > 0 ? 'Descripción limpiada' : 'SKU no encontrado o ya estaba limpio'
            ]);

        } else {
            // Limpiar TODOS los sospechosos
            $limpiadosCount = 0;
            $skusLimpiados = [];

            // Primero detectar
            $sql = "SELECT ART_IDArticulo as sku, ART_NomArticulo as nombre, art_artobs as descripcion
                    FROM sige_art_articulo
                    WHERE art_artobs IS NOT NULL
                    AND art_artobs != ''
                    AND LENGTH(art_artobs) > 10";

            $result = $db->query($sql);

            while ($row = $result->fetch_assoc()) {
                $descLower = mb_strtolower($row['descripcion'], 'UTF-8');
                $nombreLower = mb_strtolower($row['nombre'], 'UTF-8');

                foreach ($palabrasAccesorio as $palabra) {
                    if (strpos($descLower, $palabra) !== false && strpos($nombreLower, $palabra) === false) {
                        // Limpiar este
                        $skuTrim = trim($row['sku']);
                        $stmt = $db->prepare("UPDATE sige_art_articulo SET art_artobs = NULL WHERE TRIM(ART_IDArticulo) = ?");
                        $stmt->bind_param("s", $skuTrim);
                        $stmt->execute();

                        if ($stmt->affected_rows > 0) {
                            $limpiadosCount++;
                            $skusLimpiados[] = $skuTrim;
                        }
                        $stmt->close();
                        break;
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'action' => 'limpiar_todos',
                'total_limpiados' => $limpiadosCount,
                'skus' => $skusLimpiados,
                'mensaje' => "Se limpiaron $limpiadosCount descripciones incorrectas"
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Acción no válida',
            'acciones_disponibles' => [
                'detectar' => 'Ver productos con descripciones sospechosas',
                'limpiar&sku=XXX' => 'Limpiar un SKU específico',
                'limpiar' => 'Limpiar TODOS los sospechosos (cuidado!)'
            ]
        ]);
    }

    $db->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
