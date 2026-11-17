<?php
/**
 * Video Proxy - Sirve videos con soporte completo de Range requests (streaming)
 * y headers CORS para permitir captureStream().
 *
 * FIX MÓVIL: Los navegadores móviles (iOS Safari, Android Chrome) envían
 * solicitudes Range (bytes parciales) para hacer streaming progresivo.
 * El proxy anterior descargaba TODO el video en RAM antes de enviarlo,
 * lo que causaba timeouts y fallos en dispositivos móviles con archivos grandes.
 *
 * Usage: /public/video-proxy.php?codigo_video=<video_code>
 */

// ── CORS ──────────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Range, Content-Type');
header('Access-Control-Expose-Headers: Content-Range, Content-Length, Accept-Ranges');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

use App\Controllers\Api\VideoPasswordController;
use App\Core\Database;
use App\Repositories\VideoPasswordRepository;

require_once __DIR__ . '/../bootstrap/autoload.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    exit;
}

$codigoVideo = trim((string) ($_GET['codigo_video'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_-]{1,10}$/', $codigoVideo)) {
    http_response_code(400);
    echo 'Invalid video code';
    exit;
}

$video = (new VideoPasswordRepository(Database::connection()))->findVideo($codigoVideo);
if ($video === null) {
    http_response_code(404);
    echo 'Video not found';
    exit;
}

$passwordHash = (string) ($video['password_hash'] ?? '');
if ($passwordHash !== '' && !VideoPasswordController::hasTemporaryAccess($codigoVideo, $passwordHash)) {
    http_response_code(403);
    echo 'Password required';
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$rawUrl = trim((string) ($video['video_url'] ?? ''));
if ($rawUrl === '') {
    http_response_code(404);
    echo 'Video source unavailable';
    exit;
}

$base = rtrim((string) env('CCTV_VIDEOS_BASE_URL', 'https://cctv.pomplay.com.pe/videos'), '/');
$videoUrl = $rawUrl;
if (!preg_match('#^https?://#i', $videoUrl)) {
    $path = str_replace('\\', '/', $videoUrl);
    $path = preg_replace('#^file:/+#i', '', $path) ?? $path;
    if (preg_match('#cctv\.pomplay\.com\.pe/(?:videos/)?(.+)$#i', $path, $matches)) {
        $path = $matches[1];
    } elseif (preg_match('#(?:^|/)videos/(.+)$#i', $path, $matches)) {
        $path = $matches[1];
    } elseif (preg_match('#(?:^|/)cctv/(.+)$#i', $path, $matches)) {
        $path = $matches[1];
    } else {
        $path = ltrim($path, '/');
    }

    $parts = array_filter(explode('/', $path), static fn ($part) => $part !== '');
    $videoUrl = $base . '/' . implode('/', array_map('rawurlencode', $parts));
}

$parsedUrl = parse_url($videoUrl);
$host = strtolower((string) ($parsedUrl['host'] ?? ''));
$allowedHosts = ['cctv.pomplay.com.pe', 'pomplay.com.pe', strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))];
$isAllowed = false;
foreach ($allowedHosts as $allowedHost) {
    if ($allowedHost !== '' && ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost))) {
        $isAllowed = true;
        break;
    }
}

if (!$parsedUrl || !isset($parsedUrl['host']) || !in_array(strtolower($parsedUrl['scheme'] ?? ''), ['http', 'https'], true) || !$isAllowed) {
    http_response_code(403);
    echo 'Video source domain not allowed';
    exit;
}

if (strtolower($parsedUrl['host']) === 'cctv.pomplay.com.pe') {
    $videoPath = rawurldecode((string) ($parsedUrl['path'] ?? ''));
    if (!preg_match('#^/videos/(\d+)/([A-Za-z0-9_-]+)/([A-Za-z0-9_.-]+\.mp4)$#i', $videoPath, $matches)) {
        http_response_code(404);
        echo 'Video source path is not supported';
        exit;
    }

    $secret = (string) env('VPS_KEY', '');
    if ($secret === '') {
        http_response_code(503);
        echo 'Video proxy is not configured';
        exit;
    }

    $relativePath = $matches[1] . '/' . $matches[2] . '/' . $matches[3];
    $filename = $matches[3];
    $expires = time() + 3600;
    $signature = hash_hmac('sha256', $relativePath . "\n" . $filename . "\n" . $expires, $secret);
    $videoUrl = 'https://cctv.pomplay.com.pe/download.php?' . http_build_query([
        'path' => $relativePath,
        'filename' => $filename,
        'expires' => $expires,
        'signature' => $signature,
    ]);
}

// ── Leer Range request del cliente (móvil/Safari lo envía siempre) ────────
$rangeHeader = $_SERVER['HTTP_RANGE'] ?? null;

// ── Primero hacer HEAD para obtener el tamaño total del archivo ───────────
$chHead = curl_init($videoUrl);
curl_setopt_array($chHead, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_NOBODY         => true,   // HEAD request
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ],
]);
curl_exec($chHead);
$totalSize   = (int) curl_getinfo($chHead, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
$contentType = curl_getinfo($chHead, CURLINFO_CONTENT_TYPE) ?: 'video/mp4';
$headCode    = (int) curl_getinfo($chHead, CURLINFO_HTTP_CODE);
curl_close($chHead);

// Si el servidor remoto no informa Content-Length, intentar de todas formas
if ($totalSize <= 0) $totalSize = 0;

// ── Parsear el Range solicitado ───────────────────────────────────────────
$rangeStart = 0;
$rangeEnd   = $totalSize > 0 ? $totalSize - 1 : 0;
$isRange    = false;

if ($rangeHeader && preg_match('/bytes=(\d*)-(\d*)/i', $rangeHeader, $m)) {
    $isRange = true;
    $rangeStart = $m[1] !== '' ? (int)$m[1] : 0;
    $rangeEnd   = $m[2] !== '' ? (int)$m[2] : ($totalSize > 0 ? $totalSize - 1 : 0);

    // Validar rango
    if ($totalSize > 0 && $rangeEnd >= $totalSize) {
        $rangeEnd = $totalSize - 1;
    }
    if ($rangeStart > $rangeEnd) {
        http_response_code(416); // Range Not Satisfiable
        if ($totalSize > 0) {
            header("Content-Range: bytes */{$totalSize}");
        }
        exit;
    }
}

$chunkLength = $rangeEnd >= $rangeStart ? ($rangeEnd - $rangeStart + 1) : 0;

// ── Hacer la solicitud al servidor remoto con el mismo Range ─────────────
$ch = curl_init($videoUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => false,    // ← CLAVE: streaming directo, sin buffer en RAM
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 300,
    CURLOPT_BUFFERSIZE     => 128 * 1024,  // 128 KB chunks
    CURLOPT_HTTPHEADER     => array_filter([
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        $isRange ? "Range: bytes={$rangeStart}-{$rangeEnd}" : null,
    ]),
    // Escribir directamente al output de PHP sin bufferizar
    CURLOPT_WRITEFUNCTION  => function ($curl, $data) {
        echo $data;
        return strlen($data);
    },
]);

// ── Enviar headers de respuesta ANTES de transferir el cuerpo ────────────
header('Content-Type: ' . $contentType);
header('Accept-Ranges: bytes');
header('Cache-Control: private, no-store');
header('Cross-Origin-Resource-Policy: cross-origin');

// Headers CORS adicionales
header('Access-Control-Allow-Origin: *');
header('Access-Control-Expose-Headers: Content-Range, Content-Length, Accept-Ranges');

if ($isRange) {
    http_response_code(206); // Partial Content
    if ($totalSize > 0) {
        header("Content-Range: bytes {$rangeStart}-{$rangeEnd}/{$totalSize}");
        header('Content-Length: ' . $chunkLength);
    } else {
        // Sin tamaño conocido — enviar sin Content-Length
        header("Content-Range: bytes {$rangeStart}-{$rangeEnd}/*");
    }
} else {
    http_response_code(200);
    if ($totalSize > 0) {
        header('Content-Length: ' . $totalSize);
    }
}

// ── Deshabilitar output buffering para streaming real ────────────────────
if (ob_get_level()) {
    ob_end_flush();
}
flush();

// ── Transferir el video en streaming ─────────────────────────────────────
curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// (Los errores ya no se pueden reportar una vez que empezamos a escribir el body)
