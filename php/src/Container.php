<?php

namespace App;

use Exception;

/**
 * Simple Dependency Injection Container
 * Maneja la creación y almacenamiento de servicios singleton
 */
class Container
{
    private static array $instances = [];
    private static array $factories = [];
    private static bool $booted = false;

    /**
     * Registrar una factory para crear una instancia
     */
    public static function register(string $class, callable $factory): void
    {
        self::$factories[$class] = $factory;
    }

    /**
     * Obtener una instancia del container (singleton)
     */
    public static function get(string $class): object
    {
        if (!isset(self::$instances[$class])) {
            if (isset(self::$factories[$class])) {
                self::$instances[$class] = call_user_func(self::$factories[$class]);
            } else {
                throw new Exception("Service not registered: {$class}");
            }
        }

        return self::$instances[$class];
    }

    /**
     * Verificar si un servicio está registrado
     */
    public static function has(string $class): bool
    {
        return isset(self::$factories[$class]) || isset(self::$instances[$class]);
    }

    /**
     * Establecer directamente una instancia (útil para testing)
     */
    public static function set(string $class, object $instance): void
    {
        self::$instances[$class] = $instance;
    }

    /**
     * Marcar el container como inicializado
     */
    public static function boot(): void
    {
        self::$booted = true;
    }

    /**
     * Verificar si el container está inicializado
     */
    public static function isBooted(): bool
    {
        return self::$booted;
    }

    /**
     * Resetear el container (útil para testing)
     */
    public static function reset(): void
    {
        self::$instances = [];
        self::$factories = [];
        self::$booted = false;
    }
}
