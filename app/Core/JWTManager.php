<?php

namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

final class JWTManager
{
    private static ?string $secretKey = null;
    private const ALGORITHM = 'HS256';
    private const TOKEN_EXPIRY = 86400; // 24 horas en segundos
    private const REFRESH_TOKEN_EXPIRY = 604800; // 7 días en segundos

    /**
     * Obtener la clave secreta desde .env
     */
    private static function getSecretKey(): string
    {
        if (self::$secretKey === null) {
            self::$secretKey = self::readEnvValue('JWT_SECRET');

            // Si no existe, generar una y guardarla
            if (self::$secretKey === null) {
                self::$secretKey = bin2hex(random_bytes(32));
                self::saveSecretKeyToEnv(self::$secretKey);
            }
        }

        return self::$secretKey;
    }

    private static function readEnvValue(string $key): ?string
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (!is_readable($envFile)) {
            return null;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $line, 2), 2, null);
            if (trim($name) !== $key || $value === null) {
                continue;
            }

            return trim($value, " \t\n\r\0\x0B\"'");
        }

        return null;
    }

    /**
     * Guardar la clave secreta en el archivo .env
     */
    private static function saveSecretKeyToEnv(string $key): void
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        $content = file_exists($envFile) ? file_get_contents($envFile) : '';
        
        // Si ya existe JWT_SECRET, no hacer nada
        if (strpos($content, 'JWT_SECRET=') !== false) {
            return;
        }

        // Agregar JWT_SECRET al final del archivo
        $content .= "\n# JWT Configuration\n";
        $content .= "JWT_SECRET=" . $key . "\n";
        
        file_put_contents($envFile, $content);
    }

    /**
     * Generar un token JWT
     */
    public static function generateToken(array $payload): string
    {
        $issuedAt = time();
        $expire = $issuedAt + self::TOKEN_EXPIRY;

        $tokenPayload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expire,
            'jti' => bin2hex(random_bytes(16)) // Token ID único
        ]);

        return JWT::encode($tokenPayload, self::getSecretKey(), self::ALGORITHM);
    }

    /**
     * Generar un refresh token (con mayor duración)
     */
    public static function generateRefreshToken(int $userId): string
    {
        $issuedAt = time();
        $expire = $issuedAt + self::REFRESH_TOKEN_EXPIRY;

        $payload = [
            'user_id' => $userId,
            'type' => 'refresh',
            'iat' => $issuedAt,
            'exp' => $expire,
            'jti' => bin2hex(random_bytes(16))
        ];

        return JWT::encode($payload, self::getSecretKey(), self::ALGORITHM);
    }

    /**
     * Validar y decodificar un token JWT
     */
    public static function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::getSecretKey(), self::ALGORITHM));
            return (array) $decoded;
        } catch (Exception $e) {
            error_log('JWT Validation Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verificar si un token ha expirado
     */
    public static function isTokenExpired(string $token): bool
    {
        try {
            $decoded = JWT::decode($token, new Key(self::getSecretKey(), self::ALGORITHM));
            return false;
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * Obtener el payload de un token sin validar la expiración
     */
    public static function getPayload(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            return $payload;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Renovar un token si está próximo a expirar (menos de 1 hora)
     */
    public static function refreshIfNeeded(string $token): ?string
    {
        $payload = self::validateToken($token);
        
        if ($payload === null) {
            return null;
        }

        $exp = $payload['exp'] ?? 0;
        $now = time();
        $timeUntilExpiry = $exp - $now;

        // Si quedan menos de 1 hora, renovar el token
        if ($timeUntilExpiry < 3600) {
            unset($payload['iat'], $payload['exp'], $payload['jti']);
            return self::generateToken($payload);
        }

        return $token;
    }

    /**
     * Invalidar un token (agregar a lista negra)
     * Nota: Esto requeriría una tabla en la base de datos para tokens revocados
     */
    public static function revokeToken(string $token): bool
    {
        $payload = self::getPayload($token);
        
        if ($payload === null || !isset($payload['jti'])) {
            return false;
        }

        // Aquí podrías guardar el JTI en una tabla de tokens revocados
        // Por ahora, solo retornamos true
        return true;
    }

    /**
     * Crear tokens de acceso y refresh para un usuario
     */
    public static function createAuthTokens(array $userData): array
    {
        $accessToken = self::generateToken([
            'user_id' => $userData['id_usuario'],
            'usuario' => $userData['usuario'],
            'rol' => $userData['rol'],
            'nombre_completo' => $userData['nombre_completo'] ?? $userData['usuario'],
            'id_propietario' => $userData['id_propietario'] ?? null,
            'id_local' => $userData['id_local'] ?? null
        ]);

        $refreshToken = self::generateRefreshToken($userData['id_usuario']);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => self::TOKEN_EXPIRY
        ];
    }
}
