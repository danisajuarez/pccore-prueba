<?php

namespace App\Sige;

use App\Database\DatabaseService;
use App\WooCommerce\WooCommerceClient;
use App\WooCommerce\ProductMapper;
use Exception;

/**
 * Servicio de sincronización SIGE -> WooCommerce
 */
class SyncService
{
    private ProductRepository $repository;
    private WooCommerceClient $wcClient;
    private DatabaseService $db;
    private ProductMapper $mapper;

    public function __construct(
        ProductRepository $repository,
        WooCommerceClient $wcClient,
        DatabaseService $db
    ) {
        $this->repository = $repository;
        $this->wcClient = $wcClient;
        $this->db = $db;
        $this->mapper = new ProductMapper();
    }

    /**
     * Ejecutar sincronización de un lote de productos
     *
     * @param int $batchSize Tamaño del lote
     * @return array Resultado de la sincronización
     */
    public function syncBatch(int $batchSize = 50): array
    {
        $totalPendientes = $this->repository->countPendingSync();

        if ($totalPendientes === 0) {
            return [
                'success' => true,
                'message' => 'Sin cambios detectados.',
                'remaining' => 0
            ];
        }

        $productos = $this->repository->getPendingSync($batchSize);
        $batchUpdate = [];
        $notInWoo = [];
        $skuToData = [];

        // Buscar IDs en WooCommerce y preparar batch
        foreach ($productos as $prod) {
            $sku = $prod['sku'];
            $skuToData[$sku] = $prod;

            try {
                $wcProduct = $this->wcClient->findBySku($sku);

                if ($wcProduct !== null) {
                    $updateData = $this->mapper->toPriceStockUpdate(
                        $wcProduct['id'],
                        (float) $prod['precio'],
                        (int) $prod['stock'],
                        (float) $prod['precio_sin_iva']
                    );
                    $updateData['sku'] = $sku; // Guardamos para referencia
                    $batchUpdate[] = $updateData;
                } else {
                    $notInWoo[] = $sku;
                }
            } catch (Exception $e) {
                $notInWoo[] = $sku;
            }
        }

        // Hacer batch update a WooCommerce
        $successful = 0;
        $failed = 0;
        $results = [];

        if (!empty($batchUpdate)) {
            // Limpiar SKU del payload para WooCommerce
            $wcPayload = array_map(function ($item) {
                $clean = $item;
                unset($clean['sku']);
                return $clean;
            }, $batchUpdate);

            try {
                $this->wcClient->batchUpdate($wcPayload);

                // Todo OK - marcar en BD
                foreach ($batchUpdate as $item) {
                    $sku = $item['sku'];
                    $data = $skuToData[$sku];

                    $this->repository->markAsSynced(
                        $sku,
                        (float) $data['precio'],
                        (int) $data['stock']
                    );

                    $successful++;
                    $results[] = [
                        'sku' => $sku,
                        'status' => 'updated',
                        'price' => $item['regular_price'],
                        'stock' => $item['stock_quantity']
                    ];
                }
            } catch (Exception $e) {
                // Batch falló - reportar error
                foreach ($batchUpdate as $item) {
                    $failed++;
                    $results[] = [
                        'sku' => $item['sku'],
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        // Marcar los que no están en WooCommerce
        foreach ($notInWoo as $sku) {
            $data = $skuToData[$sku];

            $this->repository->markAsSynced(
                $sku,
                (float) $data['precio'],
                (int) $data['stock']
            );

            $results[] = ['sku' => $sku, 'status' => 'not_in_woo'];
        }

        $remaining = $totalPendientes - count($productos);

        return [
            'success' => true,
            'processed' => count($productos),
            'successful' => $successful,
            'not_in_woo' => count($notInWoo),
            'failed' => $failed,
            'remaining' => max(0, $remaining),
            'details' => $results
        ];
    }

    /**
     * Publicar un producto en WooCommerce
     *
     * @param string $sku
     * @return array Resultado de la publicación
     * @throws Exception
     */
    public function publishProduct(string $sku): array
    {
        $sku = trim($sku);
        $producto = $this->repository->findBySku($sku);

        if ($producto === null) {
            throw new Exception("Producto con SKU '{$sku}' no encontrado en la base de datos");
        }

        // Validaciones
        if (empty($producto['nombre'])) {
            throw new Exception('El producto no tiene nombre');
        }

        if (empty($producto['precio_final']) || $producto['precio_final'] <= 0) {
            throw new Exception('El producto no tiene precio válido');
        }

        // Preparar datos para WooCommerce
        $productData = $this->mapper->toWooCommerce($producto, $producto['atributos'] ?? []);

        // Verificar si ya existe en WooCommerce
        $existingProduct = $this->wcClient->findBySku($sku);

        if ($existingProduct !== null) {
            // Actualizar producto existente
            $response = $this->wcClient->updateProduct($existingProduct['id'], $productData);
            $mensaje = 'Producto actualizado en WooCommerce';
        } else {
            // Crear nuevo producto
            $response = $this->wcClient->createProduct($productData);
            $mensaje = 'Producto creado en WooCommerce';
        }

        return [
            'success' => true,
            'message' => $mensaje,
            'product' => $this->mapper->extractWooDetails($response)
        ];
    }

    /**
     * Actualizar producto existente en WooCommerce
     *
     * @param int $wcProductId ID del producto en WooCommerce
     * @param array $data Datos a actualizar
     * @return array
     */
    public function updateProduct(int $wcProductId, array $data): array
    {
        $response = $this->wcClient->updateProduct($wcProductId, $data);

        return [
            'success' => true,
            'product' => $this->mapper->extractWooDetails($response)
        ];
    }

    /**
     * Sincronizar precio y stock de un producto específico
     */
    public function syncPriceStock(string $sku): array
    {
        $sku = trim($sku);
        $producto = $this->repository->findBySku($sku);

        if ($producto === null) {
            throw new Exception("Producto con SKU '{$sku}' no encontrado");
        }

        $wcProduct = $this->wcClient->findBySku($sku);

        if ($wcProduct === null) {
            throw new Exception("Producto con SKU '{$sku}' no existe en WooCommerce");
        }

        $updateData = [
            'regular_price' => number_format((float) $producto['precio_final'], 2, '.', ''),
            'stock_quantity' => (int) $producto['stock']
        ];

        $response = $this->wcClient->updateProduct($wcProduct['id'], $updateData);

        return [
            'success' => true,
            'message' => 'Precio y stock sincronizados',
            'product' => $this->mapper->extractWooSummary($response)
        ];
    }
}
