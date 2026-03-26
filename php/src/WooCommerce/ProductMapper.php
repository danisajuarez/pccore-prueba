<?php

namespace App\WooCommerce;

/**
 * Mapea productos de SIGE a formato WooCommerce
 */
class ProductMapper
{
    /**
     * Convertir producto SIGE a formato WooCommerce para crear/actualizar
     */
    public function toWooCommerce(array $sigeProduct, array $atributos = []): array
    {
        $nombre = trim($sigeProduct['nombre'] ?? '');
        $descripcionLarga = trim($sigeProduct['descripcion_larga'] ?? '');
        $precioFinal = number_format((float) ($sigeProduct['precio_final'] ?? $sigeProduct['precio'] ?? 0), 2, '.', '');

        $productData = [
            'sku' => $sigeProduct['sku'],
            'name' => $nombre,
            'short_description' => $nombre,
            'description' => !empty($descripcionLarga) ? $descripcionLarga : $nombre,
            'regular_price' => $precioFinal,
            'stock_quantity' => (int) ($sigeProduct['stock'] ?? 0),
            'manage_stock' => true,
            'status' => 'publish',
            'type' => 'simple'
        ];

        // Agregar peso si existe
        if (!empty($sigeProduct['peso']) && $sigeProduct['peso'] > 0) {
            $productData['weight'] = strval($sigeProduct['peso']);
        }

        // Agregar dimensiones si existen
        $dimensions = [];
        if (!empty($sigeProduct['alto']) && $sigeProduct['alto'] > 0) {
            $dimensions['height'] = strval($sigeProduct['alto']);
        }
        if (!empty($sigeProduct['ancho']) && $sigeProduct['ancho'] > 0) {
            $dimensions['width'] = strval($sigeProduct['ancho']);
        }
        if (!empty($sigeProduct['profundidad']) && $sigeProduct['profundidad'] > 0) {
            $dimensions['length'] = strval($sigeProduct['profundidad']);
        }
        if (!empty($dimensions)) {
            $productData['dimensions'] = $dimensions;
        }

        // Agregar atributos si existen
        if (!empty($atributos)) {
            $productData['attributes'] = $this->mapAttributes($atributos);
        }

        return $productData;
    }

    /**
     * Convertir atributos SIGE a formato WooCommerce
     */
    public function mapAttributes(array $atributos): array
    {
        $wcAttributes = [];

        foreach ($atributos as $attr) {
            if (!empty($attr['nombre']) && !empty($attr['valor'])) {
                $wcAttributes[] = [
                    'name' => trim($attr['nombre']),
                    'options' => [trim($attr['valor'])],
                    'visible' => true,
                    'variation' => false
                ];
            }
        }

        return $wcAttributes;
    }

    /**
     * Crear payload para actualización de precio y stock
     */
    public function toPriceStockUpdate(int $wcProductId, float $precio, int $stock, ?float $precioSinIva = null): array
    {
        $data = [
            'id' => $wcProductId,
            'regular_price' => number_format($precio, 2, '.', ''),
            'sale_price' => '',
            'manage_stock' => true,
            'stock_quantity' => $stock,
            'stock_status' => $stock > 0 ? 'instock' : 'outofstock',
        ];

        if ($precioSinIva !== null) {
            $data['meta_data'] = [
                ['key' => '_price_no_taxes', 'value' => number_format($precioSinIva, 2, '.', '')]
            ];
        }

        return $data;
    }

    /**
     * Extraer datos resumidos de producto WooCommerce
     */
    public function extractWooSummary(array $wcProduct): array
    {
        return [
            'id' => $wcProduct['id'],
            'status' => $wcProduct['status'],
            'permalink' => $wcProduct['permalink'],
            'regular_price' => $wcProduct['regular_price'],
            'stock_quantity' => $wcProduct['stock_quantity']
        ];
    }

    /**
     * Extraer datos completos de producto WooCommerce
     */
    public function extractWooDetails(array $wcProduct): array
    {
        return [
            'id' => $wcProduct['id'],
            'sku' => $wcProduct['sku'],
            'name' => $wcProduct['name'],
            'status' => $wcProduct['status'],
            'regular_price' => $wcProduct['regular_price'],
            'stock_quantity' => $wcProduct['stock_quantity'],
            'weight' => $wcProduct['weight'] ?? null,
            'dimensions' => $wcProduct['dimensions'] ?? null,
            'attributes' => $wcProduct['attributes'] ?? [],
            'permalink' => $wcProduct['permalink']
        ];
    }
}
