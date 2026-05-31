<?php

namespace App\Core;

/**
 * Sistema de caché simple en memoria para mejorar el rendimiento
 */
final class Cache
{
    private static array $cache = [];
    private static int $ttl = 300; // 5 minutos por defecto

    /**
     * Obtener valor del caché
     */
    public static function get(string $key): mixed
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }

        $item = self::$cache[$key];
        
        // Verificar si expiró
        if (time() > $item['expires']) {
            unset(self::$cache[$key]);
            return null;
        }

        return $item['value'];
    }

    /**
     * Guardar valor en caché
     */
    public static function set(string $key, mixed $value, int $ttl = null): void
    {
        $ttl = $ttl ?? self::$ttl;
        self::$cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }

    /**
     * Generar clave de caché basada en parámetros
     */
    public static function generateKey(string $method, array $params = []): string
    {
        return $method . '_' . md5(serialize($params));
    }

    /**
     * Limpiar caché
     */
    public static function clear(): void
    {
        self::$cache = [];
    }

    /**
     * Ejecutar callback con caché
     */
    public static function remember(string $key, callable $callback, int $ttl = null): mixed
    {
        $value = self::get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        
        return $value;
    }
}
