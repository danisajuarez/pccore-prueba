<?php

namespace App\MercadoLibre;

/**
 * Servicio de búsqueda de imágenes en Mercado Libre
 */
class ImageSearchService
{
    private MercadoLibreClient $client;

    /** Marcas comunes para validación de relevancia */
    private array $knownBrands = [
        'hp', 'epson', 'canon', 'brother', 'samsung', 'lg', 'lenovo', 'dell',
        'logitech', 'kingston', 'asus', 'acer', 'msi', 'gigabyte', 'corsair',
        'seagate', 'western digital', 'wd', 'crucial', 'intel', 'amd', 'nvidia'
    ];

    public function __construct(MercadoLibreClient $client)
    {
        $this->client = $client;
    }

    /**
     * Buscar imágenes de producto en el catálogo de Mercado Libre
     *
     * @param string $query SKU, Part Number o nombre del producto
     * @return array Array con producto e imágenes encontradas
     */
    public function searchImages(string $query): array
    {
        try {
            $result = $this->client->searchProducts($query, 5);

            if ($result['http_code'] !== 200 || empty($result['data']['results'])) {
                return [];
            }

            // Tomar el primer resultado que tenga imágenes
            foreach ($result['data']['results'] as $producto) {
                if (!empty($producto['pictures'])) {
                    $imagenes = [];
                    foreach ($producto['pictures'] as $pic) {
                        $imagenes[] = [
                            'url' => $pic['url'],
                            'id' => $pic['id'],
                            'width' => $pic['max_width'] ?? null,
                            'height' => $pic['max_height'] ?? null
                        ];
                    }

                    return [
                        'producto_ml' => [
                            'id' => $producto['id'],
                            'nombre' => $producto['name'],
                            'atributos' => array_map(function ($attr) {
                                return [
                                    'nombre' => $attr['name'],
                                    'valor' => $attr['value_name']
                                ];
                            }, $producto['attributes'] ?? [])
                        ],
                        'imagenes' => $imagenes
                    ];
                }
            }

            return [];
        } catch (\Exception $e) {
            error_log("Error buscando imágenes en ML: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar imágenes con estrategia de fallback:
     * 1. Buscar por Part Number (más específico)
     * 2. Si no encuentra, buscar por SKU
     * 3. Si no encuentra, buscar por nombre
     *
     * @param string $sku
     * @param string|null $partNumber
     * @param string|null $nombre
     * @return array
     */
    public function searchWithFallback(string $sku, ?string $partNumber = null, ?string $nombre = null): array
    {
        // 1. Intentar por Part Number primero (más específico)
        if ($partNumber && strlen($partNumber) >= 3) {
            $resultado = $this->searchImages($partNumber);
            if (!empty($resultado['imagenes'])) {
                if ($this->isRelevantProduct($resultado['producto_ml']['nombre'], $nombre)) {
                    $resultado['encontrado_por'] = 'Part Number';
                    return $resultado;
                }
            }
        }

        // 2. Intentar por SKU solo si parece un código real
        if ($sku && (strlen($sku) >= 5 || !is_numeric($sku))) {
            $resultado = $this->searchImages($sku);
            if (!empty($resultado['imagenes'])) {
                if ($this->isRelevantProduct($resultado['producto_ml']['nombre'], $nombre)) {
                    $resultado['encontrado_por'] = 'SKU';
                    return $resultado;
                }
            }
        }

        // 3. Intentar por nombre del producto
        if ($nombre) {
            $nombreLimpio = $this->cleanProductName($nombre);
            if ($nombreLimpio) {
                $resultado = $this->searchImages($nombreLimpio);
                if (!empty($resultado['imagenes'])) {
                    $resultado['encontrado_por'] = 'Nombre';
                    return $resultado;
                }
            }
        }

        return ['imagenes' => [], 'encontrado_por' => null];
    }

    /**
     * Limpiar nombre de producto para mejor búsqueda
     */
    private function cleanProductName(string $nombre): string
    {
        // Eliminar caracteres especiales
        $nombre = preg_replace('/[^\w\s\-]/u', ' ', $nombre);
        // Eliminar espacios múltiples
        $nombre = preg_replace('/\s+/', ' ', $nombre);
        // Eliminar guiones solos
        $nombre = str_replace(' - ', ' ', $nombre);

        return trim($nombre);
    }

    /**
     * Verificar si el producto encontrado es relevante
     */
    private function isRelevantProduct(string $nombreEncontrado, ?string $nombreOriginal): bool
    {
        if (empty($nombreOriginal)) {
            return true;
        }

        $nombreEncontrado = strtolower($nombreEncontrado);
        $nombreOriginal = strtolower($nombreOriginal);

        // Extraer palabras clave (más de 2 caracteres)
        $palabrasOriginal = array_filter(
            preg_split('/[\s\-_]+/', $nombreOriginal),
            function ($p) {
                return strlen($p) > 2;
            }
        );

        // Verificar si al menos una palabra clave coincide
        foreach ($palabrasOriginal as $palabra) {
            if (strpos($nombreEncontrado, $palabra) !== false) {
                return true;
            }
        }

        // Buscar coincidencias de marca común
        foreach ($this->knownBrands as $marca) {
            if (strpos($nombreOriginal, $marca) !== false && strpos($nombreEncontrado, $marca) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Agregar marca conocida
     */
    public function addKnownBrand(string $brand): void
    {
        $this->knownBrands[] = strtolower($brand);
    }
}
