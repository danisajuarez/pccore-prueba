<?php

namespace App\WooCommerce;

use Exception;

/**
 * Cliente HTTP para la API de WooCommerce
 */
class WooCommerceClient
{
    private string $baseUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private int $timeout = 30;

    /**
     * @param string $baseUrl URL base de la API WC (ej: https://tienda.com/wp-json/wc/v3)
     * @param string $consumerKey Consumer Key de WooCommerce
     * @param string $consumerSecret Consumer Secret de WooCommerce
     */
    public function __construct(string $baseUrl, string $consumerKey, string $consumerSecret)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
    }

    /**
     * Realizar request a la API de WooCommerce
     *
     * @param string $endpoint Endpoint relativo (ej: /products)
     * @param string $method GET, POST, PUT, DELETE
     * @param array|null $data Datos a enviar
     * @return array Respuesta decodificada
     * @throws Exception
     */
    public function request(string $endpoint, string $method = 'GET', ?array $data = null): array
    {
        $url = $this->buildUrl($endpoint);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($method === 'PUT' || $method === 'POST' || $method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("CURL Error: {$error}");
        }

        if ($httpCode >= 400) {
            throw new Exception("WooCommerce API error: {$httpCode} - {$response}");
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Construir URL completa con autenticación
     */
    private function buildUrl(string $endpoint): string
    {
        $url = $this->baseUrl . $endpoint;

        $separator = strpos($url, '?') === false ? '?' : '&';
        $url .= $separator . 'consumer_key=' . $this->consumerKey;
        $url .= '&consumer_secret=' . $this->consumerSecret;

        return $url;
    }

    /**
     * Obtener productos
     */
    public function getProducts(array $params = []): array
    {
        $endpoint = '/products';
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        return $this->request($endpoint);
    }

    /**
     * Buscar producto por SKU
     */
    public function findBySku(string $sku): ?array
    {
        $products = $this->request('/products?sku=' . urlencode($sku));

        if (!empty($products)) {
            foreach ($products as $product) {
                if ($product['sku'] === $sku) {
                    return $product;
                }
            }
        }

        return null;
    }

    /**
     * Obtener producto por ID
     */
    public function getProduct(int $id): array
    {
        return $this->request("/products/{$id}");
    }

    /**
     * Crear producto
     */
    public function createProduct(array $data): array
    {
        return $this->request('/products', 'POST', $data);
    }

    /**
     * Actualizar producto
     */
    public function updateProduct(int $id, array $data): array
    {
        return $this->request("/products/{$id}", 'PUT', $data);
    }

    /**
     * Actualizar productos en batch
     */
    public function batchUpdate(array $products): array
    {
        return $this->request('/products/batch', 'POST', ['update' => $products]);
    }

    /**
     * Crear productos en batch
     */
    public function batchCreate(array $products): array
    {
        return $this->request('/products/batch', 'POST', ['create' => $products]);
    }

    /**
     * Eliminar producto
     */
    public function deleteProduct(int $id, bool $force = false): array
    {
        $endpoint = "/products/{$id}";
        if ($force) {
            $endpoint .= '?force=true';
        }
        return $this->request($endpoint, 'DELETE');
    }

    /**
     * Establecer timeout
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
}
