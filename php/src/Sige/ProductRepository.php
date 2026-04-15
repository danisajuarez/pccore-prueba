<?php

namespace App\Sige;

/**
 * Repositorio de productos SIGE
 * COPIA EXACTA del código de producción (php/api/product-search.php fallback)
 * Recibe conexión dinámicamente para multi-tenant
 */
class ProductRepository
{
    private $db;
    private int $listaPrecio;
    private int $deposito;

    /**
     * @param mysqli $db Conexión MySQLi (no DatabaseService)
     * @param int $listaPrecio ID de la lista de precios a usar
     * @param int $deposito ID del depósito para stock
     */
    public function __construct($db, int $listaPrecio = 1, int $deposito = 1)
    {
        $this->db = $db;
        $this->listaPrecio = $listaPrecio;
        $this->deposito = $deposito;
    }

    /**
     * Buscar producto por SKU con todos sus datos (precio, stock, dimensiones, atributos)
     * CÓDIGO EXACTO DEL FALLBACK DE PRODUCCIÓN
     *
     * @param string $sku
     * @return array|null Producto con atributos o null si no existe
     */
    public function findBySku(string $sku): ?array
    {
        $sku = trim($sku);
        $listaPrecio = $this->listaPrecio;
        $deposito = $this->deposito;

        // SQL EXACTO DE PRODUCCIÓN (product-search.php fallback líneas 117-145)
        $sql = "SELECT
                    a.ART_IDArticulo as sku,
                    a.ART_DesArticulo as nombre,
                    a.ART_PartNumber as part_number,
                    a.art_artobs as descripcion_larga,
                    (p.PAL_PrecVtaArt * COALESCE(m.MON_CotizMon, 1)) AS precio_sin_iva,
                    (p.PAL_PrecVtaArt * COALESCE(m.MON_CotizMon, 1) * (1 + (a.ART_PorcIVARI / 100))) AS precio_final,
                    GREATEST(COALESCE(s.ADS_CanFisicoArt, 0) - COALESCE(s.ADS_CanReservArt, 0), 0) AS stock,
                    d.ADV_Peso as peso,
                    d.ADV_Alto as alto,
                    d.ADV_Ancho as ancho,
                    d.ADV_Profundidad as profundidad,
                    attr.atr_descatr as attr_nombre,
                    attr.aat_descripcion as attr_valor,
                    lin.LIN_DesLinea as categoria,
                    gli.gli_descripcion as supracategoria,
                    car.CAR_DesCatArt as marca
                FROM sige_art_articulo a
                LEFT JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
                    AND p.LIS_IDListaPrecio = $listaPrecio
                LEFT JOIN sige_ads_artdepsck s ON a.ART_IDArticulo = s.ART_IDArticulo
                    AND s.DEP_IDDeposito = $deposito
                LEFT JOIN sige_mon_moneda m ON a.MON_IdMon = m.MON_IdMon
                LEFT JOIN sige_adv_artdatvar d ON a.ART_IDArticulo = d.art_idarticulo
                LEFT JOIN sige_aat_artatrib attr ON a.ART_IDArticulo = attr.art_idarticulo
                LEFT JOIN sige_lin_linea lin ON a.LIN_IDLinea = lin.LIN_IDLinea
                LEFT JOIN sige_gli_gruplin gli ON lin.GLI_IdGli = gli.gli_idgli
                LEFT JOIN sige_car_catarticulo car ON a.CAR_IdCar = car.CAR_IdCar
                WHERE TRIM(a.ART_IDArticulo) = ?
                ORDER BY attr.aat_orden";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $sku);
        $stmt->execute();
        $result = $stmt->get_result();

        $producto = null;
        $atributos = [];

        // PROCESAMIENTO EXACTO DE PRODUCCIÓN
        while ($row = $result->fetch_assoc()) {
            if ($producto === null) {
                $producto = [
                    'sku' => trim($row['sku']),
                    'nombre' => $row['nombre'],
                    'part_number' => trim($row['part_number'] ?? ''),
                    'descripcion_larga' => $row['descripcion_larga'],
                    'precio_sin_iva' => $row['precio_sin_iva'],
                    'precio' => $row['precio_final'],
                    'stock' => $row['stock'],
                    'peso' => $row['peso'],
                    'alto' => $row['alto'],
                    'ancho' => $row['ancho'],
                    'profundidad' => $row['profundidad'],
                    'categoria' => $row['categoria'],
                    'supracategoria' => $row['supracategoria'],
                    'marca' => $row['marca'],
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

        return $producto;
    }

    /**
     * Obtener productos pendientes de sincronización
     * (precio o stock diferente entre SIGE y lo registrado)
     *
     * @param int $limit Cantidad máxima de productos a retornar
     * @return array
     */
    public function getPendingSync(int $limit = 50): array
    {
        $sql = "SELECT s.art_idarticulo as sku,
                       s.pal_precvtaart as precio,
                       s.ads_disponible as stock,
                       (s.pal_precvtaart / (1 + (a.ART_PorcIVARI / 100))) AS precio_sin_iva
                FROM sige_prs_presho s
                INNER JOIN sige_art_articulo a ON a.ART_IDArticulo = s.art_idarticulo
                WHERE s.pal_precvtaart <> s.prs_precvtaart
                   OR s.prs_disponible <> s.ads_disponible
                LIMIT $limit";

        $result = $this->db->query($sql);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Contar productos pendientes de sincronización
     */
    public function countPendingSync(): int
    {
        $sql = "SELECT COUNT(*) as total
                FROM sige_prs_presho s
                INNER JOIN sige_art_articulo a ON a.ART_IDArticulo = s.art_idarticulo
                WHERE s.pal_precvtaart <> s.prs_precvtaart
                   OR s.prs_disponible <> s.ads_disponible";

        $result = $this->db->query($sql);
        $row = $result->fetch_assoc();
        return $row ? (int) $row['total'] : 0;
    }

    /**
     * Marcar producto como sincronizado
     */
    public function markAsSynced(string $sku, float $precio, int $stock): void
    {
        $sku = $this->db->real_escape_string($sku);
        $precio = $this->db->real_escape_string((string) $precio);
        $stock = $this->db->real_escape_string((string) $stock);

        $sql = "UPDATE sige_prs_presho
                SET prs_fecultactweb = NOW(),
                    prs_precvtaart = '$precio',
                    prs_disponible = '$stock'
                WHERE art_idarticulo = '$sku'";

        $this->db->query($sql);
    }

    /**
     * Buscar productos por término (para búsqueda general)
     *
     * @param string $term Término de búsqueda
     * @param int $limit Límite de resultados
     * @return array
     */
    public function search(string $term, int $limit = 20): array
    {
        $termEscaped = '%' . $this->db->real_escape_string($term) . '%';
        $listaPrecio = $this->listaPrecio;
        $deposito = $this->deposito;

        $sql = "SELECT
                    a.ART_IDArticulo as sku,
                    a.ART_DesArticulo as nombre,
                    a.ART_PartNumber as part_number,
                    p.PAL_PrecVtaArt AS precio_sin_iva,
                    (p.PAL_PrecVtaArt * (1 + (a.ART_PorcIVARI / 100))) AS precio_final,
                    (s.ADS_CanFisicoArt - s.ADS_CanReservArt) AS stock
                FROM sige_art_articulo a
                INNER JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
                INNER JOIN sige_ads_artdepsck s ON a.ART_IDArticulo = s.ART_IDArticulo
                WHERE p.LIS_IDListaPrecio = $listaPrecio
                AND s.DEP_IDDeposito = $deposito
                AND (a.ART_IDArticulo LIKE '$termEscaped' OR a.ART_DesArticulo LIKE '$termEscaped' OR a.ART_PartNumber LIKE '$termEscaped')
                LIMIT $limit";

        $result = $this->db->query($sql);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Verificar si un producto existe
     */
    public function exists(string $sku): bool
    {
        $sku = $this->db->real_escape_string(trim($sku));
        $sql = "SELECT 1 FROM sige_art_articulo WHERE TRIM(ART_IDArticulo) = '$sku' LIMIT 1";
        $result = $this->db->query($sql);
        return $result && $result->num_rows > 0;
    }
}
