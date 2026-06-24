<?php

declare(strict_types=1);

namespace App\Controllers\Api;

/**
 * ClipController — Proxy hacia el VPS para recorte profesional de clips CCTV.
 *
 * Flujo:
 *   Frontend  →  POST /api/create-clip  →  ClipController  →  VPS convert.php (FFmpeg)
 *                                                            ←  {clipUrl}
 *               ← JSON {success, clipUrl, filename, duration}
 */
final class ClipController
{
    // URL del convert.php en el VPS
    private const VPS_URL = 'https://cctv.pomplay.com.pe/convert.php';

    // Clave compartida con el VPS
    private const VPS_KEY = '5a51e68bb363b9f212f631eccca1ac1b7a15c6f3c200991f6bcebfcc15524fc6';

    // Timeout máximo para la petición al VPS (segundos)
    private const VPS_TIMEOUT = 120;

    // Dominos permitidos como origen del video (seguridad)
    private const ALLOWED_VIDEO_HOSTS = [
        'cctv.pomplay.com.pe',
        'localhost',
        '127.0.0.1',
    ];

    /**
     * POST /api/create-clip
     *
     * Parámetros esperados (JSON body):
     *   videoUrl   string  URL absoluta del MP4 original en el VPS
     *   startTime  float   Tiempo de inicio en segundos
     *   endTime    float   Tiempo de fin en segundos
     *   duration   float   Duración calculada (endTime - startTime)
     *   cameraId   string  (opcional) ID de cámara
     *   zoom       float   (opcional) Nivel de zoom actual
     *   panX       float   (opcional) Posición pan X
     *   panY       float   (opcional) Posición pan Y
     */
    public function create(): void
    {
        // Cabeceras CORS y Content-Type
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

        // Responder a preflight OPTIONS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Solo aceptar POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            return;
        }

        // Parsear body JSON
        $body  = file_get_contents('php://input');
        $input = json_decode($body ?: '{}', true) ?? [];

        // ── Validación de parámetros ────────────────────────────────
        $videoUrl  = trim($input['videoUrl']  ?? '');
        $startTime = (float)($input['startTime'] ?? -1);
        $endTime   = (float)($input['endTime']   ?? -1);

        if (empty($videoUrl)) {
            $this->jsonError(400, 'videoUrl es requerido');
            return;
        }

        if ($startTime < 0 || $endTime <= 0 || $endTime <= $startTime) {
            $this->jsonError(400, 'startTime y endTime inválidos');
            return;
        }

        $duration = $endTime - $startTime;
        if ($duration < 1) {
            $this->jsonError(400, 'El clip debe tener al menos 1 segundo de duración');
            return;
        }

        if ($duration > 7200) {
            $this->jsonError(400, 'El clip no puede superar 2 horas de duración');
            return;
        }

        // ── Validar que la URL del video sea de un host permitido ──
        $parsedUrl = parse_url($videoUrl);
        $host = strtolower($parsedUrl['host'] ?? '');
        $isAllowed = false;
        foreach (self::ALLOWED_VIDEO_HOSTS as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                $isAllowed = true;
                break;
            }
        }
        if (!$isAllowed) {
            $this->jsonError(403, 'La URL del video no es de un dominio permitido');
            return;
        }

        // ── Parámetros opcionales ───────────────────────────────────
        $cameraId = $input['cameraId'] ?? null;
        $zoom     = (float)($input['zoom'] ?? 1);
        $panX     = (float)($input['panX'] ?? 0);
        $panY     = (float)($input['panY'] ?? 0);

        // ── Enviar petición al VPS ──────────────────────────────────
        $vpsResult = $this->callVps($videoUrl, $startTime, $endTime, $zoom, $panX, $panY);

        if (!$vpsResult['success']) {
            http_response_code(502);
            echo json_encode([
                'success' => false,
                'error'   => $vpsResult['error'] ?? 'Error al procesar el clip en el servidor',
            ]);
            return;
        }

        // ── Respuesta exitosa ───────────────────────────────────────
        echo json_encode([
            'success'  => true,
            'clipUrl'  => $vpsResult['clipUrl'],
            'filename' => $vpsResult['filename'] ?? basename($vpsResult['clipUrl']),
            'duration' => $duration,
            'startTime' => $startTime,
            'endTime'   => $endTime,
            'cameraId'  => $cameraId,
            'message'  => 'Clip generado exitosamente',
        ]);
    }

    /**
     * Llama al convert.php del VPS con los parámetros de recorte.
     *
     * El VPS (nuevo modo) espera POST con:
     *   mode      = 'clip'
     *   key       = API_KEY
     *   video_url = URL completa del MP4 original
     *   start     = segundos de inicio
     *   end       = segundos de fin
     *   zoom      = factor de zoom (1 = sin zoom)
     *   pan_x     = desplazamiento X (porcentaje)
     *   pan_y     = desplazamiento Y (porcentaje)
     *
     * El VPS responde JSON:
     *   {ok: true, clip_url: "https://cctv.pomplay.com.pe/clips/abc.mp4", filename: "abc.mp4"}
     */
    private function callVps(
        string $videoUrl,
        float  $startTime,
        float  $endTime,
        float  $zoom = 1.0,
        float  $panX = 0.0,
        float  $panY = 0.0
    ): array {
        $postData = http_build_query([
            'mode'      => 'clip',
            'key'       => self::VPS_KEY,
            'video_url' => $videoUrl,
            'start'     => $startTime,
            'end'       => $endTime,
            'zoom'      => $zoom,
            'pan_x'     => $panX,
            'pan_y'     => $panY,
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n"
                                 . "Content-Length: " . strlen($postData) . "\r\n"
                                 . "X-Api-Key: " . self::VPS_KEY . "\r\n",
                'content'       => $postData,
                'timeout'       => self::VPS_TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents(self::VPS_URL, false, $ctx);

        if ($raw === false) {
            return ['success' => false, 'error' => 'No se pudo conectar al servidor de procesamiento'];
        }

        // Obtener código HTTP de respuesta
        $httpCode = 200;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $h, $m)) {
                    $httpCode = (int)$m[1];
                }
            }
        }

        $json = json_decode($raw, true);

        if ($httpCode >= 400 || !$json) {
            $errMsg = $json['error'] ?? $json['message'] ?? ('Error VPS HTTP ' . $httpCode);
            return ['success' => false, 'error' => $errMsg];
        }

        // El VPS devuelve {ok: true, clip_url: "..."}
        if (!empty($json['ok']) && !empty($json['clip_url'])) {
            return [
                'success'  => true,
                'clipUrl'  => $json['clip_url'],
                'filename' => $json['filename'] ?? basename($json['clip_url']),
            ];
        }

        // El VPS puede devolver {success: true, clip_url: "..."} también
        if (!empty($json['success']) && !empty($json['clip_url'])) {
            return [
                'success'  => true,
                'clipUrl'  => $json['clip_url'],
                'filename' => $json['filename'] ?? basename($json['clip_url']),
            ];
        }

        $errMsg = $json['error'] ?? $json['message'] ?? 'Respuesta inesperada del servidor VPS';
        return ['success' => false, 'error' => $errMsg];
    }

    /**
     * Emitir respuesta de error JSON
     */
    private function jsonError(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
    }

    /**
     * GET /api/download-clip?url=...&filename=...
     *
     * Proxy de descarga: recupera el MP4 del VPS y lo sirve con
     * Content-Disposition: attachment para forzar descarga real en el navegador.
     * Necesario porque el atributo `download` en <a> es ignorado para URLs
     * de dominios distintos (cross-origin).
     */
    public function download(): void
    {
        $clipUrl  = trim($_GET['url']      ?? '');
        $filename = trim($_GET['filename'] ?? '');

        // Validar que la URL sea del dominio permitido
        if (empty($clipUrl)) {
            http_response_code(400);
            echo 'URL requerida';
            return;
        }

        $parsed = parse_url($clipUrl);
        $host   = strtolower($parsed['host'] ?? '');
        $isAllowed = false;
        foreach (self::ALLOWED_VIDEO_HOSTS as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                $isAllowed = true;
                break;
            }
        }
        if (!$isAllowed) {
            http_response_code(403);
            echo 'Dominio no permitido';
            return;
        }

        // Nombre de archivo seguro
        if (empty($filename)) {
            $filename = basename($parsed['path'] ?? 'clip.mp4');
        }
        // Sanitizar: solo caracteres seguros
        $filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $filename);
        if (!str_ends_with(strtolower($filename), '.mp4')) {
            $filename .= '.mp4';
        }

        // Obtener el archivo del VPS via stream
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 60,
                'ignore_errors' => true,
            ],
        ]);

        $data = @file_get_contents($clipUrl, false, $ctx);

        if ($data === false || strlen($data) < 1000) {
            http_response_code(502);
            echo 'No se pudo obtener el clip del servidor';
            return;
        }

        // Verificar HTTP status del VPS
        $httpCode = 200;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $h, $m)) {
                    $httpCode = (int)$m[1];
                }
            }
        }
        if ($httpCode >= 400) {
            http_response_code(502);
            echo 'El clip no está disponible (HTTP ' . $httpCode . ')';
            return;
        }

        // Servir con cabeceras de descarga forzada
        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        echo $data;
    }
}
