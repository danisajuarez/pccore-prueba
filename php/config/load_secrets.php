<?php
/**
 * Cargador de credenciales locales - PCCore
 *
 * Devuelve el array de secrets desde secrets.php.
 * Si el archivo no existe, corta con un mensaje claro para que
 * quien instale el proyecto sepa qué hacer.
 */

function pccore_secrets(): array
{
    static $secrets = null;

    if ($secrets !== null) {
        return $secrets;
    }

    $path = __DIR__ . '/secrets.php';

    if (!file_exists($path)) {
        $msg = "Falta el archivo de credenciales: php/config/secrets.php\n"
             . "Copiá php/config/secrets.example.php como secrets.php y completá tus credenciales.\n"
             . "Ver TRASPASO-KEYS.md para más detalles.";
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $msg . "\n");
            exit(1);
        }
        http_response_code(500);
        die($msg);
    }

    $secrets = require $path;
    return $secrets;
}

/**
 * Atajo para obtener una sola credencial.
 */
function pccore_secret(string $key, $default = null)
{
    $secrets = pccore_secrets();
    return $secrets[$key] ?? $default;
}
