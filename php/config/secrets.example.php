<?php
/**
 * PLANTILLA DE CREDENCIALES - PCCore
 *
 * COPIAR este archivo como "secrets.php" en esta misma carpeta
 * y reemplazar los valores con las credenciales propias.
 *
 *   cp secrets.example.php secrets.php
 *
 * El archivo "secrets.php" está ignorado por git (no se sube al repo).
 *
 * Ver TRASPASO-KEYS.md en la raíz del proyecto para saber cómo
 * obtener cada credencial.
 */

return [
    // -------------------------------------------------------------------
    // Mercado Libre  →  https://developers.mercadolibre.com.ar
    // Crear una app y copiar App ID + Client Secret
    // -------------------------------------------------------------------
    'ML_APP_ID'        => 'TU_APP_ID_AQUI',
    'ML_CLIENT_SECRET' => 'TU_CLIENT_SECRET_AQUI',

    // -------------------------------------------------------------------
    // WooCommerce (solo para los scripts de diagnóstico de la carpeta cli/)
    // WordPress Admin → WooCommerce → Ajustes → Avanzado → API REST
    // -------------------------------------------------------------------
    'WC_URL'    => 'https://TU-TIENDA.com/wp-json/wc/v3',
    'WC_KEY'    => 'ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'WC_SECRET' => 'cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
];
