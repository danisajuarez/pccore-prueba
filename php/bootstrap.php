<?php
/**
 * Bootstrap - Inicialización del sistema
 *
 * Este archivo configura el container de dependencias y registra todos los servicios.
 * Debe ser incluido después de vendor/autoload.php
 */

use App\Container;
use App\Config\AppConfig;
use App\Database\DatabaseService;
use App\Auth\SessionManager;
use App\Auth\AuthService;
use App\Auth\ApiKeyValidator;
use App\Http\Response;
use App\WooCommerce\WooCommerceClient;
use App\WooCommerce\ProductMapper;
use App\Sige\ProductRepository;
use App\Sige\SyncService;
use App\MercadoLibre\TokenManager;
use App\MercadoLibre\MercadoLibreClient;
use App\MercadoLibre\ImageSearchService;

// Configuración base
Container::register(AppConfig::class, function () {
    return new AppConfig();
});

// Base de datos
Container::register(DatabaseService::class, function () {
    return new DatabaseService(Container::get(AppConfig::class));
});

// Autenticación
Container::register(SessionManager::class, function () {
    return new SessionManager(Container::get(AppConfig::class));
});

Container::register(AuthService::class, function () {
    return new AuthService(
        Container::get(DatabaseService::class),
        Container::get(SessionManager::class),
        Container::get(AppConfig::class)
    );
});

Container::register(ApiKeyValidator::class, function () {
    return new ApiKeyValidator(Container::get(AppConfig::class));
});

// HTTP Response
Container::register(Response::class, function () {
    return new Response();
});

// WooCommerce
Container::register(WooCommerceClient::class, function () {
    return new WooCommerceClient(Container::get(AppConfig::class));
});

Container::register(ProductMapper::class, function () {
    return new ProductMapper();
});

// SIGE
Container::register(ProductRepository::class, function () {
    return new ProductRepository(
        Container::get(DatabaseService::class),
        Container::get(AppConfig::class)
    );
});

Container::register(SyncService::class, function () {
    return new SyncService(
        Container::get(ProductRepository::class),
        Container::get(WooCommerceClient::class),
        Container::get(DatabaseService::class)
    );
});

// MercadoLibre
Container::register(TokenManager::class, function () {
    return new TokenManager();
});

Container::register(MercadoLibreClient::class, function () {
    return new MercadoLibreClient(Container::get(TokenManager::class));
});

Container::register(ImageSearchService::class, function () {
    return new ImageSearchService(Container::get(MercadoLibreClient::class));
});

// Marcar como inicializado
Container::boot();

// Variables globales para compatibilidad con código legacy
// Estas se establecerán cuando se requiera config.php
