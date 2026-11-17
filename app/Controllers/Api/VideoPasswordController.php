<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\PasswordPolicy;
use App\Core\RateLimiter;
use App\Repositories\VideoPasswordRepository;

final class VideoPasswordController
{
    public function __construct(private VideoPasswordRepository $videos)
    {
    }

    public function verify(string $codigoVideo): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $rateLimitKey = 'video_password_' . $this->getClientIp() . '_' . $codigoVideo;
        $maxAttempts = 5;
        $windowSeconds = 900;

        if (!RateLimiter::check($rateLimitKey, $maxAttempts, $windowSeconds)) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Demasiados intentos. Inténtalo más tarde.',
                'retry_after' => RateLimiter::retryAfter($rateLimitKey),
            ]);
            return;
        }

        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        $password = is_array($input) ? (string) ($input['password'] ?? '') : '';
        $video = $this->videos->findVideo($codigoVideo);

        if ($video === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Video no encontrado']);
            return;
        }

        if (empty($video['password_hash'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Este video no requiere contraseña']);
            return;
        }

        if ($password === '' || strlen($password) > 72 || !password_verify($password, $video['password_hash'])) {
            RateLimiter::increment($rateLimitKey, $windowSeconds);
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'valid' => false,
                'message' => 'Contraseña incorrecta',
                'remaining' => RateLimiter::remaining($rateLimitKey, $maxAttempts, $windowSeconds),
            ]);
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[$this->accessKey($codigoVideo)] = [
            'granted_at' => time(),
            'expires_at' => time() + 7200,
            'password_fingerprint' => hash('sha256', $video['password_hash']),
        ];

        http_response_code(200);
        echo json_encode(['success' => true, 'valid' => true, 'message' => 'Contraseña verificada']);
    }

    public function update(string $codigoVideo): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Auth::isAuthenticated() || (!Auth::isOwner() && !Auth::isAdmin())) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            return;
        }

        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        $input = is_array($input) ? $input : [];
        $csrfToken = (string) ($input['csrf_token'] ?? '');
        $sessionToken = (string) ($_SESSION['video_password_csrf'] ?? '');
        if ($csrfToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
            return;
        }

        $video = $this->videos->findVideo($codigoVideo);
        if ($video === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Video no encontrado']);
            return;
        }

        if (Auth::isOwner()) {
            $propietarioId = (int) (Auth::getPropietarioId() ?? 0);
            if ($propietarioId <= 0 || !$this->videos->canManageVideo($codigoVideo, $propietarioId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'No tienes permiso para modificar este video']);
                return;
            }
        }

        $removePassword = filter_var($input['remove'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $password = (string) ($input['password'] ?? '');
        if (!$removePassword && !PasswordPolicy::meetsRequirements($password)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'La contraseña debe tener al menos 8 caracteres e incluir mayúscula, minúscula, número y símbolo (máximo 72 bytes)',
            ]);
            return;
        }

        $this->videos->updatePassword($codigoVideo, $removePassword ? null : $password);
        unset($_SESSION[$this->accessKey($codigoVideo)]);

        echo json_encode([
            'success' => true,
            'protected' => !$removePassword,
            'message' => $removePassword
                ? 'Se quitó la contraseña del video'
                : 'Se actualizó la contraseña del video',
        ]);
    }

    public static function hasTemporaryAccess(string $codigoVideo, string $passwordHash): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $accessKey = self::makeAccessKey($codigoVideo);
        $access = $_SESSION[$accessKey] ?? null;
        if (!is_array($access)) {
            return false;
        }

        if (
            time() > (int) ($access['expires_at'] ?? 0)
            || !hash_equals(
                (string) ($access['password_fingerprint'] ?? ''),
                hash('sha256', $passwordHash)
            )
        ) {
            unset($_SESSION[$accessKey]);
            return false;
        }

        return true;
    }

    private function accessKey(string $codigoVideo): string
    {
        return self::makeAccessKey($codigoVideo);
    }

    private static function makeAccessKey(string $codigoVideo): string
    {
        return 'video_access_' . $codigoVideo;
    }

    private function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
