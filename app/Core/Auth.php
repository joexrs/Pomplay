<?php

namespace App\Core;

use App\Core\JWTManager;

final class Auth
{
    private const JWT_COOKIE_NAME = 'pomplay_token';
    private const REFRESH_COOKIE_NAME = 'pomplay_refresh';

    /**
     * Verificar si el usuario está autenticado
     */
    public static function isAuthenticated(): bool
    {
        // Primero intentar con JWT (solo si está disponible)
        if (class_exists('App\Core\JWTManager') && isset($_COOKIE[self::JWT_COOKIE_NAME])) {
            $token = $_COOKIE[self::JWT_COOKIE_NAME];
            $payload = JWTManager::validateToken($token);
            
            if ($payload !== null) {
                // Token válido, sincronizar con sesión
                self::syncSessionFromToken($payload);
                
                // Intentar renovar el token si está próximo a expirar
                $newToken = JWTManager::refreshIfNeeded($token);
                if ($newToken !== $token) {
                    self::setTokenCookie($newToken);
                }
                
                return true;
            }
            
            // Token inválido, intentar con refresh token
            if (isset($_COOKIE[self::REFRESH_COOKIE_NAME])) {
                $refreshed = self::refreshAccessToken();
                if ($refreshed) {
                    return true;
                }
            }
            
            // Limpiar cookies inválidas
            self::clearTokenCookies();
        }

        // Fallback a sesión tradicional
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return !empty($_SESSION['loggedin']) && !empty($_SESSION['user_id']);
    }

    /**
     * Sincronizar sesión desde el token JWT
     */
    private static function syncSessionFromToken(array $payload): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_id'] = $payload['user_id'] ?? null;
        $_SESSION['usuario'] = $payload['usuario'] ?? null;
        $_SESSION['rol'] = $payload['rol'] ?? null;
        $_SESSION['nombre_completo'] = $payload['nombre_completo'] ?? null;
        $_SESSION['id_propietario'] = $payload['id_propietario'] ?? null;
        $_SESSION['id_local'] = $payload['id_local'] ?? null;
        $_SESSION['loggedin'] = true;
        $_SESSION['admin_id'] = $payload['user_id'] ?? null; // Compatibilidad
    }

    /**
     * Refrescar el access token usando el refresh token
     */
    private static function refreshAccessToken(): bool
    {
        if (!isset($_COOKIE[self::REFRESH_COOKIE_NAME])) {
            return false;
        }

        $refreshToken = $_COOKIE[self::REFRESH_COOKIE_NAME];
        $payload = JWTManager::validateToken($refreshToken);

        if ($payload === null || ($payload['type'] ?? '') !== 'refresh') {
            return false;
        }

        // Aquí deberías obtener los datos del usuario desde la BD
        // Por ahora, solo validamos que el token sea válido
        return false; // Implementar lógica de refresh completa
    }

    /**
     * Establecer cookie de token JWT
     */
    private static function setTokenCookie(string $token, int $expiry = 86400): void
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        setcookie(
            self::JWT_COOKIE_NAME,
            $token,
            [
                'expires' => time() + $expiry,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }

    /**
     * Establecer cookie de refresh token
     */
    private static function setRefreshCookie(string $token): void
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        setcookie(
            self::REFRESH_COOKIE_NAME,
            $token,
            [
                'expires' => time() + 604800, // 7 días
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }

    /**
     * Limpiar cookies de tokens
     */
    private static function clearTokenCookies(): void
    {
        setcookie(self::JWT_COOKIE_NAME, '', time() - 3600, '/');
        setcookie(self::REFRESH_COOKIE_NAME, '', time() - 3600, '/');
    }

    /**
     * Obtener el ID del usuario autenticado (compatible con admin_id antiguo)
     */
    public static function getUserId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
    }

    /**
     * Obtener el ID del admin autenticado (por compatibilidad)
     */
    public static function getAdminId(): ?int
    {
        return self::getUserId();
    }

    /**
     * Obtener el usuario autenticado
     */
    public static function getUser(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['usuario'] ?? null;
    }

    /**
     * Obtener el nombre completo del usuario autenticado
     */
    public static function getFullName(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['nombre_completo'] ?? null;
    }

    /**
     * Obtener el rol del usuario autenticado
     */
    public static function getRole(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['rol'] ?? null;
    }

    /**
     * Verificar si el usuario tiene un rol específico
     */
    public static function hasRole(string $role): bool
    {
        return self::getRole() === $role;
    }

    /**
     * Verificar si el usuario tiene uno de los roles específicos
     */
    public static function hasAnyRole(array $roles): bool
    {
        $userRole = self::getRole();
        return $userRole && in_array($userRole, $roles, true);
    }

    /**
     * Obtener el ID del local del usuario (si aplica)
     * Para propietarios, devuelve el local seleccionado de sesión
     */
    public static function getLocalId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['id_local'] ?? null;
    }

    /**
     * Obtener el ID del propietario del usuario (si aplica)
     */
    public static function getPropietarioId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['id_propietario'] ?? null;
    }

    /**
     * Establecer el local seleccionado para un propietario
     */
    public static function setSelectedLocal(int $localId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['id_local'] = $localId;
        
        // Actualizar el token JWT con el nuevo local (solo si está disponible)
        if (class_exists('App\Core\JWTManager') && isset($_COOKIE[self::JWT_COOKIE_NAME])) {
            $token = $_COOKIE[self::JWT_COOKIE_NAME];
            $payload = JWTManager::validateToken($token);
            
            if ($payload !== null) {
                $payload['id_local'] = $localId;
                unset($payload['iat'], $payload['exp'], $payload['jti']);
                $newToken = JWTManager::generateToken($payload);
                self::setTokenCookie($newToken);
            }
        }
    }

    /**
     * Obtener el email del usuario autenticado
     */
    public static function getEmail(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['email'] ?? null;
    }

    /**
     * Guardar sesión de usuario con soporte de roles y JWT
     */
    public static function login(
        int $userId,
        string $usuario,
        string $rol = 'ADMIN',
        ?int $idLocal = null,
        ?string $nombreCompleto = null,
        ?string $email = null,
        ?string $foto = null
    ): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Guardar en sesión
        $_SESSION['user_id'] = $userId;
        $_SESSION['usuario'] = $usuario;
        $_SESSION['rol'] = $rol;
        $_SESSION['id_local'] = $idLocal;
        $_SESSION['nombre_completo'] = $nombreCompleto ?? $usuario;
        $_SESSION['email'] = $email;
        $_SESSION['foto'] = $foto;
        $_SESSION['loggedin'] = true;
        $_SESSION['admin_id'] = $userId; // Compatibilidad
        $_SESSION['ultimo_acceso'] = date('Y-m-d H:i:s');

        // Generar tokens JWT solo si está disponible
        if (class_exists('App\Core\JWTManager')) {
            // Datos del usuario
            $userData = [
                'id_usuario' => $userId,
                'usuario' => $usuario,
                'rol' => $rol,
                'id_local' => $idLocal,
                'nombre_completo' => $nombreCompleto ?? $usuario,
                'email' => $email,
                'foto' => $foto,
                'id_propietario' => $_SESSION['id_propietario'] ?? null
            ];

            $tokens = JWTManager::createAuthTokens($userData);
            
            // Establecer cookies con los tokens
            self::setTokenCookie($tokens['access_token'], $tokens['expires_in']);
            self::setRefreshCookie($tokens['refresh_token']);
        }
    }

    /**
     * Cerrar sesión
     */
    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Revocar el token JWT si existe y está disponible
        if (class_exists('App\Core\JWTManager') && isset($_COOKIE[self::JWT_COOKIE_NAME])) {
            JWTManager::revokeToken($_COOKIE[self::JWT_COOKIE_NAME]);
        }

        // Limpiar todas las variables de sesión
        $_SESSION = [];

        // Eliminar la cookie de sesión si existe
        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Limpiar cookies de JWT
        self::clearTokenCookies();

        // Destruir la sesión
        session_destroy();
    }

    /**
     * Requerir autenticación. Si no está autenticado, redirige a login
     */
    public static function requireAuth(string $loginPath = '../login.php'): void
    {
        if (!self::isAuthenticated()) {
            header('Location: ' . $loginPath);
            exit;
        }
    }

    /**
     * Requerir un rol específico. Si no lo tiene, redirige o muestra error
     */
    public static function requireRole(string $role, string $redirectTo = '../login.php'): void
    {
        if (!self::isAuthenticated()) {
            header('Location: ' . $redirectTo);
            exit;
        }

        if (!self::hasRole($role)) {
            http_response_code(403);
            die('Acceso denegado. Se requiere rol: ' . htmlspecialchars($role));
        }
    }

    /**
     * Requerir uno de varios roles
     */
    public static function requireAnyRole(array $roles, string $redirectTo = '../login.php'): void
    {
        if (!self::isAuthenticated()) {
            header('Location: ' . $redirectTo);
            exit;
        }

        if (!self::hasAnyRole($roles)) {
            http_response_code(403);
            die('Acceso denegado. Se requiere uno de los roles: ' . implode(', ', $roles));
        }
    }

    /**
     * Obtener toda la información del usuario en la sesión
     */
    public static function getCurrentUser(): ?array
    {
        if (!self::isAuthenticated()) {
            return null;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return [
            'id_usuario' => self::getUserId(),
            'usuario' => self::getUser(),
            'nombre_completo' => self::getFullName(),
            'email' => self::getEmail(),
            'rol' => self::getRole(),
            'id_local' => self::getLocalId(),
            'foto' => $_SESSION['foto'] ?? null,
            'ultimo_acceso' => $_SESSION['ultimo_acceso'] ?? null,
        ];
    }

    /**
     * Es Admin (ADMIN o SUPER_ADMIN)
     */
    public static function isAdmin(): bool
    {
        return self::hasAnyRole(['ADMIN', 'SUPER_ADMIN']);
    }

    /**
     * Es Dueño
     */
    public static function isOwner(): bool
    {
        return self::hasRole('DUENO') || self::hasRole('DUEÑO');
    }

    /**
     * Es Super Admin
     */
    public static function isSuperAdmin(): bool
    {
        return self::hasRole('SUPER_ADMIN');
    }

    /**
     * Actualizar la hora del último acceso
     */
    public static function updateLastAccess(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['ultimo_acceso'] = date('Y-m-d H:i:s');
    }

    /**
     * Obtener el token JWT actual
     */
    public static function getToken(): ?string
    {
        return $_COOKIE[self::JWT_COOKIE_NAME] ?? null;
    }
}
