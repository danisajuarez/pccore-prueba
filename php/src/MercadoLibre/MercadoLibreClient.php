<?php

namespace App\MercadoLibre;

use Exception;

/**
 * Cliente HTTP para la API de Mercado Libre
 */
class MercadoLibreClient
{
    private TokenManager $tokenManager;
    private string $baseUrl = 'https://api.mercadolibre.com';
    private string $siteId = 'MLA'; // Argentina
    private int $timeout = 30;

    public function __construct(TokenManager $tokenManager)
    {
        $this->tokenManager = $tokenManager;
    }

    /**
     * Realizar request a la API de Mercado Libre
     *
     * @param string $endpoint Endpoint relativo
     * @param array $params Query parameters
     * @return array [http_code, data]
     */
    public function request(string $endpoint, array $params = []): array
    {
        $accessToken = $this->tokenManager->getAccessToken();

        $url = $this->baseUrl . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("CURL Error: {$error}");
        }

        return [
            'http_code' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }

    /**
     * Buscar productos en el catálogo
     */
    public function searchProducts(string $query, int $limit = 5): array
    {
        return $this->request('/products/search', [
            'q' => $query,
            'site_id' => $this->siteId,
            'limit' => $limit
        ]);
    }

    /**
     * Obtener detalles de un producto
     */
    public function getProduct(string $productId): array
    {
        return $this->request("/products/{$productId}");
    }

    /**
     * Buscar items (publicaciones)
     */
    public function searchItems(string $query, int $limit = 10): array
    {
        return $this->request('/sites/' . $this->siteId . '/search', [
            'q' => $query,
            'limit' => $limit
        ]);
    }

    /**
     * Obtener categorías
     */
    public function getCategories(): array
    {
        return $this->request('/sites/' . $this->siteId . '/categories');
    }

    /**
     * Establecer Site ID
     */
    public function setSiteId(string $siteId): self
    {
        $this->siteId = $siteId;
        return $this;
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
