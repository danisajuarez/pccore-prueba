<?php

namespace App\Sige;

use App\Config\AppConfig;
use App\Database\DatabaseService;

/**
 * Repositorio de productos SIGE
 * Centraliza todas las consultas a la base de datos de productos
 */
class ProductRepository
{
    private DatabaseService $db;
    private AppConfig $config;

    public function __construct(DatabaseService $db, AppConfig $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Buscar producto por SKU con todos sus datos (precio, stock, dimensiones, atributos)
     *
     * Esta es la consulta centralizada que antes estaba duplicada en:
     * - product-search.php
     * - product-publish.php
     *
     * @param string $sku
     * @return array|null Producto con atributos o null si no existe
     */
    public function findBySku(string $sku): ?array
    {
        $sku = trim($sku);
        $sql = "SELECT
                    a.ART_IDArticulo as sku,
                    a.ART_DesArticulo as nombre,
                    a.ART_PartNumber as part_number,
                    a.art_artobs as descripcion_larga,
                    p.PAL_PrecVtaArt AS precio_sin_iva,
                    (p.PAL_PrecVtaArt * (1 + (a.ART_PorcIVARI / 100))) AS precio_final,
                    (s.ADS_CanFisicoArt - s.ADS_CanReservArt) AS stock,
                    d.ADV_Peso as peso,
                    d.ADV_Alto as alto,
                    d.ADV_Ancho as ancho,
                    d.ADV_Profundidad as profundidad,
                    attr.atr_descatr as attr_nombre,
                    attr.aat_descripcion as attr_valor
                FROM sige_art_articulo a
                LEFT JOIN sige_pal_preartlis p ON a.ART_IDArticulo = p.ART_IDArticulo
                LEFT JOIN sige_ads_artdepsck s ON a.ART_IDArticulo = s.ART_IDArticulo
                LEFT JOIN sige_adv_artdatvar d ON a.ART_IDArticulo = d.art_idarticulo
                LEFT JOIN sige_aat_artatrib attr ON a.ART_IDArticulo = attr.art_idarticulo
                WHERE TRIM(a.ART_IDArticulo) = ?
                AND p.LIS_IDListaPrecio = ?
                AND s.DEP_IDDeposito = ?
                ORDER BY attr.aat_orden";

        $listaPrecios = $this->config->getListaPrecio();
        $deposito = $this->config->getDeposito();

        $result = $this->db->query($sql, 'sii', [$sku, $listaPrecios, $deposito]);

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        // Procesar resultados (múltiples filas si hay varios atributos)
        $producto = null;
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
                    'precio_final' => $row['precio_final'],
                    'stock' => $row['stock'],
                    'peso' => $row['peso'],
                    'alto' => $row['alto'],
                    'ancho' => $row['ancho'],
                    'profundidad' => $row['profundidad'],
                    'atributos' => []
                ];
            }

            // Agregar atributo si existe
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
                LIMIT {$limit}";

        return $this->db->fetchAll($sql);
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

        $result = $this->db->fetchOne($sql);
        return $result ? (int) $result['total'] : 0;
    }

    /**
     * Marcar producto como sincronizado
     */
    public function markAsSynced(string $sku, float $precio, int $stock): void
    {
        $precioEscaped = $this->db->escape((string) $precio);
        $stockEscaped = $this->db->escape((string) $stock);
        $skuEscaped = $this->db->escape($sku);

        $sql = "UPDATE sige_prs_presho
                SET prs_fecultactweb = NOW(),
                    prs_precvtaart = '{$precioEscaped}',
                    prs_disponible = '{$stockEscaped}'
                WHERE art_idarticulo = '{$skuEscaped}'";

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
        $termEscaped = '%' . $this->db->escape($term) . '%';

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
                WHERE p.LIS_IDListaPrecio = ?
                AND s.DEP_IDDeposito = ?
                AND (a.ART_IDArticulo LIKE ? OR a.ART_DesArticulo LIKE ? OR a.ART_PartNumber LIKE ?)
                LIMIT {$limit}";

        return $this->db->fetchAll(
            $sql,
            'iisss',
            [
                $this->config->getListaPrecio(),
                $this->config->getDeposito(),
                $termEscaped,
                $termEscaped,
                $termEscaped
            ]
        );
    }

    /**
     * Verificar si un producto existe
     */
    public function exists(string $sku): bool
    {
        $sql = "SELECT 1 FROM sige_art_articulo WHERE TRIM(ART_IDArticulo) = ? LIMIT 1";
        $result = $this->db->fetchOne($sql, 's', [$sku]);
        return $result !== null;
    }
}
