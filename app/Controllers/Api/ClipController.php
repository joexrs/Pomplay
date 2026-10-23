<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Repositories\EstadisticasRepository;

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
    public function __construct(
        private EstadisticasRepository $estadisticas
    ) {
    }

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
        $cameraId     = $input['cameraId'] ?? null;
        $codigoVideo  = trim($input['codigoVideo'] ?? '');
        $idLocal      = isset($input['idLocal']) ? (int) $input['idLocal'] : null;
        $codigoCancha = trim($input['codigoCancha'] ?? '');
        $zoom         = (float)($input['zoom'] ?? 1);
        $panX         = (float)($input['panX'] ?? 0);
        $panY         = (float)($input['panY'] ?? 0);

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
        $this->estadisticas->logClip([
            'codigo_video'  => $codigoVideo !== '' ? $codigoVideo : null,
            'id_local'      => $idLocal ?: null,
            'codigo_cancha' => $codigoCancha !== '' ? $codigoCancha : null,
            'video_url'     => $videoUrl,
            'clip_url'      => $vpsResult['clipUrl'],
            'filename'      => $vpsResult['filename'] ?? basename($vpsResult['clipUrl']),
            'start_time'    => $startTime,
            'end_time'      => $endTime,
            'duracion'      => $duration,
            'id_camara'     => is_numeric($cameraId) ? (int) $cameraId : null,
            'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

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
            'key'       => (string) env('VPS_KEY', ''),
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
                                 . "X-Api-Key: " . (string) env('VPS_KEY', '') . "\r\n",
                'content'       => $postData,
                'timeout'       => self::VPS_TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);

        $vpsUrl = (string) env('VPS_CONVERT_URL', 'https://cctv.pomplay.com.pe/convert.php');
        $raw = @file_get_contents($vpsUrl, false, $ctx);

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
     *
     * Usa fopen/fread por bloques para no cargar videos grandes en memoria.
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
            $filename = basename($parsed['path'] ?? 'video.mp4');
        }
        $filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $filename);
        if (!str_ends_with(strtolower($filename), '.mp4')) {
            $filename .= '.mp4';
        }

        // Aumentar tiempos para videos grandes (sin límite de ejecución)
        @set_time_limit(0);
        @ini_set('memory_limit', '128M');

        // Abrir stream del archivo en el VPS sin cargar todo en RAM
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 600,     // 10 min máximo para streamear el archivo
                'ignore_errors' => true,
            ],
        ]);

        $stream = @fopen($clipUrl, 'rb', false, $ctx);

        if (!$stream) {
            http_response_code(502);
            echo 'No se pudo conectar al servidor de video';
            return;
        }

        // Leer código HTTP de la respuesta del VPS
        $httpCode = 200;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $h, $m)) {
                    $httpCode = (int)$m[1];
                }
            }
        }

        if ($httpCode >= 400) {
            fclose($stream);
            http_response_code(502);
            echo 'El archivo no está disponible (HTTP ' . $httpCode . ')';
            return;
        }

        // Intentar obtener Content-Length del VPS para pasarlo al browser
        $contentLength = null;
        foreach ($http_response_header as $h) {
            if (stripos($h, 'Content-Length:') === 0) {
                $contentLength = trim(substr($h, strlen('Content-Length:')));
                break;
            }
        }

        // Emitir cabeceras de descarga
        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        if ($contentLength !== null) {
            header('Content-Length: ' . $contentLength);
        }
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        // Limpiar cualquier buffer de salida previo
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Streamear el archivo en bloques de 256 KB → memoria constante
        while (!feof($stream)) {
            $chunk = fread($stream, 262144); // 256 KB
            if ($chunk === false) break;
            echo $chunk;
            flush();
        }

        fclose($stream);
    }

    /** Descarga completa por una ruta propia para archivos largos. */
    public function downloadFullVideo(): void
    {
        $url = trim($_GET['url'] ?? '');
        if (!$this->isAllowedVideoUrl($url)) {
            http_response_code($url === '' ? 400 : 403);
            echo $url === '' ? 'URL requerida' : 'Dominio no permitido';
            return;
        }

        // Mandar el navegador al VPS para que el hosting compartido no tenga
        // que mantener abierta una conexión PHP durante toda la transferencia.
        $parts = parse_url($url);
        $videoPath = rawurldecode($parts['path'] ?? '');
        if (strtolower($parts['host'] ?? '') === 'cctv.pomplay.com.pe'
            && preg_match('#^/videos/(\d+)/([A-Za-z0-9_-]+)/([A-Za-z0-9_.-]+\.mp4)$#i', $videoPath, $matches)) {
            $secret = (string) env('VPS_KEY', '');
            if ($secret !== '') {
                $relativePath = $matches[1] . '/' . $matches[2] . '/' . $matches[3];
                $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', trim($_GET['filename'] ?? basename($relativePath)));
                if (!str_ends_with(strtolower($filename), '.mp4')) $filename .= '.mp4';
                $expires = time() + 21600;
                $signature = hash_hmac('sha256', $relativePath . "\n" . $filename . "\n" . $expires, $secret);
                $downloadUrl = 'https://cctv.pomplay.com.pe/download.php?' . http_build_query([
                    'path' => $relativePath,
                    'filename' => $filename,
                    'expires' => $expires,
                    'signature' => $signature,
                ]);
                header('Cache-Control: no-store');
                header('Location: ' . $downloadUrl, true, 302);
                return;
            }
        }

        if (!function_exists('curl_init')) {
            http_response_code(503);
            echo 'El servidor no tiene cURL habilitado para descargar videos grandes';
            return;
        }

        $filename = trim($_GET['filename'] ?? 'video_completo.mp4');
        $filename = preg_replace('/[^a-zA-Z0-9_\\-.]/', '_', $filename);
        if (!str_ends_with(strtolower($filename), '.mp4')) $filename .= '.mp4';

        @set_time_limit(0);
        @ini_set('memory_limit', '128M');
        while (ob_get_level() > 0) @ob_end_clean();

        // LiteSpeed/PHP puede no exponer Content-Range del origen al callback;
        // obtener el tamaño por HEAD permite reconstruirlo para reanudaciones.
        $totalSize = null;
        $head = curl_init($url);
        curl_setopt_array($head, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        curl_exec($head);
        $headSize = curl_getinfo($head, CURLINFO_CONTENT_LENGTH_DOWNLOAD_T);
        if (is_numeric($headSize) && (int) $headSize > 0) $totalSize = (int) $headSize;
        curl_close($head);

        $status = 0;
        $contentLength = null;
        $contentRange = null;
        $contentType = 'video/mp4';
        $started = false;
        $transferred = 0;
        $clientRange = trim($_SERVER['HTTP_RANGE'] ?? '');
        if ($clientRange !== '' && !preg_match('/^bytes=\d*-\d*$/i', $clientRange)) {
            http_response_code(416);
            echo 'Rango de descarga no válido';
            return;
        }
        $ch = curl_init($url);
        $curlOptions = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => 300,
            CURLOPT_BUFFERSIZE => 262144,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$status, &$contentLength, &$contentType): int {
                $length = strlen($line);
                if (preg_match('/^HTTP\\/[\\d.]+\\s+(\\d+)/i', $line, $match)) {
                    $status = (int) $match[1];
                    $contentLength = null;
                    $contentRange = null;
                    $contentType = 'video/mp4';
                } elseif (stripos($line, 'Content-Length:') === 0) {
                    $contentLength = trim(substr($line, strlen('Content-Length:')));
                } elseif (stripos($line, 'Content-Range:') === 0) {
                    $contentRange = trim(substr($line, strlen('Content-Range:')));
                } elseif (stripos($line, 'Content-Type:') === 0) {
                    $contentType = trim(substr($line, strlen('Content-Type:')));
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$status, &$contentLength, &$contentRange, &$contentType, &$started, &$transferred, $filename, $clientRange, $totalSize): int {
                if ($status < 200 || $status >= 300 || ($clientRange !== '' && $status !== 206)) return 0;
                if (!$started) {
                    if ($status === 206 && $contentRange === null && $totalSize !== null) {
                        $rangeValue = substr($clientRange, 6);
                        if (preg_match('/^(\d*)-(\d*)$/', $rangeValue, $rangeMatch)) {
                            if ($rangeMatch[1] === '') {
                                $suffixLength = (int) $rangeMatch[2];
                                $rangeStart = max(0, $totalSize - $suffixLength);
                                $rangeEnd = $totalSize - 1;
                            } else {
                                $rangeStart = (int) $rangeMatch[1];
                                $rangeEnd = $rangeMatch[2] === '' ? $totalSize - 1 : min((int) $rangeMatch[2], $totalSize - 1);
                            }
                            if ($rangeStart <= $rangeEnd) $contentRange = "bytes {$rangeStart}-{$rangeEnd}/{$totalSize}";
                        }
                    }
                    if ($status === 206 && $contentRange === null) return 0;
                    if ($status === 206) http_response_code(206);
                    header('Content-Type: ' . $contentType);
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    if ($contentLength !== null && ctype_digit($contentLength)) header('Content-Length: ' . $contentLength);
                    if ($contentRange !== null) header('Content-Range: ' . $contentRange);
                    header('Accept-Ranges: bytes');
                    header('Cache-Control: no-store');
                    header('X-Content-Type-Options: nosniff');
                    $started = true;
                }
                if (connection_aborted()) return 0;
                $written = strlen($chunk);
                echo $chunk;
                $transferred += $written;
                flush();
                return $written;
            },
        ];
        if ($clientRange !== '') $curlOptions[CURLOPT_RANGE] = substr($clientRange, 6);
        curl_setopt_array($ch, $curlOptions);

        $ok = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$started) {
            http_response_code(502);
            echo $clientRange !== '' && $httpCode === 200
                ? 'El servidor de video no admite reanudar esta descarga.'
                : 'No se pudo iniciar la descarga del video (HTTP ' . $httpCode . ').';
        }
        if ($ok === false && !connection_aborted()) {
            error_log(sprintf('[download-full-video] Transferencia interrumpida. HTTP %d, bytes enviados %d, tamaño esperado %s, error: %s', $httpCode, $transferred, $contentLength ?? 'desconocido', $curlError));
        } elseif ($contentLength !== null && ctype_digit($contentLength) && $transferred !== (int) $contentLength) {
            error_log(sprintf('[download-full-video] Tamaño incompleto. Bytes enviados %d de %s.', $transferred, $contentLength));
        }
    }

    /** Comprueba en el VPS si el video original ya está accesible. */
    public function checkFullVideo(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $url = trim($_GET['url'] ?? '');
        if (!$this->isAllowedVideoUrl($url)) {
            http_response_code($url === '' ? 400 : 403);
            echo json_encode(['ready' => false, 'error' => $url === '' ? 'URL requerida' : 'Dominio no permitido']);
            return;
        }

        if (!function_exists('curl_init')) {
            http_response_code(503);
            echo json_encode(['ready' => false, 'error' => 'No está disponible la comprobación del servidor']);
            return;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        echo json_encode(['ready' => $status >= 200 && $status < 400, 'error' => $status >= 400 ? 'El archivo aún no está disponible (HTTP ' . $status . ')' : ($error ?: null)]);
    }

    private function isAllowedVideoUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $host = strtolower($parsed['host'] ?? '');
        foreach (self::ALLOWED_VIDEO_HOSTS as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) return true;
        }
        return false;
    }
}

