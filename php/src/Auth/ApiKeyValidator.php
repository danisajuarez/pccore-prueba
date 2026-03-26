<?php

namespace App\Auth;

use App\Config\AppConfig;

/**
 * Validador de API Keys para endpoints de API
 */
class ApiKeyValidator
{
    private AppConfig $config;

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Obtener API key del request
     */
    public function getKeyFromRequest(): string
    {
        // Intentar desde headers
        $headers = $this->getRequestHeaders();
        $apiKey = $headers['X-Api-Key']
            ?? $headers['x-api-key']
            ?? $headers['X-API-KEY']
            ?? '';

        // Intentar desde $_SERVER
        if (empty($apiKey)) {
            $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        }

        // Intentar desde query string
        if (empty($apiKey)) {
            $apiKey = $_GET['api_key'] ?? $_GET['key'] ?? '';
        }

        return $apiKey;
    }

    /**
     * Obtener headers del request
     */
    private function getRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders() ?: [];
        }

        // Fallback para servidores sin getallheaders
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }

    /**
     * Validar la API key del request
     */
    public function validate(): bool
    {
        $providedKey = $this->getKeyFromRequest();
        $expectedKey = $this->config->getApiKey();

        return !empty($providedKey) && $providedKey === $expectedKey;
    }

    /**
     * Requerir API key válida (termina ejecución si es inválida)
     */
    public function requireValid(): void
    {
        if (!$this->validate()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'API Key inválida'
            ]);
            exit();
        }
    }

    /**
     * Obtener la API key esperada (para debug/referencia)
     */
    public function getExpectedKey(): string
    {
        return $this->config->getApiKey();
    }
}
