<?php

namespace App\Core;

/**
 * Rate Limiter basado en archivos
 * Almacena contadores de intentos en disco para prevenir fuerza bruta.
 */
final class RateLimiter
{
    private static string $storagePath = '';

    /**
     * Obtener la ruta de almacenamiento
     */
    private static function getStoragePath(): string
    {
        if (self::$storagePath === '') {
            self::$storagePath = dirname(__DIR__, 2) . '/temp/rate_limit';
            if (!is_dir(self::$storagePath)) {
                @mkdir(self::$storagePath, 0755, true);
            }
        }
        return self::$storagePath;
    }

    /**
     * Generar nombre de archivo seguro para la clave
     */
    private static function getFilePath(string $key): string
    {
        return self::getStoragePath() . '/' . md5($key) . '.json';
    }

    /**
     * Verificar si la clave ha excedido el límite de intentos
     *
     * @param string $key Clave única (ej: hash de IP + video_id)
     * @param int $maxAttempts Número máximo de intentos permitidos
     * @param int $windowSeconds Ventana de tiempo en segundos
     * @return bool true si aún tiene intentos disponibles, false si está bloqueado
     */
    public static function check(string $key, int $maxAttempts = 5, int $windowSeconds = 900): bool
    {
        $data = self::readData($key);

        if ($data === null) {
            return true; // No hay registros, puede continuar
        }

        // Verificar si la ventana ha expirado
        if (time() > $data['window_end']) {
            self::deleteFile($key);
            return true;
        }

        return $data['attempts'] < $maxAttempts;
    }

    /**
     * Incrementar el contador de intentos
     */
    public static function increment(string $key, int $windowSeconds = 900): void
    {
        $data = self::readData($key);

        if ($data === null || time() > ($data['window_end'] ?? 0)) {
            // Nuevo registro o ventana expirada
            $data = [
                'attempts' => 1,
                'window_start' => time(),
                'window_end' => time() + $windowSeconds,
            ];
        } else {
            $data['attempts']++;
        }

        self::writeData($key, $data);
    }

    /**
     * Obtener intentos restantes
     */
    public static function remaining(string $key, int $maxAttempts = 5, int $windowSeconds = 900): int
    {
        $data = self::readData($key);

        if ($data === null || time() > ($data['window_end'] ?? 0)) {
            return $maxAttempts;
        }

        return max(0, $maxAttempts - $data['attempts']);
    }

    /**
     * Obtener segundos restantes hasta que se reinicie la ventana
     */
    public static function retryAfter(string $key): int
    {
        $data = self::readData($key);

        if ($data === null) {
            return 0;
        }

        $remaining = ($data['window_end'] ?? 0) - time();
        return max(0, $remaining);
    }

    /**
     * Leer datos del archivo
     */
    private static function readData(string $key): ?array
    {
        $file = self::getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Escribir datos al archivo
     */
    private static function writeData(string $key, array $data): void
    {
        $file = self::getFilePath($key);
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Eliminar archivo de la clave
     */
    private static function deleteFile(string $key): void
    {
        $file = self::getFilePath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Limpiar archivos expirados (mantenimiento)
     * Llamar periódicamente o al generar nuevos PINs
     */
    public static function cleanup(int $maxAgeSeconds = 3600): void
    {
        $path = self::getStoragePath();
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '/*.json');
        if ($files === false) {
            return;
        }

        $now = time();
        foreach ($files as $file) {
            if ($now - filemtime($file) > $maxAgeSeconds) {
                @unlink($file);
            }
        }
    }
}
