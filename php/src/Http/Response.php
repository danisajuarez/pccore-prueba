<?php

namespace App\Http;

/**
 * Respuestas JSON estandarizadas
 */
class Response
{
    /**
     * Enviar headers para API JSON
     */
    public function setupApiHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    }

    /**
     * Manejar preflight CORS
     */
    public function handlePreflight(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            return true;
        }
        return false;
    }

    /**
     * Enviar respuesta JSON exitosa
     */
    public function success(array $data = [], string $message = null): void
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        $response = array_merge($response, $data);

        echo json_encode($response);
        exit();
    }

    /**
     * Enviar respuesta JSON de error
     */
    public function error(string $message, int $httpCode = 400, array $extra = []): void
    {
        http_response_code($httpCode);

        $response = [
            'success' => false,
            'error' => $message
        ];

        $response = array_merge($response, $extra);

        echo json_encode($response);
        exit();
    }

    /**
     * Error 400 - Bad Request
     */
    public function badRequest(string $message = 'Solicitud inválida'): void
    {
        $this->error($message, 400);
    }

    /**
     * Error 401 - Unauthorized
     */
    public function unauthorized(string $message = 'No autorizado'): void
    {
        $this->error($message, 401);
    }

    /**
     * Error 404 - Not Found
     */
    public function notFound(string $message = 'Recurso no encontrado'): void
    {
        $this->error($message, 404);
    }

    /**
     * Error 405 - Method Not Allowed
     */
    public function methodNotAllowed(string $message = 'Método no permitido'): void
    {
        $this->error($message, 405);
    }

    /**
     * Error 500 - Internal Server Error
     */
    public function serverError(string $message = 'Error interno del servidor'): void
    {
        $this->error($message, 500);
    }

    /**
     * Enviar respuesta JSON cruda
     */
    public function json(array $data, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        echo json_encode($data);
        exit();
    }

    /**
     * Requerir método HTTP específico
     */
    public function requireMethod(string $method): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== $method) {
            $this->methodNotAllowed("Solo se permite el método {$method}");
        }
    }

    /**
     * Obtener datos JSON del body del request
     */
    public function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        return is_array($data) ? $data : [];
    }
}
