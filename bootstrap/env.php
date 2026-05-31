<?php

/**
 * Carga las variables de entorno desde el archivo .env
 */
function loadEnv(string $path = __DIR__ . '/../.env'): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        // Parsear líneas en formato KEY=VALUE
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Eliminar comillas si existen
            $value = trim($value, '\'"');

            $_ENV[$key] = $value;
            if (!array_key_exists($key, $_SERVER)) {
                $_SERVER[$key] = $value;
            }
        }
    }
}

/**
 * Obtiene una variable de entorno con valor por defecto
 */
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

// Cargar variables al iniciar
loadEnv();
