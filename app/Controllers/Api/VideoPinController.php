<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Repositories\VideoPinRepository;

/**
 * Controlador API para generación y verificación de PINs de video.
 */
final class VideoPinController
{
    public function __construct(private VideoPinRepository $videoPins)
    {
    }

    /**
     * POST /api/videos/{id}/generar-pin
     * Genera un nuevo PIN para un video. Solo accesible por OWNER (dueños).
     */
    public function generate(string $codigoVideo): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Verificar autenticación (owner o admin)
        if (!Auth::isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            return;
        }

        if (!Auth::isOwner() && !Auth::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            return;
        }

        // Validar que el video existe y pertenece a un local privado
        if (!$this->videoPins->isVideoPrivate($codigoVideo)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Este video no pertenece a un local privado',
            ]);
            return;
        }

        // Obtener parámetros del body
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $longitud = (int) ($input['longitud'] ?? 6);
        $minutosExpiracion = (int) ($input['minutos_expiracion'] ?? 30);

        try {
            $result = $this->videoPins->generatePin($codigoVideo, $longitud, $minutosExpiracion);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'pin' => $result['pin'], // Solo se muestra esta vez
                'expira_en' => $result['expira_en'],
                'longitud' => $result['longitud'],
                'message' => 'PIN generado exitosamente',
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al generar el PIN',
            ]);
        }
    }

    /**
     * POST /api/videos/{id}/verificar-pin
     * Verifica un PIN ingresado por el usuario. Acceso público con rate limiting.
     */
    public function verify(string $codigoVideo): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Rate limiting: 5 intentos cada 15 minutos por IP + video
        $clientIp = $this->getClientIp();
        $rateLimitKey = 'pin_verify_' . $clientIp . '_' . $codigoVideo;
        $maxAttempts = 5;
        $windowSeconds = 900; // 15 minutos

        if (!RateLimiter::check($rateLimitKey, $maxAttempts, $windowSeconds)) {
            $retryAfter = RateLimiter::retryAfter($rateLimitKey);
            $minutosRestantes = (int) ceil($retryAfter / 60);

            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => "Demasiados intentos. Espera {$minutosRestantes} minuto(s).",
                'retry_after' => $retryAfter,
                'remaining' => 0,
            ]);
            return;
        }

        // Obtener PIN del body
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $pinIngresado = trim((string) ($input['pin'] ?? ''));

        if ($pinIngresado === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Debes ingresar un código de acceso',
            ]);
            return;
        }

        // Validar formato (solo dígitos, 4 o 6)
        if (!preg_match('/^\d{4,6}$/', $pinIngresado)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Formato de código inválido',
            ]);
            return;
        }

        try {
            $result = $this->videoPins->verifyPin($codigoVideo, $pinIngresado);

            if ($result['valid']) {
                // PIN válido — generar token de acceso temporal en sesión
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }

                // Almacenar acceso temporal para este video (válido por 2 horas)
                $accessKey = 'video_access_' . $codigoVideo;
                $_SESSION[$accessKey] = [
                    'granted_at' => time(),
                    'expires_at' => time() + 7200, // 2 horas
                ];

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'valid' => true,
                    'message' => 'Código verificado correctamente',
                ]);
                return;
            }

            // PIN inválido — incrementar rate limiter
            RateLimiter::increment($rateLimitKey, $windowSeconds);
            $remaining = RateLimiter::remaining($rateLimitKey, $maxAttempts, $windowSeconds);

            if ($result['expired']) {
                http_response_code(200);
                echo json_encode([
                    'success' => false,
                    'valid' => false,
                    'expired' => true,
                    'message' => 'El código ha expirado. Solicita uno nuevo al propietario.',
                    'remaining' => $remaining,
                ]);
                return;
            }

            if ($result['not_found']) {
                http_response_code(200);
                echo json_encode([
                    'success' => false,
                    'valid' => false,
                    'message' => 'No existe un código activo para este video. Solicita uno al propietario.',
                    'remaining' => $remaining,
                ]);
                return;
            }

            // PIN incorrecto
            http_response_code(200);
            echo json_encode([
                'success' => false,
                'valid' => false,
                'message' => 'Código incorrecto',
                'remaining' => $remaining,
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al verificar el código',
            ]);
        }
    }

    /**
     * Verificar si el usuario tiene acceso temporal a un video (por sesión).
     */
    public static function hasTemporaryAccess(string $codigoVideo): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $accessKey = 'video_access_' . $codigoVideo;
        $access = $_SESSION[$accessKey] ?? null;

        if ($access === null) {
            return false;
        }

        // Verificar si el acceso temporal no ha expirado
        if (time() > ($access['expires_at'] ?? 0)) {
            unset($_SESSION[$accessKey]);
            return false;
        }

        return true;
    }

    /**
     * Obtener la IP del cliente de forma segura.
     */
    private function getClientIp(): string
    {
        // Priorizar headers de proxies comunes, pero sanitizar
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            $ip = $_SERVER[$header] ?? '';
            if ($ip !== '') {
                // Si hay múltiples IPs (X-Forwarded-For), tomar la primera
                $ip = explode(',', $ip)[0];
                $ip = trim($ip);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
