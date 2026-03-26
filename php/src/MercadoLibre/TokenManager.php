<?php

namespace App\MercadoLibre;

use Exception;

/**
 * Gestiona los tokens OAuth de Mercado Libre
 */
class TokenManager
{
    private string $appId;
    private string $clientSecret;
    private string $tokenFile;

    public function __construct(
        ?string $appId = null,
        ?string $clientSecret = null,
        ?string $tokenFile = null
    ) {
        $this->appId = $appId ?? '828139284413193';
        $this->clientSecret = $clientSecret ?? 'zkXFOW1IOODosHBEkeJmjBKLCzG9AFq2';
        $this->tokenFile = $tokenFile ?? dirname(__DIR__, 2) . '/config/ml_token.json';
    }

    /**
     * Obtener access token válido
     * Usa cache de archivo con verificación de expiración
     */
    public function getAccessToken(): string
    {
        // Verificar si hay token guardado y vigente
        if (file_exists($this->tokenFile)) {
            $tokenData = json_decode(file_get_contents($this->tokenFile), true);

            if ($tokenData && isset($tokenData['expires_at']) && time() < $tokenData['expires_at']) {
                return $tokenData['access_token'];
            }
        }

        // Obtener nuevo token
        return $this->refreshToken();
    }

    /**
     * Obtener nuevo token desde la API
     */
    private function refreshToken(): string
    {
        $tokenUrl = 'https://api.mercadolibre.com/oauth/token';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->appId,
            'client_secret' => $this->clientSecret
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Error obteniendo token de ML: {$response}");
        }

        $tokenData = json_decode($response, true);

        // Guardar token con timestamp de expiración (5 min antes de expirar)
        $tokenData['expires_at'] = time() + $tokenData['expires_in'] - 300;
        file_put_contents($this->tokenFile, json_encode($tokenData));

        return $tokenData['access_token'];
    }

    /**
     * Invalidar token guardado
     */
    public function invalidateToken(): void
    {
        if (file_exists($this->tokenFile)) {
            unlink($this->tokenFile);
        }
    }

    /**
     * Verificar si el token actual es válido
     */
    public function isTokenValid(): bool
    {
        if (!file_exists($this->tokenFile)) {
            return false;
        }

        $tokenData = json_decode(file_get_contents($this->tokenFile), true);
        return $tokenData && isset($tokenData['expires_at']) && time() < $tokenData['expires_at'];
    }
}
